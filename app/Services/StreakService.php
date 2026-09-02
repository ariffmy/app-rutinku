<?php

namespace App\Services;

use App\Models\RoutineModel;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class StreakService
{
    public function __construct(
        private readonly ?TaskCompletionService $completions = null,
        private readonly ?RoutineModel $routines = null,
    ) {
    }

    public function evaluateDay(int $childUserId, DateTimeInterface $date): array
    {
        $local = $this->localDate($date);
        $progress = ($this->completions ?? new TaskCompletionService())
            ->getTodayProgress($childUserId, $local);
        $requiredTotal = (int) $progress['required_total_count'];
        $requiredCompleted = (int) $progress['required_completed_count'];

        return [
            'date' => $local->format('Y-m-d'),
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
            'qualifies' => $requiredTotal > 0,
            'is_perfect' => $requiredTotal > 0 && $requiredCompleted === $requiredTotal,
        ];
    }

    public function perfectDaysBetween(
        int $childUserId,
        DateTimeInterface $start,
        DateTimeInterface $end,
    ): int {
        $startDate = $this->localDate($start);
        $endDate = $this->localDate($end);
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Julat tarikh streak tidak sah.');
        }

        $perfectDays = 0;
        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate->modify('+1 day'));
        foreach ($period as $date) {
            if ($this->evaluateDay($childUserId, $date)['is_perfect']) {
                ++$perfectDays;
            }
        }

        return $perfectDays;
    }

    public function currentStreak(int $childUserId, DateTimeInterface $asOf): int
    {
        $date = $this->localDate($asOf);
        $earliest = $this->earliestActiveRoutineDate($childUserId);
        if ($earliest === null) {
            return 0;
        }

        $today = $this->localDate(new DateTimeImmutable('now', new DateTimeZone(app_timezone())));
        if ($date > $today) {
            $date = $today;
        }

        $streak = 0;
        $isFirstDate = true;
        while ($date >= $earliest) {
            $day = $this->evaluateDay($childUserId, $date);
            if (! $day['qualifies']) {
                $date = $date->modify('-1 day');
                $isFirstDate = false;
                continue;
            }

            if ($day['is_perfect']) {
                ++$streak;
            } elseif ($isFirstDate && $date->format('Y-m-d') === $today->format('Y-m-d')) {
                // Today remains in progress and does not break yesterday's streak.
            } else {
                break;
            }

            $date = $date->modify('-1 day');
            $isFirstDate = false;
        }

        return $streak;
    }

    private function earliestActiveRoutineDate(int $childUserId): ?DateTimeImmutable
    {
        $routine = ($this->routines ?? new RoutineModel())
            ->where('child_user_id', $childUserId)
            ->where('is_active', true)
            ->orderBy('created_at', 'ASC')
            ->first();
        if ($routine === null) {
            return null;
        }

        return new DateTimeImmutable(substr((string) $routine['created_at'], 0, 10), new DateTimeZone(app_timezone()));
    }

    private function localDate(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone(app_timezone()))
            ->setTime(0, 0);
    }
}
