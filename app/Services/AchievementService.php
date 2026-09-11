<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AchievementModel;
use App\Models\ChildAchievementModel;
use App\Models\PerfectDayModel;
use App\Models\PointTransactionModel;
use App\Models\TaskCompletionModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class AchievementService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return list<array<string, mixed>> Newly awarded achievements. */
    public function checkAll(int $childId, DateTimeInterface $at): array
    {
        $this->assertActiveChild($childId);
        $local = DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone(app_timezone()));
        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childId);
            $achievements = (new AchievementModel($this->db))->where('is_active', 1)->orderBy('id', 'ASC')->findAll();
            $metrics = $this->metrics($childId, $local, array_unique(array_column($achievements, 'condition_type')));
            $earned = new ChildAchievementModel($this->db);
            $awarded = [];

            foreach ($achievements as $achievement) {
                if (($metrics[$achievement['condition_type']] ?? 0) < (int) $achievement['condition_value']) {
                    continue;
                }
                $existing = $earned->where('child_id', $childId)
                    ->where('achievement_id', (int) $achievement['id'])->first();
                if ($existing !== null) {
                    continue;
                }
                $id = $earned->insert([
                    'child_id' => $childId,
                    'achievement_id' => (int) $achievement['id'],
                    'earned_at' => $local->format('Y-m-d H:i:s'),
                    'points_awarded' => (int) $achievement['points_reward'],
                    'created_at' => $local->format('Y-m-d H:i:s'),
                ], true);
                if ($id === false) {
                    throw new \RuntimeException('Pencapaian tidak dapat direkodkan.');
                }
                $record = $earned->find($id);
                if ((int) $record['points_awarded'] > 0) {
                    (new PointService(db: $this->db))->awardAchievementPoints($childId, (int) $record['id']);
                }
                $awarded[] = $achievement + ['child_achievement' => $record];
            }
            $this->db->transComplete();
            return $awarded;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            if ($this->isDuplicateError($exception)) {
                // The database unique key is authoritative during concurrent checks.
                return [];
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function catalogueForChild(int $childId): array
    {
        $this->assertActiveChild($childId);
        $rows = (new AchievementModel($this->db))
            ->select('achievements.*, child_achievements.id AS child_achievement_id, child_achievements.earned_at, child_achievements.points_awarded')
            ->join('child_achievements', 'child_achievements.achievement_id = achievements.id AND child_achievements.child_id = ' . $childId, 'left')
            ->where('achievements.is_active', 1)
            ->orderBy('child_achievements.earned_at', 'DESC')
            ->orderBy('achievements.condition_value', 'ASC')
            ->findAll();
        foreach ($rows as &$row) {
            $row['is_unlocked'] = $row['child_achievement_id'] !== null;
        }
        unset($row);
        return $rows;
    }

    private function metrics(int $childId, DateTimeInterface $at, array $types): array
    {
        $metrics = [];
        if (in_array('task_count', $types, true)) {
            $metrics['task_count'] = (new TaskCompletionModel($this->db))->where('child_user_id', $childId)
                ->where('status', 'completed')->countAllResults();
        }
        if (in_array('perfect_day_count', $types, true)) {
            $metrics['perfect_day_count'] = (new PerfectDayModel($this->db))->where('child_id', $childId)->countAllResults();
        }
        if (in_array('streak', $types, true)) {
            $metrics['streak'] = (new StreakService(new TaskCompletionService(db: $this->db)))->currentStreak($childId, $at);
        }
        if (in_array('earned_points', $types, true)) {
            $metrics['earned_points'] = $this->earnedPointsExcludingAchievementBonuses($childId);
        }
        return $metrics;
    }

    private function earnedPointsExcludingAchievementBonuses(int $childId): int
    {
        $row = (new PointTransactionModel($this->db))->selectSum('points', 'earned_points')
            ->where('child_user_id', $childId)
            ->whereIn('type', ['task', 'bonus'])
            ->groupStart()->where('reference_type !=', 'achievement')->orWhere('reference_type', null)->groupEnd()
            ->whereNotIn('id', static function (\CodeIgniter\Database\BaseBuilder $builder) use ($childId) {
                return $builder->select('reference_id')->from('point_transactions')
                    ->where('child_user_id', $childId)->where('type', 'reversal')
                    ->where('reference_type', 'point_transaction')->where('reference_id !=', null);
            })->first();
        return (int) ($row['earned_points'] ?? 0);
    }

    private function assertActiveChild(int $childId): void
    {
        $child = (new UserModel($this->db))->find($childId);
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD) {
            throw new \DomainException('Identiti Anak tidak sah untuk pencapaian.');
        }
    }

    private function lockChild(int $childId): void
    {
        if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
            $table = $this->db->protectIdentifiers($this->db->prefixTable('users'));
            $this->db->query("SELECT id FROM {$table} WHERE id = ? FOR UPDATE", [$childId]);
        }
    }

    private function isDuplicateError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());
        return str_contains($message, 'duplicate') || str_contains($message, 'unique constraint');
    }
}
