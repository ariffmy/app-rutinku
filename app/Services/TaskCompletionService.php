<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\TaskCompletionException;
use App\Models\AuditLogModel;
use App\Models\RoutineDayModel;
use App\Models\RoutineModel;
use App\Models\RoutineTaskModel;
use App\Models\TaskCompletionModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class TaskCompletionService
{
    private BaseConnection $db;

    public function __construct(
        private readonly ?TaskCompletionModel $completions = null,
        private readonly ?RoutineTaskModel $routineTasks = null,
        private readonly ?RoutineModel $routines = null,
        private readonly ?RoutineDayModel $routineDays = null,
        private readonly ?UserModel $users = null,
        private readonly ?TodayTaskResolver $todayTasks = null,
        private readonly ?AuditLogService $auditLogs = null,
        private readonly ?PointService $points = null,
        ?BaseConnection $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function completeTask(int $childUserId, int $routineTaskId, DateTimeInterface $at): array
    {
        $local = $this->localTime($at);
        $this->assertActiveChild($childUserId);

        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childUserId);
            $task = $this->eligibleTask($childUserId, $routineTaskId, $local);
            $completions = $this->completions ?? new TaskCompletionModel();

            if ($completions
                ->where('child_user_id', $childUserId)
                ->where('routine_task_id', $routineTaskId)
                ->where('completion_date', $local->format('Y-m-d'))
                ->first() !== null) {
                throw new TaskCompletionException('Tugasan ini sudah disiapkan hari ini.');
            }

            $completionId = $completions->insert([
                'child_user_id' => $childUserId,
                'routine_task_id' => $routineTaskId,
                'completion_date' => $local->format('Y-m-d'),
                'completed_at' => $local->format('Y-m-d H:i:s'),
                'points_awarded' => (int) $task['points'],
            ], true);

            if ($completionId === false) {
                throw new TaskCompletionException('Tugasan tidak dapat ditandakan selesai.');
            }

            ($this->points ?? new PointService(db: $this->db))->awardTaskPoints(
                $childUserId,
                (int) $completionId,
            );

            $this->db->transComplete();
        } catch (TaskCompletionException $exception) {
            $this->db->transRollback();
            throw $exception;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            if ($this->isDuplicateError($exception)) {
                throw new TaskCompletionException('Tugasan ini sudah disiapkan hari ini.', 0, $exception);
            }
            throw $exception;
        }

        return ($this->completions ?? new TaskCompletionModel())->find((int) $completionId);
    }

    public function undoTask(int $childUserId, int $routineTaskId, DateTimeInterface $at): void
    {
        $local = $this->localTime($at);
        $this->assertActiveChild($childUserId);

        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childUserId);
            $completions = $this->completions ?? new TaskCompletionModel();
            $completion = $completions
                ->where('child_user_id', $childUserId)
                ->where('routine_task_id', $routineTaskId)
                ->where('completion_date', $local->format('Y-m-d'))
                ->first();

            if ($completion === null) {
                throw new TaskCompletionException('Tugasan ini belum disiapkan hari ini atau tidak boleh dibatalkan.');
            }

            ($this->points ?? new PointService(db: $this->db))->reverseTaskPoints(
                $childUserId,
                (int) $completion['id'],
                $local,
                $childUserId,
            );

            if (! $completions->delete((int) $completion['id'])) {
                throw new TaskCompletionException('Penyelesaian tidak dapat dibatalkan.');
            }

            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'task.completion_undone',
                $childUserId,
                $childUserId,
                'task_completion',
                (int) $completion['id'],
                'Anak membatalkan penyelesaian pada hari yang sama.',
                [
                    'routine_task_id' => (int) $completion['routine_task_id'],
                    'completion_date' => $completion['completion_date'],
                    'completed_at' => $completion['completed_at'],
                    'points_awarded' => (int) $completion['points_awarded'],
                ],
                ['active' => false],
            );

            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function getTodayProgress(int $childUserId, DateTimeInterface $at): array
    {
        $schedule = ($this->todayTasks ?? new TodayTaskResolver())->resolve($childUserId, $at);
        $rows = ($this->completions ?? new TaskCompletionModel())
            ->where('child_user_id', $childUserId)
            ->where('completion_date', $schedule['date'])
            ->findAll();
        $byTask = [];
        foreach ($rows as $row) {
            $byTask[(int) $row['routine_task_id']] = $row;
        }

        $completed = 0;
        $requiredCompleted = 0;
        $snapshotPoints = 0;

        foreach ($schedule['routines'] as &$routine) {
            foreach ($routine['tasks'] as &$task) {
                $completion = $byTask[(int) $task['id']] ?? null;
                $task['is_completed'] = $completion !== null;
                $task['completion_id'] = $completion === null ? null : (int) $completion['id'];
                $task['completed_at'] = $completion['completed_at'] ?? null;
                $task['points_awarded'] = $completion === null ? null : (int) $completion['points_awarded'];

                if ($completion !== null) {
                    ++$completed;
                    $snapshotPoints += (int) $completion['points_awarded'];
                    if ((bool) $task['is_required']) {
                        ++$requiredCompleted;
                    }
                }
            }
            unset($task);
        }
        unset($routine);

        $schedule['completed_count'] = $completed;
        $schedule['total_count'] = (int) $schedule['task_count'];
        $schedule['required_completed_count'] = $requiredCompleted;
        $schedule['required_total_count'] = (int) $schedule['required_task_count'];
        $schedule['completion_percentage'] = $schedule['task_count'] === 0
            ? 0
            : (int) round(($completed / $schedule['task_count']) * 100);
        $schedule['completed_snapshot_points'] = $snapshotPoints;

        return $schedule;
    }

    private function eligibleTask(int $childUserId, int $routineTaskId, \DateTimeInterface $date): array
    {
        $task = ($this->routineTasks ?? new RoutineTaskModel())->find($routineTaskId);
        if ($task === null || ! (bool) $task['is_active']) {
            throw new TaskCompletionException('Tugasan ini tidak tersedia.');
        }

        $routine = ($this->routines ?? new RoutineModel())->find((int) $task['routine_id']);
        if ($routine === null || ! (bool) $routine['is_active'] || (int) $routine['child_user_id'] !== $childUserId) {
            throw new TaskCompletionException('Tugasan ini tidak tersedia.');
        }

        $scheduled = ($this->routineDays ?? new RoutineDayModel())
            ->where('routine_id', (int) $routine['id'])
            ->findAll();
        if (! (new TaskScheduleService())->isScheduled($task, $date, array_column($scheduled, 'day_of_week'))) {
            throw new TaskCompletionException('Tugasan ini tidak dijadualkan hari ini.');
        }

        return $task;
    }

    private function assertActiveChild(int $childUserId): void
    {
        $child = ($this->users ?? new UserModel())->find($childUserId);
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD) {
            throw new TaskCompletionException('Identiti Anak tidak sah.');
        }
    }

    private function lockChild(int $childUserId): void
    {
        if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
            $this->db->query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$childUserId]);
        }
    }

    private function localTime(DateTimeInterface $at): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone(app_timezone()));
    }

    private function isDuplicateError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'unique constraint');
    }
}
