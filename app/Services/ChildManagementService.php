<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\AuthorizationException;
use App\Models\ChildProfileModel;
use App\Models\FamilyUserModel;
use App\Models\UserDeviceModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Throwable;

class ChildManagementService
{
    private BaseConnection $db;

    public function __construct(
        private readonly ?UserModel $users = null,
        private readonly ?ChildProfileModel $profiles = null,
        private readonly ?FamilyUserModel $memberships = null,
        private readonly ?FamilyService $families = null,
        private readonly ?FamilyAuthorizationService $authorization = null,
        private readonly ?AuditLogService $auditLogs = null,
        ?BaseConnection $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function allForParent(int $parentUserId): array
    {
        $family = $this->parentFamily($parentUserId);

        return ($this->families ?? new FamilyService())->children((int) $family['id']);
    }

    public function getForParent(int $parentUserId, int $childUserId): array
    {
        $family = $this->parentFamily($parentUserId);
        $child = ($this->users ?? new UserModel())->find($childUserId);
        if ($child === null || $child->roleEnum() !== UserRole::CHILD
            || ! ($this->authorization ?? new FamilyAuthorizationService())
                ->userBelongsToFamily($childUserId, (int) $family['id'])) {
            throw new AuthorizationException('Anak tidak berada dalam keluarga Ibu bapa ini.');
        }

        return [
            'user' => $child,
            'profile' => ($this->profiles ?? new ChildProfileModel())->where('user_id', $childUserId)->first(),
            'family' => $family,
        ];
    }

    public function create(int $parentUserId, array $data): int
    {
        $family = $this->parentFamily($parentUserId);
        $name = $this->validName($data['name'] ?? null);
        $dateOfBirth = $this->nullableDate($data['date_of_birth'] ?? null);
        $rankingEligible = $this->flag($data['is_ranking_eligible'] ?? 1);
        $users = $this->users ?? new UserModel();
        $profiles = $this->profiles ?? new ChildProfileModel();
        $memberships = $this->memberships ?? new FamilyUserModel();

        $this->db->transException(true)->transStart();
        try {
            $childId = $users->insert([
                'name' => $name,
                'email' => null,
                'username' => 'child-' . bin2hex(random_bytes(8)),
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'role' => UserRole::CHILD->value,
                'is_active' => 1,
                'last_login_at' => null,
            ], true);
            if ($childId === false) {
                throw new \RuntimeException('Anak tidak dapat dicipta: ' . implode(' ', $users->errors()));
            }

            if ($memberships->insert(['family_id' => (int) $family['id'], 'user_id' => (int) $childId], true) === false) {
                throw new \RuntimeException('Keahlian keluarga anak tidak dapat dicipta.');
            }
            $profileId = $profiles->insert([
                'user_id' => (int) $childId,
                'avatar' => null,
                'date_of_birth' => $dateOfBirth,
                'is_ranking_eligible' => $rankingEligible,
            ], true);
            if ($profileId === false) {
                throw new \RuntimeException('Profil Anak tidak dapat dicipta: ' . implode(' ', $profiles->errors()));
            }

            ($this->auditLogs ?? new AuditLogService())->record(
                'child.created',
                $parentUserId,
                (int) $childId,
                'child_profile',
                (int) $profileId,
                'Ibu bapa mencipta akaun dan profil anak.',
                null,
                [
                    'name' => $name,
                    'date_of_birth' => $dateOfBirth,
                    'is_ranking_eligible' => (bool) $rankingEligible,
                    'is_active' => true,
                ],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return (int) $childId;
    }

    public function update(int $parentUserId, int $childUserId, array $data): void
    {
        $current = $this->getForParent($parentUserId, $childUserId);
        $name = $this->validName($data['name'] ?? null);
        $dateOfBirth = $this->nullableDate($data['date_of_birth'] ?? null);
        $rankingEligible = $this->flag($data['is_ranking_eligible'] ?? 0);
        $isActive = $this->flag($data['is_active'] ?? 0);
        $users = $this->users ?? new UserModel();
        $profiles = $this->profiles ?? new ChildProfileModel();

        $this->db->transException(true)->transStart();
        try {
            if (! $users->update($childUserId, ['name' => $name, 'is_active' => $isActive])) {
                throw new \RuntimeException('Akaun Anak tidak dapat dikemas kini: ' . implode(' ', $users->errors()));
            }

            if ((bool) $current['user']->is_active && ! (bool) $isActive) {
                $now = date('Y-m-d H:i:s');
                $devices = new UserDeviceModel();
                $activeDevices = $devices
                    ->where('user_id', $childUserId)
                    ->where('is_trusted', true)
                    ->where('revoked_at', null)
                    ->findAll();

                foreach ($activeDevices as $device) {
                    $devices->skipValidation(true)->update((int) $device['id'], [
                        'is_trusted' => false,
                        'revoked_at' => $now,
                    ]);
                }

                if ($activeDevices !== []) {
                    ($this->auditLogs ?? new AuditLogService())->record(
                        'device.revoked_on_child_deactivation',
                        $parentUserId,
                        $childUserId,
                        'user_device',
                        null,
                        'Penyahaktifan anak membatalkan akses semua peranti dipercayai secara kekal.',
                        ['active_device_ids' => array_column($activeDevices, 'id')],
                        ['active_devices' => 0, 'revoked_at' => $now],
                    );
                }
            }

            $profile = $current['profile'];
            if ($profile === null) {
                $profileId = $profiles->insert([
                    'user_id' => $childUserId,
                    'avatar' => null,
                    'date_of_birth' => $dateOfBirth,
                    'is_ranking_eligible' => $rankingEligible,
                ], true);
            } else {
                $profileId = (int) $profile['id'];
                if (! $profiles->update($profileId, [
                    'date_of_birth' => $dateOfBirth,
                    'is_ranking_eligible' => $rankingEligible,
                ])) {
                    throw new \RuntimeException('Profil Anak tidak dapat dikemas kini: ' . implode(' ', $profiles->errors()));
                }
            }

            ($this->auditLogs ?? new AuditLogService())->record(
                'child.profile_updated',
                $parentUserId,
                $childUserId,
                'child_profile',
                (int) $profileId,
                'Ibu bapa mengemas kini tetapan penting profil anak.',
                [
                    'name' => $current['user']->name,
                    'date_of_birth' => $profile['date_of_birth'] ?? null,
                    'is_ranking_eligible' => (bool) ($profile['is_ranking_eligible'] ?? false),
                    'is_active' => (bool) $current['user']->is_active,
                ],
                [
                    'name' => $name,
                    'date_of_birth' => $dateOfBirth,
                    'is_ranking_eligible' => (bool) $rankingEligible,
                    'is_active' => (bool) $isActive,
                ],
            );
            $this->db->transComplete();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
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

    private function validName(mixed $name): string
    {
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Nama Anak wajib dan maksimum 120 aksara.');
        }

        return $name;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function flag(mixed $value): int
    {
        return (string) $value === '1' ? 1 : 0;
    }
}
