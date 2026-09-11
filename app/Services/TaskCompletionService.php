<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\AuthorizationException;
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
                'status' => ! empty($task['requires_approval']) ? 'pending' : 'completed',
                'rejection_reason' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
            ], true);

            if ($completionId === false) {
                throw new TaskCompletionException('Tugasan tidak dapat ditandakan selesai.');
            }

            if (empty($task['requires_approval'])) {
                ($this->points ?? new PointService(db: $this->db))->awardTaskPoints(
                    $childUserId,
                    (int) $completionId,
                );
                (new PerfectDayService($this->db))->awardIfQualified($childUserId, $local);
                (new AchievementService($this->db))->checkAll($childUserId, $local);
                (new MissionService($this->db))->checkForChild($childUserId, $local);
            }

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

            if (($completion['status'] ?? 'completed') !== 'completed') {
                throw new TaskCompletionException('Hanya tugasan yang telah diterima boleh dibatalkan.');
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
                $status = $completion['status'] ?? ($completion === null ? 'not_completed' : 'completed');
                $task['completion_status'] = $status;
                $task['rejection_reason'] = $completion['rejection_reason'] ?? null;
                $task['is_completed'] = $status === 'completed';
                $task['completion_id'] = $completion === null ? null : (int) $completion['id'];
                $task['completed_at'] = $completion['completed_at'] ?? null;
                $task['points_awarded'] = $completion === null ? null : (int) $completion['points_awarded'];

                if ($status === 'completed') {
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

    public function pendingForParent(int $parentUserId): array
    {
        $parent = ($this->users ?? new UserModel())->find($parentUserId);
        $family = (new FamilyService())->currentFamilyForUser($parentUserId);
        if ($parent === null || ! $parent->is_active || $parent->roleEnum() !== UserRole::PARENT || $family === null) {
            throw new AuthorizationException('Ibu bapa tidak sah.');
        }

        return $this->db->table('task_completions completion')
            ->select('completion.*, task.title AS task_title, routine.name AS routine_name, child.name AS child_name')
            ->join('routine_tasks task', 'task.id = completion.routine_task_id')
            ->join('routines routine', 'routine.id = task.routine_id')
            ->join('users child', 'child.id = completion.child_user_id')
            ->join('family_users membership', 'membership.user_id = child.id')
            ->where('membership.family_id', (int) $family['id'])
            ->where('completion.status', 'pending')
            ->orderBy('completion.completed_at', 'ASC')
            ->get()->getResultArray();
    }

    public function approveCompletion(int $parentUserId, int $completionId, DateTimeInterface $at): array
    {
        $completion = ($this->completions ?? new TaskCompletionModel())->find($completionId);
        if ($completion === null || ! (new FamilyAuthorizationService())->parentCanManageChild($parentUserId, (int) $completion['child_user_id'])) {
            throw new AuthorizationException('Ibu bapa tidak boleh meluluskan penyelesaian ini.');
        }

        $local = $this->localTime($at);
        $this->db->transException(true)->transStart();
        try {
            $this->lockChild((int) $completion['child_user_id']);
            $this->lockCompletion($completionId);
            $completion = ($this->completions ?? new TaskCompletionModel())->find($completionId);
            if ($completion === null || ($completion['status'] ?? 'completed') !== 'pending') {
                throw new TaskCompletionException('Penyelesaian ini bukan lagi menunggu kelulusan.');
            }
            if (! (new FamilyAuthorizationService())->parentCanManageChild($parentUserId, (int) $completion['child_user_id'])) {
                throw new AuthorizationException('Ibu bapa tidak boleh meluluskan penyelesaian ini.');
            }

            if (! ($this->completions ?? new TaskCompletionModel())->update($completionId, [
                'status' => 'completed',
                'rejection_reason' => null,
                'reviewed_at' => $local->format('Y-m-d H:i:s'),
                'reviewed_by_user_id' => $parentUserId,
            ])) {
                throw new TaskCompletionException('Kelulusan tidak dapat disimpan.');
            }
            ($this->points ?? new PointService(db: $this->db))->awardTaskPoints((int) $completion['child_user_id'], $completionId);
            (new PerfectDayService($this->db))->awardIfQualified(
                (int) $completion['child_user_id'],
                new DateTimeImmutable((string) $completion['completion_date'], new DateTimeZone(app_timezone())),
            );
            (new AchievementService($this->db))->checkAll((int) $completion['child_user_id'], $local);
            (new MissionService($this->db))->checkForChild((int) $completion['child_user_id'], $local);
            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'task.completion_approved', $parentUserId, (int) $completion['child_user_id'],
                'task_completion', $completionId, 'Ibu bapa meluluskan penyelesaian tugasan.',
                ['status' => 'pending'], ['status' => 'completed', 'points_awarded' => (int) $completion['points_awarded']],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ($this->completions ?? new TaskCompletionModel())->find($completionId);
    }

    public function rejectCompletion(int $parentUserId, int $completionId, ?string $reason, DateTimeInterface $at): array
    {
        $completion = ($this->completions ?? new TaskCompletionModel())->find($completionId);
        if ($completion === null || ! (new FamilyAuthorizationService())->parentCanManageChild($parentUserId, (int) $completion['child_user_id'])) {
            throw new AuthorizationException('Ibu bapa tidak boleh menolak penyelesaian ini.');
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason) > 500) {
            throw new TaskCompletionException('Sebab penolakan tidak boleh melebihi 500 aksara.');
        }

        $local = $this->localTime($at);
        $this->db->transException(true)->transStart();
        try {
            $this->lockChild((int) $completion['child_user_id']);
            $this->lockCompletion($completionId);
            $completion = ($this->completions ?? new TaskCompletionModel())->find($completionId);
            if ($completion === null || ($completion['status'] ?? 'completed') !== 'pending') {
                throw new TaskCompletionException('Penyelesaian ini bukan lagi menunggu kelulusan.');
            }
            if (! (new FamilyAuthorizationService())->parentCanManageChild($parentUserId, (int) $completion['child_user_id'])) {
                throw new AuthorizationException('Ibu bapa tidak boleh menolak penyelesaian ini.');
            }
            if (! ($this->completions ?? new TaskCompletionModel())->update($completionId, [
                'status' => 'rejected',
                'rejection_reason' => $reason === '' ? null : $reason,
                'reviewed_at' => $local->format('Y-m-d H:i:s'),
                'reviewed_by_user_id' => $parentUserId,
            ])) {
                throw new TaskCompletionException('Penolakan tidak dapat disimpan.');
            }
            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'task.completion_rejected', $parentUserId, (int) $completion['child_user_id'],
                'task_completion', $completionId, $reason === '' ? 'Ibu bapa menolak penyelesaian tugasan.' : $reason,
                ['status' => 'pending'], ['status' => 'rejected', 'rejection_reason' => $reason === '' ? null : $reason],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ($this->completions ?? new TaskCompletionModel())->find($completionId);
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

        $task['requires_approval'] = ! empty($task['requires_approval']) || ! empty($routine['requires_approval']);

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
            $table = $this->db->protectIdentifiers($this->db->prefixTable('users'));
            $this->db->query("SELECT id FROM {$table} WHERE id = ? FOR UPDATE", [$childUserId]);
        }
    }

    private function lockCompletion(int $completionId): void
    {
        if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
            $table = $this->db->protectIdentifiers($this->db->prefixTable('task_completions'));
            $this->db->query("SELECT id FROM {$table} WHERE id = ? FOR UPDATE", [$completionId]);
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
