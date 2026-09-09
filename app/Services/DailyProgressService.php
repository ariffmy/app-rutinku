<?php

namespace App\Services;

use DateTimeInterface;

class DailyProgressService
{
    public function forChild(int $childUserId, DateTimeInterface $at): array
    {
        return $this->fromSchedule((new TaskCompletionService())->getTodayProgress($childUserId, $at));
    }

    /** Reuse the resolved dashboard schedule without issuing additional queries. */
    public function fromSchedule(array $schedule): array
    {
        $completed = 0;
        $total = 0;
        foreach ($schedule['routines'] as $routine) {
            if (! (bool) ($routine['is_required'] ?? true)) {
                continue;
            }
            $requiredTasks = array_filter($routine['tasks'], static fn (array $task): bool => (bool) $task['is_required']);
            // Empty routines and routines with only optional tasks cannot inflate progress.
            if ($requiredTasks === []) {
                continue;
            }
            ++$total;
            $unfinished = array_filter($requiredTasks, static fn (array $task): bool => $task['completion_status'] !== 'completed');
            if ($unfinished === []) {
                ++$completed;
            }
        }

        return [
            'completed_count' => $completed,
            'total_count' => $total,
            'percentage' => $total === 0 ? 0 : (int) round($completed * 100 / $total),
        ];
    }
}
