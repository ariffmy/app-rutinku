<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\RoutineDayModel;
use App\Models\RoutineModel;
use App\Models\RoutineTaskModel;
use App\Models\UserModel;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class TodayTaskResolver
{
    public function __construct(
        private readonly ?UserModel $users = null,
        private readonly ?RoutineModel $routines = null,
        private readonly ?RoutineDayModel $routineDays = null,
        private readonly ?RoutineTaskModel $routineTasks = null,
    ) {
    }

    public function resolve(int $childUserId, DateTimeInterface $date): array
    {
        $child = ($this->users ?? new UserModel())->find($childUserId);
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD) {
            return $this->emptyResult($date);
        }

        $localDate = DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone(app_timezone()));
        $weekday = (int) $localDate->format('N');

        $scheduledRoutineIds = array_column(
            ($this->routineDays ?? new RoutineDayModel())
                ->select('routine_id')
                ->where('day_of_week', $weekday)
                ->findAll(),
            'routine_id',
        );

        if ($scheduledRoutineIds === []) {
            return $this->emptyResult($localDate);
        }

        $routines = ($this->routines ?? new RoutineModel())
            ->where('child_user_id', $childUserId)
            ->where('is_active', true)
            ->whereIn('id', array_map('intval', $scheduledRoutineIds))
            ->orderBy('sort_order', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $taskCount = 0;
        $requiredTaskCount = 0;
        $availablePoints = 0;

        foreach ($routines as &$routine) {
            $tasks = ($this->routineTasks ?? new RoutineTaskModel())
                ->where('routine_id', (int) $routine['id'])
                ->where('is_active', true)
                ->orderBy('sort_order', 'ASC')
                ->orderBy('task_time', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();

            $routine['tasks'] = $tasks;
            $taskCount += count($tasks);
            $requiredTaskCount += count(array_filter($tasks, static fn (array $task): bool => (bool) $task['is_required']));
            $availablePoints += array_sum(array_map(static fn (array $task): int => (int) $task['points'], $tasks));
        }

        return [
            'date' => $localDate->format('Y-m-d'),
            'weekday' => $weekday,
            'routines' => $routines,
            'task_count' => $taskCount,
            'required_task_count' => $requiredTaskCount,
            'available_points' => $availablePoints,
        ];
    }

    private function emptyResult(DateTimeInterface $date): array
    {
        $localDate = DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone(app_timezone()));

        return [
            'date' => $localDate->format('Y-m-d'),
            'weekday' => (int) $localDate->format('N'),
            'routines' => [],
            'task_count' => 0,
            'required_task_count' => 0,
            'available_points' => 0,
        ];
    }
}
