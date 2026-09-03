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

class RankingService
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
            static fn (array $child): bool => (bool) $child['is_active'] && (bool) $child['is_ranking_eligible'],
        ));
        $rows = [];
        $today = $this->localDate(new DateTimeImmutable('now', new DateTimeZone(app_timezone())));
        $calculationEnd = $start > $today ? null : ($end > $today ? $today : $end);
        $streakAsOf = $calculationEnd ?? $today;

        foreach ($children as $child) {
            $childId = (int) $child['id'];
            $completed = 0;
            $scheduled = 0;
            $perfectDays = 0;
            if ($calculationEnd !== null) {
                $period = new DatePeriod($start, new DateInterval('P1D'), $calculationEnd->modify('+1 day'));
                foreach ($period as $day) {
                    $progress = ($this->completions ?? new TaskCompletionService())->getTodayProgress($childId, $day);
                    $completed += (int) $progress['completed_count'];
                    $scheduled += (int) $progress['total_count'];
                    if ((int) $progress['required_total_count'] > 0
                        && (int) $progress['required_completed_count'] === (int) $progress['required_total_count']) {
                        ++$perfectDays;
                    }
                }
            }

            $rows[] = [
                'child_user_id' => $childId,
                'name' => $child['name'],
                'avatar' => $child['avatar'],
                'earned_points' => $calculationEnd === null
                    ? 0
                    : ($this->points ?? new PointService())->getEarnedPointsBetween($childId, $start, $calculationEnd),
                'completed_tasks' => $completed,
                'scheduled_tasks' => $scheduled,
                'completion_percentage' => $scheduled === 0 ? 0 : (int) round(($completed / $scheduled) * 100),
                'perfect_days' => $perfectDays,
                'is_perfect_day' => $periodType === 'daily' && $perfectDays === 1,
                'current_streak' => ($this->streaks ?? new StreakService())->currentStreak($childId, $streakAsOf),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            foreach (['earned_points', 'completion_percentage', 'perfect_days', 'current_streak'] as $field) {
                $comparison = $right[$field] <=> $left[$field];
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return strcasecmp((string) $left['name'], (string) $right['name']);
        });
        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return [
            'period_type' => $periodType,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'rows' => $rows,
            'total_scheduled_tasks' => array_sum(array_column($rows, 'scheduled_tasks')),
            'total_completed_tasks' => array_sum(array_column($rows, 'completed_tasks')),
        ];
    }

    private function parentFamily(int $parentUserId): array
    {
        $parent = ($this->users ?? new UserModel())->find($parentUserId);
        $family = ($this->families ?? new FamilyService())->currentFamilyForUser($parentUserId);
        if ($parent === null || ! $parent->is_active || $parent->roleEnum() !== UserRole::PARENT || $family === null) {
            throw new AuthorizationException('Maklumat keluarga ibu bapa tidak sah.');
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
