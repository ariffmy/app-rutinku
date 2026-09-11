<?php

namespace App\Services;

use App\Enums\PointTransactionType;
use App\Enums\UserRole;
use App\Exceptions\AuthorizationException;
use App\Exceptions\PointException;
use App\Models\AuditLogModel;
use App\Models\PointTransactionModel;
use App\Models\RoutineTaskModel;
use App\Models\RewardModel;
use App\Models\RewardRedemptionModel;
use App\Models\TaskCompletionModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class PointService
{
    private BaseConnection $db;

    public function __construct(
        private readonly ?PointTransactionModel $transactions = null,
        private readonly ?TaskCompletionModel $completions = null,
        private readonly ?RoutineTaskModel $routineTasks = null,
        private readonly ?RewardRedemptionModel $rewardRedemptions = null,
        private readonly ?RewardModel $rewards = null,
        private readonly ?UserModel $users = null,
        private readonly ?FamilyAuthorizationService $authorization = null,
        private readonly ?AuditLogService $auditLogs = null,
        ?BaseConnection $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function awardTaskPoints(int $childUserId, int $completionId): array
    {
        $this->assertActiveChild($childUserId);
        $completion = ($this->completions ?? new TaskCompletionModel())->find($completionId);
        if ($completion === null || (int) $completion['child_user_id'] !== $childUserId
            || ($completion['status'] ?? 'completed') !== 'completed') {
            throw new PointException('Penyelesaian tidak sah untuk pemberian mata.');
        }

        $transactions = $this->transactions ?? new PointTransactionModel();
        $existing = $transactions
            ->where('type', PointTransactionType::TASK->value)
            ->where('reference_type', 'task_completion')
            ->where('reference_id', $completionId)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $task = ($this->routineTasks ?? new RoutineTaskModel())->find((int) $completion['routine_task_id']);
        $description = $task === null
            ? 'Penyelesaian tugasan #' . $completionId
            : 'Tugasan selesai: ' . $task['title'];

        try {
            $transactionId = $transactions->insert([
                'child_user_id' => $childUserId,
                'type' => PointTransactionType::TASK->value,
                'points' => (int) $completion['points_awarded'],
                'reference_type' => 'task_completion',
                'reference_id' => $completionId,
                'description' => mb_substr($description, 0, 500),
                'transaction_date' => $completion['completion_date'],
                'created_by_user_id' => $childUserId,
            ], true);
        } catch (Throwable $exception) {
            if ($this->isDuplicateError($exception)) {
                $existing = ($this->transactions ?? new PointTransactionModel())
                    ->where('type', PointTransactionType::TASK->value)
                    ->where('reference_type', 'task_completion')
                    ->where('reference_id', $completionId)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $exception;
        }

        if ($transactionId === false) {
            throw new PointException('Mata tugasan tidak dapat direkodkan.');
        }

        return ($this->transactions ?? new PointTransactionModel())->find((int) $transactionId);
    }

    public function reverseTaskPoints(
        int $childUserId,
        int $completionId,
        DateTimeInterface $at,
        ?int $createdByUserId = null,
    ): array {
        $this->assertActiveChild($childUserId);
        $transactions = $this->transactions ?? new PointTransactionModel();
        $original = $transactions
            ->where('child_user_id', $childUserId)
            ->where('type', PointTransactionType::TASK->value)
            ->where('reference_type', 'task_completion')
            ->where('reference_id', $completionId)
            ->first();
        if ($original === null) {
            throw new PointException('Rekod mata asal tidak ditemui.');
        }

        $existing = ($this->transactions ?? new PointTransactionModel())
            ->where('type', PointTransactionType::REVERSAL->value)
            ->where('reference_type', 'point_transaction')
            ->where('reference_id', (int) $original['id'])
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $local = $this->localTime($at);
        try {
            $transactionId = ($this->transactions ?? new PointTransactionModel())->insert([
                'child_user_id' => $childUserId,
                'type' => PointTransactionType::REVERSAL->value,
                'points' => -((int) $original['points']),
                'reference_type' => 'point_transaction',
                'reference_id' => (int) $original['id'],
                'description' => mb_substr('Batal: ' . ($original['description'] ?: 'penyelesaian tugasan'), 0, 500),
                'transaction_date' => $local->format('Y-m-d'),
                'created_by_user_id' => $createdByUserId ?? $childUserId,
            ], true);
        } catch (Throwable $exception) {
            if ($this->isDuplicateError($exception)) {
                $existing = ($this->transactions ?? new PointTransactionModel())
                    ->where('type', PointTransactionType::REVERSAL->value)
                    ->where('reference_type', 'point_transaction')
                    ->where('reference_id', (int) $original['id'])
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $exception;
        }

        if ($transactionId === false) {
            throw new PointException('Pembatalan mata tidak dapat direkodkan.');
        }

        return ($this->transactions ?? new PointTransactionModel())->find((int) $transactionId);
    }

    public function manualAdjustment(
        int $parentUserId,
        int $childUserId,
        int $points,
        string $reason,
        DateTimeInterface $at,
        ?int $requestId = null,
    ): array {
        if (! ($this->authorization ?? new FamilyAuthorizationService())
            ->parentCanManageChild($parentUserId, $childUserId)) {
            throw new AuthorizationException('Ibu bapa tidak boleh melaraskan mata anak ini.');
        }

        $reason = trim($reason);
        if ($points === 0 || abs($points) > 1000000) {
            throw new PointException('Jumlah pelarasan mesti antara -1,000,000 hingga 1,000,000 dan bukan sifar.');
        }
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new PointException('Sebab pelarasan wajib dan tidak boleh melebihi 500 aksara.');
        }

        $local = $this->localTime($at);
        $requestId ??= random_int(1, PHP_INT_MAX);
        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childUserId);
            $existing = ($this->transactions ?? new PointTransactionModel())
                ->where('type', PointTransactionType::ADJUSTMENT->value)
                ->where('reference_type', 'manual_adjustment')
                ->where('reference_id', $requestId)
                ->first();
            if ($existing !== null) {
                if ((int) $existing['child_user_id'] !== $childUserId
                    || (int) $existing['points'] !== $points
                    || (string) $existing['description'] !== $reason
                    || (int) $existing['created_by_user_id'] !== $parentUserId) {
                    throw new PointException('Kunci permintaan pelarasan telah digunakan untuk data lain.');
                }
                $this->db->transComplete();
                return $existing;
            }
            $oldBalance = $this->getBalance($childUserId);
            $transactionId = ($this->transactions ?? new PointTransactionModel())->insert([
                'child_user_id' => $childUserId,
                'type' => PointTransactionType::ADJUSTMENT->value,
                'points' => $points,
                'reference_type' => 'manual_adjustment',
                'reference_id' => $requestId,
                'description' => $reason,
                'transaction_date' => $local->format('Y-m-d'),
                'created_by_user_id' => $parentUserId,
            ], true);
            if ($transactionId === false) {
                throw new PointException('Pelarasan mata tidak dapat direkodkan.');
            }

            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'points.manual_adjustment',
                $parentUserId,
                $childUserId,
                'point_transaction',
                (int) $transactionId,
                $reason,
                ['balance' => $oldBalance],
                ['balance' => $oldBalance + $points, 'points' => $points, 'reason' => $reason],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            if ($this->isDuplicateError($exception)) {
                $existing = ($this->transactions ?? new PointTransactionModel())
                    ->where('type', PointTransactionType::ADJUSTMENT->value)
                    ->where('reference_type', 'manual_adjustment')
                    ->where('reference_id', $requestId)
                    ->first();
                if ($existing !== null
                    && (int) $existing['child_user_id'] === $childUserId
                    && (int) $existing['points'] === $points
                    && (string) $existing['description'] === $reason
                    && (int) $existing['created_by_user_id'] === $parentUserId) {
                    return $existing;
                }
            }
            throw $exception;
        }

        return ($this->transactions ?? new PointTransactionModel())->find((int) $transactionId);
    }

    public function redeemReward(
        int $childUserId,
        int $redemptionId,
        DateTimeInterface $at,
        int $createdByUserId,
    ): array {
        $this->assertActiveChild($childUserId);
        $redemption = ($this->rewardRedemptions ?? new RewardRedemptionModel())->find($redemptionId);
        if ($redemption === null || (int) $redemption['child_user_id'] !== $childUserId) {
            throw new PointException('Penebusan tidak sah untuk potongan mata.');
        }

        $transactions = $this->transactions ?? new PointTransactionModel();
        $existing = $transactions
            ->where('type', PointTransactionType::REWARD->value)
            ->where('reference_type', 'reward_redemption')
            ->where('reference_id', $redemptionId)
            ->first();
        if ($existing !== null) {
            return $existing;
        }
        if (($redemption['status'] ?? null) !== \App\Enums\RewardRedemptionStatus::PENDING->value
            || (int) $redemption['points_used'] <= 0) {
            throw new PointException('Penebusan belum layak untuk potongan mata.');
        }

        $reward = ($this->rewards ?? new RewardModel())->find((int) $redemption['reward_id']);
        $description = $reward === null ? 'Penebusan ganjaran #' . $redemptionId : 'Ganjaran: ' . $reward['title'];
        try {
            $transactionId = $transactions->insert([
                'child_user_id' => $childUserId,
                'type' => PointTransactionType::REWARD->value,
                'points' => -((int) $redemption['points_used']),
                'reference_type' => 'reward_redemption',
                'reference_id' => $redemptionId,
                'description' => mb_substr($description, 0, 500),
                'transaction_date' => $this->localTime($at)->format('Y-m-d'),
                'created_by_user_id' => $createdByUserId,
            ], true);
        } catch (Throwable $exception) {
            if ($this->isDuplicateError($exception)) {
                $existing = ($this->transactions ?? new PointTransactionModel())
                    ->where('type', PointTransactionType::REWARD->value)
                    ->where('reference_type', 'reward_redemption')
                    ->where('reference_id', $redemptionId)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $exception;
        }

        if ($transactionId === false) {
            throw new PointException('Potongan mata ganjaran tidak dapat direkodkan.');
        }

        return ($this->transactions ?? new PointTransactionModel())->find((int) $transactionId);
    }

    public function awardPerfectDayPoints(int $childUserId, int $perfectDayId): array
    {
        $this->assertActiveChild($childUserId);
        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childUserId);
            $record = (new \App\Models\PerfectDayModel($this->db))->find($perfectDayId);
            if ($record === null || (int) $record['child_id'] !== $childUserId) {
                throw new PointException('Rekod Perfect Day tidak sah.');
            }
            $transactions = $this->transactions ?? new PointTransactionModel($this->db);
            $existing = $transactions->where('type', PointTransactionType::BONUS->value)
                ->where('reference_type', 'perfect_day')->where('reference_id', $perfectDayId)->first();
            if ($existing === null) {
                $id = $transactions->insert([
                    'child_user_id' => $childUserId,
                    'type' => PointTransactionType::BONUS->value,
                    'points' => (int) $record['bonus_points'],
                    'reference_type' => 'perfect_day',
                    'reference_id' => $perfectDayId,
                    'description' => 'Bonus Perfect Day!',
                    'transaction_date' => $record['perfect_date'],
                    'created_by_user_id' => null,
                ], true);
                if ($id === false) {
                    throw new PointException('Bonus Perfect Day tidak dapat direkodkan.');
                }
                $existing = $transactions->find($id);
            }
            $this->db->transComplete();
            return $existing;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function awardAchievementPoints(int $childUserId, int $childAchievementId): array
    {
        $this->assertActiveChild($childUserId);
        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childUserId);
            $record = (new \App\Models\ChildAchievementModel($this->db))->find($childAchievementId);
            if ($record === null || (int) $record['child_id'] !== $childUserId || (int) $record['points_awarded'] <= 0) {
                throw new PointException('Rekod bonus pencapaian tidak sah.');
            }
            $transactions = $this->transactions ?? new PointTransactionModel($this->db);
            $existing = $transactions->where('type', PointTransactionType::BONUS->value)
                ->where('reference_type', 'achievement')->where('reference_id', $childAchievementId)->first();
            if ($existing === null) {
                $id = $transactions->insert([
                    'child_user_id' => $childUserId,
                    'type' => PointTransactionType::BONUS->value,
                    'points' => (int) $record['points_awarded'],
                    'reference_type' => 'achievement',
                    'reference_id' => $childAchievementId,
                    'description' => 'Bonus pencapaian',
                    'transaction_date' => substr((string) $record['earned_at'], 0, 10),
                    'created_by_user_id' => null,
                ], true);
                if ($id === false) {
                    throw new PointException('Bonus pencapaian tidak dapat direkodkan.');
                }
                $existing = $transactions->find($id);
            }
            $this->db->transComplete();
            return $existing;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function getBalance(int $childUserId): int
    {
        $this->assertActiveChild($childUserId);
        $row = ($this->transactions ?? new PointTransactionModel())
            ->selectSum('points', 'balance')
            ->where('child_user_id', $childUserId)
            ->first();

        return (int) ($row['balance'] ?? 0);
    }

    public function getHistory(int $childUserId, int $limit = 50): array
    {
        $this->assertActiveChild($childUserId);

        return ($this->transactions ?? new PointTransactionModel())
            ->where('child_user_id', $childUserId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll(max(1, min($limit, 100)));
    }

    public function getParentAccount(int $parentUserId, int $childUserId, int $limit = 50): array
    {
        if (! ($this->authorization ?? new FamilyAuthorizationService())
            ->parentCanManageChild($parentUserId, $childUserId)) {
            throw new AuthorizationException('Ibu bapa tidak boleh melihat mata anak ini.');
        }

        return [
            'balance' => $this->getBalance($childUserId),
            'history' => $this->getHistory($childUserId, $limit),
        ];
    }

    public function getEarnedPointsBetween(
        int $childUserId,
        DateTimeInterface $start,
        DateTimeInterface $end,
    ): int {
        $this->assertActiveChild($childUserId);
        $startDate = $this->localTime($start)->format('Y-m-d');
        $endDate = $this->localTime($end)->format('Y-m-d');
        if ($startDate > $endDate) {
            throw new PointException('Julat tarikh mata tidak sah.');
        }

        $row = ($this->transactions ?? new PointTransactionModel())
            ->selectSum('points', 'earned_points')
            ->where('child_user_id', $childUserId)
            ->whereIn('type', [
                PointTransactionType::TASK->value,
                PointTransactionType::BONUS->value,
            ])
            // Undo keeps the original award in the append-only ledger. Do not
            // count cancelled awards again when a task is completed a second time.
            ->whereNotIn('id', static function (\CodeIgniter\Database\BaseBuilder $builder) use ($childUserId) {
                return $builder->select('reference_id')->from('point_transactions')
                    ->where('child_user_id', $childUserId)
                    ->where('type', PointTransactionType::REVERSAL->value)
                    ->where('reference_type', 'point_transaction')
                    ->where('reference_id !=', null);
            })
            ->where('transaction_date >=', $startDate)
            ->where('transaction_date <=', $endDate)
            ->first();

        return (int) ($row['earned_points'] ?? 0);
    }

    private function assertActiveChild(int $childUserId): void
    {
        $child = ($this->users ?? new UserModel())->find($childUserId);
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD) {
            throw new PointException('Identiti Anak tidak sah.');
        }
    }

    private function lockChild(int $childUserId): void
    {
        if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
            $table = $this->db->protectIdentifiers($this->db->prefixTable('users'));
            $this->db->query("SELECT id FROM {$table} WHERE id = ? FOR UPDATE", [$childUserId]);
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
