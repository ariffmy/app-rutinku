<?php

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Models\RoutineDayModel;
use App\Models\RoutineModel;
use App\Models\RoutineTaskModel;
use App\Models\TaskCompletionModel;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

class RoutineService
{
    private BaseConnection $db;

    public function __construct(
        private readonly ?RoutineModel $routines = null,
        private readonly ?RoutineDayModel $routineDays = null,
        private readonly ?RoutineTaskModel $routineTasks = null,
        private readonly ?TaskCompletionModel $taskCompletions = null,
        private readonly ?FamilyAuthorizationService $authorization = null,
        private readonly ?FamilyService $families = null,
        ?BaseConnection $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function listForParent(int $parentUserId, ?int $childUserId = null): array
    {
        $family = ($this->families ?? new FamilyService())->currentFamilyForUser($parentUserId);
        if ($family === null) {
            return [];
        }

        $model = $this->routines ?? new RoutineModel();
        $model->select('routines.*, users.name AS child_name')
            ->join('users', 'users.id = routines.child_user_id')
            ->join('family_users', 'family_users.user_id = users.id')
            ->where('family_users.family_id', (int) $family['id'])
            ->where('users.role', 'child');

        if ($childUserId !== null) {
            if (! ($this->authorization ?? new FamilyAuthorizationService())->parentCanManageChild($parentUserId, $childUserId)) {
                throw new AuthorizationException('Ibu bapa tidak boleh melihat rutin anak ini.');
            }
            $model->where('routines.child_user_id', $childUserId);
        }

        $routines = $model
            ->orderBy('users.name', 'ASC')
            ->orderBy('routines.sort_order', 'ASC')
            ->orderBy('routines.start_time', 'ASC')
            ->findAll();

        foreach ($routines as &$routine) {
            $routine['days'] = $this->daysForRoutine((int) $routine['id']);
            $routine['task_count'] = ($this->routineTasks ?? new RoutineTaskModel())
                ->where('routine_id', (int) $routine['id'])
                ->countAllResults();
        }

        return $routines;
    }

    public function getForParent(int $parentUserId, int $routineId): array
    {
        $this->assertParentCanManageRoutine($parentUserId, $routineId);
        $routine = ($this->routines ?? new RoutineModel())->find($routineId);
        $routine['days'] = $this->daysForRoutine($routineId);
        $routine['tasks'] = $this->tasksForRoutine($routineId);

        return $routine;
    }

    public function create(int $parentUserId, array $data, array $days): int
    {
        $childUserId = (int) ($data['child_user_id'] ?? 0);
        if (! ($this->authorization ?? new FamilyAuthorizationService())->parentCanManageChild($parentUserId, $childUserId)) {
            throw new AuthorizationException('Ibu bapa tidak boleh mencipta rutin untuk anak ini.');
        }

        $days = $this->normalizeDays($days);
        $payload = $this->routinePayload($data, $childUserId);

        return $this->insertRoutines([$payload], $days)[0];
    }

    /** @return list<int> IDs of independent routines for the family's currently active children. */
    public function createForAllChildren(int $parentUserId, array $data, array $days): array
    {
        $families = $this->families ?? new FamilyService();
        $family = $families->currentFamilyForUser($parentUserId);
        if ($family === null) {
            throw new AuthorizationException('Ibu bapa mesti menjadi ahli satu keluarga.');
        }

        $authorization = $this->authorization ?? new FamilyAuthorizationService();
        $payloads = [];
        foreach ($families->children((int) $family['id']) as $child) {
            if (! $child['is_active']) {
                continue;
            }

            $childUserId = (int) $child['id'];
            if (! $authorization->parentCanManageChild($parentUserId, $childUserId)) {
                throw new AuthorizationException('Ibu bapa tidak boleh mencipta rutin untuk anak ini.');
            }
            $payloads[] = $this->routinePayload($data, $childUserId);
        }

        if ($payloads === []) {
            throw new InvalidArgumentException('Tiada anak aktif. Tambah atau aktifkan anak dahulu.');
        }

        return $this->insertRoutines($payloads, $this->normalizeDays($days));
    }

    public function update(int $parentUserId, int $routineId, array $data, array $days): void
    {
        $routine = $this->getForParent($parentUserId, $routineId);
        $childUserId = (int) ($data['child_user_id'] ?? 0);
        if (! ($this->authorization ?? new FamilyAuthorizationService())->parentCanManageChild($parentUserId, $childUserId)) {
            throw new AuthorizationException('Ibu bapa tidak boleh memindahkan rutin kepada anak tersebut.');
        }

        $days = $this->normalizeDays($days);
        $payload = $this->routinePayload($data, $childUserId);
        $routines = $this->routines ?? new RoutineModel();

        $this->db->transException(true)->transStart();
        try {
            if (! $routines->update($routineId, $payload)) {
                throw new InvalidArgumentException(implode(' ', $routines->errors()));
            }
            $this->replaceDays($routineId, $days);
            $this->db->transComplete();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function delete(int $parentUserId, int $routineId): string
    {
        $this->assertParentCanManageRoutine($parentUserId, $routineId);
        $hasHistory = ($this->taskCompletions ?? new TaskCompletionModel())
            ->join('routine_tasks', 'routine_tasks.id = task_completions.routine_task_id')
            ->where('routine_tasks.routine_id', $routineId)
            ->countAllResults() > 0;

        if (! $hasHistory) {
            ($this->routines ?? new RoutineModel())->delete($routineId);

            return 'deleted';
        }

        $this->db->transException(true)->transStart();
        try {
            ($this->routines ?? new RoutineModel())->update($routineId, ['is_active' => 0]);
            $this->db->table('routine_tasks')->where('routine_id', $routineId)->update(['is_active' => 0]);
            $this->db->transComplete();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return 'archived';
    }

    public function getTaskForParent(int $parentUserId, int $taskId): array
    {
        if (! ($this->authorization ?? new FamilyAuthorizationService())->parentCanManageRoutineTask($parentUserId, $taskId)) {
            throw new AuthorizationException('Ibu bapa tidak boleh mengurus tugasan rutin ini.');
        }

        $task = ($this->routineTasks ?? new RoutineTaskModel())->find($taskId);
        $task['routine'] = $this->getForParent($parentUserId, (int) $task['routine_id']);

        return $task;
    }

    public function createTask(int $parentUserId, int $routineId, array $data): int
    {
        $this->assertParentCanManageRoutine($parentUserId, $routineId);
        $tasks = $this->routineTasks ?? new RoutineTaskModel();
        $taskId = $tasks->insert($this->taskPayload($data, $routineId), true);

        if ($taskId === false) {
            throw new InvalidArgumentException(implode(' ', $tasks->errors()));
        }

        return (int) $taskId;
    }

    public function updateTask(int $parentUserId, int $taskId, array $data): int
    {
        $task = $this->getTaskForParent($parentUserId, $taskId);
        $tasks = $this->routineTasks ?? new RoutineTaskModel();

        if (! $tasks->update($taskId, $this->taskPayload($data, (int) $task['routine_id']))) {
            throw new InvalidArgumentException(implode(' ', $tasks->errors()));
        }

        return (int) $task['routine_id'];
    }

    public function deleteTask(int $parentUserId, int $taskId): array
    {
        $task = $this->getTaskForParent($parentUserId, $taskId);
        $hasHistory = ($this->taskCompletions ?? new TaskCompletionModel())
            ->where('routine_task_id', $taskId)
            ->countAllResults() > 0;

        if ($hasHistory) {
            ($this->routineTasks ?? new RoutineTaskModel())->update($taskId, ['is_active' => 0]);
        } else {
            ($this->routineTasks ?? new RoutineTaskModel())->delete($taskId);
        }

        return [
            'routine_id' => (int) $task['routine_id'],
            'action' => $hasHistory ? 'archived' : 'deleted',
        ];
    }

    public function tasksForRoutine(int $routineId): array
    {
        return ($this->routineTasks ?? new RoutineTaskModel())
            ->where('routine_id', $routineId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('task_time', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function daysForRoutine(int $routineId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['day_of_week'],
            ($this->routineDays ?? new RoutineDayModel())
                ->where('routine_id', $routineId)
                ->orderBy('day_of_week', 'ASC')
                ->findAll(),
        );
    }

    private function assertParentCanManageRoutine(int $parentUserId, int $routineId): void
    {
        if (! ($this->authorization ?? new FamilyAuthorizationService())->parentCanManageRoutine($parentUserId, $routineId)) {
            throw new AuthorizationException('Ibu bapa tidak boleh mengurus rutin ini.');
        }
    }

    /** Save every child and schedule together, or roll back the entire operation. */
    private function insertRoutines(array $payloads, array $days): array
    {
        $routines = $this->routines ?? new RoutineModel();
        $routineIds = [];
        $this->db->transException(true)->transStart();
        try {
            foreach ($payloads as $payload) {
                $routineId = $routines->insert($payload, true);
                if ($routineId === false) {
                    throw new InvalidArgumentException(implode(' ', $routines->errors()));
                }

                $this->replaceDays((int) $routineId, $days);
                $routineIds[] = (int) $routineId;
            }
            $this->db->transComplete();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return $routineIds;
    }

    private function replaceDays(int $routineId, array $days): void
    {
        $model = $this->routineDays ?? new RoutineDayModel();
        $model->where('routine_id', $routineId)->delete();

        foreach ($days as $day) {
            if ($model->insert(['routine_id' => $routineId, 'day_of_week' => $day]) === false) {
                throw new InvalidArgumentException(implode(' ', $model->errors()));
            }
        }
    }

    private function normalizeDays(array $days): array
    {
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        if ($days === [] || array_filter($days, static fn (int $day): bool => $day < 1 || $day > 7) !== []) {
            throw new InvalidArgumentException('Pilih sekurang-kurangnya satu hari yang sah.');
        }

        return $days;
    }

    private function routinePayload(array $data, int $childUserId): array
    {
        return [
            'child_user_id' => $childUserId,
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'type' => $this->normalizeNullable($data['type'] ?? null),
            'start_time' => $this->normalizeTime($data['start_time'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
        ];
    }

    private function taskPayload(array $data, int $routineId): array
    {
        return [
            'routine_id' => $routineId,
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'task_time' => $this->normalizeTime($data['task_time'] ?? null),
            'points' => (int) ($data['points'] ?? 0),
            'is_required' => ! empty($data['is_required']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
        ];
    }

    private function normalizeNullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = $this->normalizeNullable($value);
        if ($value === null) {
            return null;
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }
}
