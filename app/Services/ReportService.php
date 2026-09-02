<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\AuthorizationException;
use App\Models\UserModel;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class ReportService
{
    public function __construct(
        private readonly ?UserModel $users = null,
        private readonly ?FamilyService $families = null,
        private readonly ?TaskCompletionService $completions = null,
        private readonly ?PointService $points = null,
        private readonly ?StreakService $streaks = null,
    ) {
    }

    public function daily(int $parentUserId, DateTimeInterface $date): array
    {
        $local = $this->localDate($date);

        return $this->calculate($parentUserId, 'daily', $local, $local);
    }

    public function weekly(int $parentUserId, DateTimeInterface $date): array
    {
        $local = $this->localDate($date);
        $start = $local->modify('-' . ((int) $local->format('N') - 1) . ' days');

        return $this->calculate($parentUserId, 'weekly', $start, $start->modify('+6 days'));
    }

    public function monthly(int $parentUserId, DateTimeInterface $date): array
    {
        $start = $this->localDate($date)->modify('first day of this month');

        return $this->calculate($parentUserId, 'monthly', $start, $start->modify('last day of this month'));
    }

    private function calculate(
        int $parentUserId,
        string $periodType,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): array {
        $family = $this->parentFamily($parentUserId);
        $children = array_values(array_filter(
            ($this->families ?? new FamilyService())->children((int) $family['id']),
            static fn (array $child): bool => (bool) $child['is_active'],
        ));
        $today = $this->localDate(new DateTimeImmutable('now', new DateTimeZone(app_timezone())));
        $calculationEnd = $start > $today ? null : ($end > $today ? $today : $end);
        $streakAsOf = $calculationEnd ?? $today;
        $rows = [];
        $daily = [];

        if ($calculationEnd !== null) {
            foreach (new DatePeriod($start, new DateInterval('P1D'), $calculationEnd->modify('+1 day')) as $day) {
                $daily[$day->format('Y-m-d')] = [
                    'date' => $day->format('Y-m-d'),
                    'completed_tasks' => 0,
                    'scheduled_tasks' => 0,
                    'perfect_children' => 0,
                    'earned_points' => 0,
                ];
            }
        }

        foreach ($children as $child) {
            $childId = (int) $child['id'];
            $completed = 0;
            $scheduled = 0;
            $requiredCompleted = 0;
            $requiredScheduled = 0;
            $perfectDays = 0;
            $earnedPoints = 0;

            if ($calculationEnd !== null) {
                foreach (new DatePeriod($start, new DateInterval('P1D'), $calculationEnd->modify('+1 day')) as $day) {
                    $progress = ($this->completions ?? new TaskCompletionService())->getTodayProgress($childId, $day);
                    $dayCompleted = (int) $progress['completed_count'];
                    $dayScheduled = (int) $progress['total_count'];
                    $dayRequiredCompleted = (int) $progress['required_completed_count'];
                    $dayRequiredScheduled = (int) $progress['required_total_count'];
                    $isPerfect = $dayRequiredScheduled > 0 && $dayRequiredCompleted === $dayRequiredScheduled;
                    $dayEarned = ($this->points ?? new PointService())->getEarnedPointsBetween($childId, $day, $day);

                    $completed += $dayCompleted;
                    $scheduled += $dayScheduled;
                    $requiredCompleted += $dayRequiredCompleted;
                    $requiredScheduled += $dayRequiredScheduled;
                    $perfectDays += $isPerfect ? 1 : 0;
                    $earnedPoints += $dayEarned;

                    $key = $day->format('Y-m-d');
                    $daily[$key]['completed_tasks'] += $dayCompleted;
                    $daily[$key]['scheduled_tasks'] += $dayScheduled;
                    $daily[$key]['perfect_children'] += $isPerfect ? 1 : 0;
                    $daily[$key]['earned_points'] += $dayEarned;
                }
            }

            $rows[] = [
                'child_user_id' => $childId,
                'name' => $child['name'],
                'avatar' => $child['avatar'],
                'completed_tasks' => $completed,
                'scheduled_tasks' => $scheduled,
                'completion_percentage' => $scheduled === 0 ? 0 : (int) round(($completed / $scheduled) * 100),
                'required_completed_tasks' => $requiredCompleted,
                'required_scheduled_tasks' => $requiredScheduled,
                'perfect_days' => $perfectDays,
                'earned_points' => $earnedPoints,
                'current_balance' => ($this->points ?? new PointService())->getBalance($childId),
                'current_streak' => ($this->streaks ?? new StreakService())->currentStreak($childId, $streakAsOf),
            ];
        }

        foreach ($daily as &$day) {
            $day['completion_percentage'] = $day['scheduled_tasks'] === 0
                ? 0
                : (int) round(($day['completed_tasks'] / $day['scheduled_tasks']) * 100);
        }
        unset($day);

        $totalScheduled = array_sum(array_column($rows, 'scheduled_tasks'));
        $totalCompleted = array_sum(array_column($rows, 'completed_tasks'));

        return [
            'family' => $family,
            'period_type' => $periodType,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'calculation_end' => $calculationEnd?->format('Y-m-d'),
            'is_future' => $calculationEnd === null,
            'rows' => $rows,
            'daily_breakdown' => array_values($daily),
            'total_scheduled_tasks' => $totalScheduled,
            'total_completed_tasks' => $totalCompleted,
            'completion_percentage' => $totalScheduled === 0 ? 0 : (int) round(($totalCompleted / $totalScheduled) * 100),
            'total_perfect_days' => array_sum(array_column($rows, 'perfect_days')),
            'total_earned_points' => array_sum(array_column($rows, 'earned_points')),
        ];
    }

    private function parentFamily(int $parentUserId): array
    {
        $parent = ($this->users ?? new UserModel())->find($parentUserId);
        $family = ($this->families ?? new FamilyService())->currentFamilyForUser($parentUserId);
        if ($parent === null || ! $parent->is_active || $parent->roleEnum() !== UserRole::PARENT || $family === null) {
            throw new AuthorizationException('Parent family context is invalid.');
        }

        return $family;
    }

    private function localDate(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone(app_timezone()))
            ->setTime(0, 0);
    }
}
