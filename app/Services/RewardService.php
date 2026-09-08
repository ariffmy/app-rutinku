<?php

namespace App\Services;

use App\Enums\RewardRedemptionStatus;
use App\Enums\UserRole;
use App\Exceptions\AuthorizationException;
use App\Exceptions\RewardException;
use App\Models\AuditLogModel;
use App\Models\RewardModel;
use App\Models\RewardRedemptionModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

class RewardService
{
    public const CATEGORIES = [
        'Makanan & Minuman', 'Masa Skrin', 'Aktiviti', 'Hadiah', 'Keistimewaan',
        'Digital', 'Wang', 'Istimewa', 'Lain-lain',
    ];
    public const REDEMPTION_LIMITS = ['unlimited', 'daily', 'weekly', 'monthly', 'once'];

    private BaseConnection $db;

    public function __construct(
        private readonly ?RewardModel $rewards = null,
        private readonly ?RewardRedemptionModel $redemptions = null,
        private readonly ?UserModel $users = null,
        private readonly ?FamilyService $families = null,
        private readonly ?FamilyAuthorizationService $authorization = null,
        private readonly ?PointService $points = null,
        private readonly ?AuditLogService $auditLogs = null,
        ?BaseConnection $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function listForParent(int $parentUserId): array
    {
        $family = $this->parentFamily($parentUserId);
        $rewards = ($this->rewards ?? new RewardModel())
            ->where('family_id', (int) $family['id'])
            ->orderBy('is_active', 'DESC')
            ->orderBy('points_required', 'ASC')
            ->orderBy('title', 'ASC')
            ->findAll();
        $redemptions = ($this->redemptions ?? new RewardRedemptionModel())
            ->select('reward_redemptions.*, rewards.title AS reward_title, users.name AS child_name')
            ->join('rewards', 'rewards.id = reward_redemptions.reward_id')
            ->join('users', 'users.id = reward_redemptions.child_user_id')
            ->where('rewards.family_id', (int) $family['id'])
            ->orderBy("CASE WHEN status = 'pending' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('reward_redemptions.requested_at', 'DESC')
            ->findAll(100);

        return ['family' => $family, 'rewards' => $rewards, 'redemptions' => $redemptions];
    }

    public function getForParent(int $parentUserId, int $rewardId): array
    {
        $family = $this->parentFamily($parentUserId);
        $reward = ($this->rewards ?? new RewardModel())->find($rewardId);
        if ($reward === null || (int) $reward['family_id'] !== (int) $family['id']) {
            throw new AuthorizationException('Ibu bapa tidak boleh mengurus ganjaran ini.');
        }

        return $reward;
    }

    public function create(int $parentUserId, array $data): int
    {
        $family = $this->parentFamily($parentUserId);
        $model = $this->rewards ?? new RewardModel();
        $id = $model->insert($this->rewardPayload($data, (int) $family['id']), true);
        if ($id === false) {
            throw new InvalidArgumentException(implode(' ', $model->errors()));
        }

        return (int) $id;
    }

    public function update(int $parentUserId, int $rewardId, array $data): void
    {
        $reward = $this->getForParent($parentUserId, $rewardId);
        $model = $this->rewards ?? new RewardModel();
        if (! $model->update($rewardId, $this->rewardPayload(array_replace($reward, $data), (int) $reward['family_id']))) {
            throw new InvalidArgumentException(implode(' ', $model->errors()));
        }
    }

    public function archive(int $parentUserId, int $rewardId): void
    {
        $this->getForParent($parentUserId, $rewardId);
        if (! ($this->rewards ?? new RewardModel())->update($rewardId, ['is_active' => 0])) {
            throw new RewardException('Ganjaran tidak dapat dinyahaktifkan.');
        }
    }

    public function childCatalogue(int $childUserId): array
    {
        $family = $this->childFamily($childUserId);
        $rewards = ($this->rewards ?? new RewardModel())
            ->where('family_id', (int) $family['id'])
            ->where('is_active', true)
            ->orderBy('points_required', 'ASC')
            ->orderBy('title', 'ASC')
            ->findAll();
        $redemptions = ($this->redemptions ?? new RewardRedemptionModel())
            ->select('reward_redemptions.*, rewards.title AS reward_title')
            ->join('rewards', 'rewards.id = reward_redemptions.reward_id')
            ->where('reward_redemptions.child_user_id', $childUserId)
            ->orderBy('reward_redemptions.requested_at', 'DESC')
            ->findAll(50);
        $pendingRewardIds = [];
        foreach ($redemptions as $redemption) {
            if ($redemption['status'] === RewardRedemptionStatus::PENDING->value) {
                $pendingRewardIds[(int) $redemption['reward_id']] = true;
            }
        }

        $balance = ($this->points ?? new PointService(db: $this->db))->getBalance($childUserId);
        foreach ($rewards as &$reward) {
            $reward['has_pending_request'] = isset($pendingRewardIds[(int) $reward['id']]);
            $reward['can_afford'] = $balance >= (int) $reward['points_required'];
            $reward['limit_message'] = $this->redemptionLimitError($childUserId, $reward, new DateTimeImmutable('now', new DateTimeZone(app_timezone())));
        }
        unset($reward);

        return [
            'family' => $family,
            'balance' => $balance,
            'rewards' => $rewards,
            'redemptions' => $redemptions,
        ];
    }

    public function requestRedemption(int $childUserId, int $rewardId, DateTimeInterface $at): array
    {
        $family = $this->childFamily($childUserId);
        $local = $this->localTime($at);

        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childUserId);
            $reward = ($this->rewards ?? new RewardModel())->find($rewardId);
            if ($reward === null || ! (bool) $reward['is_active']
                || (int) $reward['family_id'] !== (int) $family['id']) {
                throw new RewardException('Ganjaran ini tidak tersedia.');
            }

            $pending = ($this->redemptions ?? new RewardRedemptionModel())
                ->where('reward_id', $rewardId)
                ->where('child_user_id', $childUserId)
                ->where('status', RewardRedemptionStatus::PENDING->value)
                ->first();
            if ($pending !== null) {
                throw new RewardException('Ganjaran ini sudah mempunyai permohonan menunggu kelulusan.');
            }

            if (($this->points ?? new PointService(db: $this->db))->getBalance($childUserId) < (int) $reward['points_required']) {
                throw new RewardException('Mata belum mencukupi untuk ganjaran ini.');
            }
            if ($message = $this->redemptionLimitError($childUserId, $reward, $local)) {
                throw new RewardException($message);
            }

            $redemptionId = ($this->redemptions ?? new RewardRedemptionModel())->insert([
                'reward_id' => $rewardId,
                'child_user_id' => $childUserId,
                'points_used' => (int) $reward['points_required'],
                'status' => RewardRedemptionStatus::PENDING->value,
                'requested_at' => $local->format('Y-m-d H:i:s'),
                'approved_at' => null,
                'rejected_at' => null,
                'approved_by_user_id' => null,
                'rejected_by_user_id' => null,
            ], true);
            if ($redemptionId === false) {
                throw new RewardException('Permohonan ganjaran tidak dapat direkodkan.');
            }
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ($this->redemptions ?? new RewardRedemptionModel())->find((int) $redemptionId);
    }

    public function approve(int $parentUserId, int $redemptionId, DateTimeInterface $at): array
    {
        $redemption = $this->getRedemptionForParent($parentUserId, $redemptionId);
        $local = $this->localTime($at);
        $childUserId = (int) $redemption['child_user_id'];

        $this->db->transException(true)->transStart();
        try {
            $this->lockChild($childUserId);
            $this->lockRedemption($redemptionId);
            $redemption = ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
            if ($redemption === null || $redemption['status'] !== RewardRedemptionStatus::PENDING->value) {
                throw new RewardException('Penebusan ini bukan lagi menunggu kelulusan.');
            }

            $points = $this->points ?? new PointService(db: $this->db);
            if ($points->getBalance($childUserId) < (int) $redemption['points_used']) {
                throw new RewardException('Baki Anak tidak mencukupi untuk kelulusan.');
            }

            $pointTransaction = $points->redeemReward($childUserId, $redemptionId, $local, $parentUserId);
            if (! ($this->redemptions ?? new RewardRedemptionModel())->update($redemptionId, [
                'status' => RewardRedemptionStatus::APPROVED->value,
                'approved_at' => $local->format('Y-m-d H:i:s'),
                'approved_by_user_id' => $parentUserId,
            ])) {
                throw new RewardException('Penebusan tidak dapat diluluskan.');
            }

            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'reward.approved',
                $parentUserId,
                $childUserId,
                'reward_redemption',
                $redemptionId,
                'Ibu bapa meluluskan penebusan ganjaran.',
                ['status' => RewardRedemptionStatus::PENDING->value],
                [
                    'status' => RewardRedemptionStatus::APPROVED->value,
                    'points_used' => (int) $redemption['points_used'],
                    'point_transaction_id' => (int) $pointTransaction['id'],
                ],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
    }

    public function reject(int $parentUserId, int $redemptionId, DateTimeInterface $at): array
    {
        $redemption = $this->getRedemptionForParent($parentUserId, $redemptionId);
        $local = $this->localTime($at);
        $childUserId = (int) $redemption['child_user_id'];

        $this->db->transException(true)->transStart();
        try {
            $this->lockRedemption($redemptionId);
            $redemption = ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
            if ($redemption === null || $redemption['status'] !== RewardRedemptionStatus::PENDING->value) {
                throw new RewardException('Penebusan ini bukan lagi menunggu kelulusan.');
            }
            if (! ($this->redemptions ?? new RewardRedemptionModel())->update($redemptionId, [
                'status' => RewardRedemptionStatus::REJECTED->value,
                'rejected_at' => $local->format('Y-m-d H:i:s'),
                'rejected_by_user_id' => $parentUserId,
            ])) {
                throw new RewardException('Penebusan tidak dapat ditolak.');
            }

            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'reward.rejected',
                $parentUserId,
                $childUserId,
                'reward_redemption',
                $redemptionId,
                'Ibu bapa menolak penebusan ganjaran.',
                ['status' => RewardRedemptionStatus::PENDING->value],
                ['status' => RewardRedemptionStatus::REJECTED->value, 'points_used' => 0],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
    }

    public function cancel(int $childUserId, int $redemptionId): array
    {
        $this->childFamily($childUserId);
        $this->db->transException(true)->transStart();
        try {
            $this->lockRedemption($redemptionId);
            $redemption = ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
            if ($redemption === null || (int) $redemption['child_user_id'] !== $childUserId
                || $redemption['status'] !== RewardRedemptionStatus::PENDING->value) {
                throw new RewardException('Hanya permohonan sendiri yang masih Menunggu boleh dibatalkan.');
            }
            if (! ($this->redemptions ?? new RewardRedemptionModel())->update($redemptionId, [
                'status' => RewardRedemptionStatus::CANCELLED->value,
            ])) {
                throw new RewardException('Permohonan tidak dapat dibatalkan.');
            }
            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'reward.cancelled', $childUserId, $childUserId, 'reward_redemption', $redemptionId,
                'Anak membatalkan permohonan ganjaran.',
                ['status' => RewardRedemptionStatus::PENDING->value],
                ['status' => RewardRedemptionStatus::CANCELLED->value],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
    }

    public function complete(int $parentUserId, int $redemptionId): array
    {
        $redemption = $this->getRedemptionForParent($parentUserId, $redemptionId);
        $this->db->transException(true)->transStart();
        try {
            $this->lockRedemption($redemptionId);
            $redemption = ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
            if ($redemption === null || $redemption['status'] !== RewardRedemptionStatus::APPROVED->value) {
                throw new RewardException('Hanya ganjaran Diluluskan boleh ditandakan Selesai.');
            }
            if (! ($this->redemptions ?? new RewardRedemptionModel())->update($redemptionId, [
                'status' => RewardRedemptionStatus::COMPLETED->value,
            ])) {
                throw new RewardException('Ganjaran tidak dapat ditandakan Selesai.');
            }
            ($this->auditLogs ?? new AuditLogService(new AuditLogModel()))->record(
                'reward.completed', $parentUserId, (int) $redemption['child_user_id'], 'reward_redemption', $redemptionId,
                'Ibu bapa menandakan ganjaran sebagai Selesai.',
                ['status' => RewardRedemptionStatus::APPROVED->value],
                ['status' => RewardRedemptionStatus::COMPLETED->value],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
    }

    private function getRedemptionForParent(int $parentUserId, int $redemptionId): array
    {
        $redemption = ($this->redemptions ?? new RewardRedemptionModel())->find($redemptionId);
        if ($redemption === null || ! ($this->authorization ?? new FamilyAuthorizationService())
            ->parentCanManageChild($parentUserId, (int) $redemption['child_user_id'])) {
            throw new AuthorizationException('Ibu bapa tidak boleh mengurus penebusan ini.');
        }
        $this->getForParent($parentUserId, (int) $redemption['reward_id']);

        return $redemption;
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

    private function childFamily(int $childUserId): array
    {
        $child = ($this->users ?? new UserModel())->find($childUserId);
        $family = ($this->families ?? new FamilyService())->currentFamilyForUser($childUserId);
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD || $family === null) {
            throw new RewardException('Maklumat keluarga anak tidak sah.');
        }

        return $family;
    }

    private function rewardPayload(array $data, int $familyId): array
    {
        $category = trim((string) ($data['category'] ?? 'Lain-lain')) ?: 'Lain-lain';
        $limit = (string) ($data['redemption_limit'] ?? 'unlimited');
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Kategori ganjaran tidak sah.');
        }
        if (! in_array($limit, self::REDEMPTION_LIMITS, true)) {
            throw new InvalidArgumentException('Had penebusan tidak sah.');
        }

        return [
            'family_id' => $familyId,
            'title' => trim((string) ($data['title'] ?? '')),
            'category' => $category,
            'description' => $this->nullable($data['description'] ?? null),
            'points_required' => (int) ($data['points_required'] ?? 0),
            'image' => $this->nullable($data['image'] ?? null),
            'redemption_limit' => $limit,
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
        ];
    }

    private function redemptionLimitError(int $childUserId, array $reward, DateTimeInterface $at): ?string
    {
        $limit = (string) ($reward['redemption_limit'] ?? 'unlimited');
        if ($limit === 'unlimited') {
            return null;
        }

        $query = ($this->redemptions ?? new RewardRedemptionModel())
            ->where('reward_id', (int) $reward['id'])
            ->where('child_user_id', $childUserId)
            ->whereIn('status', [RewardRedemptionStatus::APPROVED->value, RewardRedemptionStatus::COMPLETED->value]);
        $local = $this->localTime($at);

        if ($limit !== 'once') {
            [$start, $end] = match ($limit) {
                'daily' => [$local->setTime(0, 0), $local->setTime(0, 0)->modify('+1 day')],
                'weekly' => [$local->setTime(0, 0)->modify('monday this week'), $local->setTime(0, 0)->modify('monday next week')],
                'monthly' => [$local->setTime(0, 0)->modify('first day of this month'), $local->setTime(0, 0)->modify('first day of next month')],
                default => throw new InvalidArgumentException('Had penebusan tidak sah.'),
            };
            $query->where('requested_at >=', $start->format('Y-m-d H:i:s'))
                ->where('requested_at <', $end->format('Y-m-d H:i:s'));
        }

        return $query->countAllResults() > 0
            ? 'Had penebusan “' . ui_reward_limit($limit) . '” telah dicapai.'
            : null;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function lockChild(int $childUserId): void
    {
        if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
            $this->db->query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$childUserId]);
        }
    }

    private function lockRedemption(int $redemptionId): void
    {
        if (in_array($this->db->DBDriver, ['MySQLi', 'Postgre'], true)) {
            $this->db->query('SELECT id FROM reward_redemptions WHERE id = ? FOR UPDATE', [$redemptionId]);
        }
    }

    private function localTime(DateTimeInterface $at): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone(app_timezone()));
    }
}
