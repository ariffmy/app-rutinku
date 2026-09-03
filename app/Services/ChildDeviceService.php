<?php

namespace App\Services;

use App\Entities\ProvisionedDevice;
use App\Entities\User;
use App\Enums\UserRole;
use App\Exceptions\AuthorizationException;
use App\Models\UserDeviceModel;
use App\Models\UserModel;
use CodeIgniter\Cookie\Cookie;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Cookie as CookieConfig;

class ChildDeviceService
{
    public const COOKIE_NAME = 'child_device';
    public const COOKIE_LIFETIME_SECONDS = 15_552_000;

    private BaseConnection $db;

    public function __construct(
        private readonly ?UserDeviceModel $devices = null,
        private readonly ?UserModel $users = null,
        private readonly ?FamilyService $families = null,
        private readonly ?FamilyAuthorizationService $authorization = null,
        private readonly ?AuditLogService $auditLogs = null,
        ?BaseConnection $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    public function provision(
        int $parentUserId,
        int $childUserId,
        ?string $deviceName = null,
        ?string $deviceType = null,
    ): ProvisionedDevice {
        $this->assertParentCanManageChild($parentUserId, $childUserId);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::COOKIE_LIFETIME_SECONDS);
        $devices = $this->devices ?? new UserDeviceModel();
        $audit = $this->auditLogs ?? new AuditLogService();

        $this->db->transException(true)->transStart();

        try {
            $this->lockChild($childUserId);

            $activeDevices = $devices
                ->where('user_id', $childUserId)
                ->where('is_trusted', true)
                ->where('revoked_at', null)
                ->findAll();

            foreach ($activeDevices as $activeDevice) {
                $devices->skipValidation(true)->update((int) $activeDevice['id'], [
                    'is_trusted' => false,
                    'revoked_at' => $now,
                ]);
            }

            $deviceId = $devices->insert([
                'user_id' => $childUserId,
                'token_hash' => $tokenHash,
                'device_name' => $this->normalizeNullable($deviceName),
                'device_type' => $this->normalizeNullable($deviceType),
                'is_trusted' => 1,
                'expires_at' => $expiresAt,
                'last_used_at' => null,
                'revoked_at' => null,
                'created_by_user_id' => $parentUserId,
            ], true);

            if ($deviceId === false) {
                throw new \RuntimeException('Peranti dipercayai tidak dapat disediakan: ' . implode(' ', $devices->errors()));
            }

            $audit->record(
                'device.provisioned',
                $parentUserId,
                $childUserId,
                'user_device',
                (int) $deviceId,
                'Ibu bapa menyediakan pelayar ini sebagai peranti dipercayai anak.',
                $activeDevices === [] ? null : ['replaced_device_ids' => array_column($activeDevices, 'id')],
                [
                    'device_name' => $this->normalizeNullable($deviceName),
                    'device_type' => $this->normalizeNullable($deviceType),
                    'is_trusted' => true,
                    'expires_at' => $expiresAt,
                ],
            );

            $this->db->transComplete();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return new ProvisionedDevice((int) $deviceId, $childUserId, $rawToken);
    }

    public function revoke(int $parentUserId, int $childUserId, int $deviceId): bool
    {
        $this->assertParentCanManageChild($parentUserId, $childUserId);
        $devices = $this->devices ?? new UserDeviceModel();
        $audit = $this->auditLogs ?? new AuditLogService();
        $now = date('Y-m-d H:i:s');

        $this->db->transException(true)->transStart();

        try {
            $this->lockChild($childUserId);
            $device = $devices->find($deviceId);

            if ($device === null || (int) $device['user_id'] !== $childUserId) {
                throw new AuthorizationException('Peranti ini bukan milik anak ini.');
            }

            if (! (bool) $device['is_trusted'] || $device['revoked_at'] !== null) {
                $this->db->transComplete();

                return false;
            }

            $devices->skipValidation(true)->update($deviceId, [
                'is_trusted' => false,
                'revoked_at' => $now,
            ]);
            $audit->record(
                'device.revoked',
                $parentUserId,
                $childUserId,
                'user_device',
                $deviceId,
                'Ibu bapa membatalkan akses peranti dipercayai anak.',
                ['is_trusted' => true, 'revoked_at' => null],
                ['is_trusted' => false, 'revoked_at' => $now],
            );

            $this->db->transComplete();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return true;
    }

    public function reset(int $parentUserId, int $childUserId): int
    {
        $this->assertParentCanManageChild($parentUserId, $childUserId);
        $devices = $this->devices ?? new UserDeviceModel();
        $audit = $this->auditLogs ?? new AuditLogService();
        $now = date('Y-m-d H:i:s');

        $this->db->transException(true)->transStart();

        try {
            $this->lockChild($childUserId);
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

            $audit->record(
                'device.reset',
                $parentUserId,
                $childUserId,
                'user_device',
                null,
                'Ibu bapa menetapkan semula semua peranti dipercayai anak.',
                ['active_device_ids' => array_column($activeDevices, 'id')],
                ['active_devices' => 0],
            );

            $this->db->transComplete();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return count($activeDevices);
    }

    public function resolveIntoContext(string $rawToken, TrustedChildContext $context): bool
    {
        $context->clear();

        if (preg_match('/\A[a-f0-9]{64}\z/D', $rawToken) !== 1) {
            return false;
        }

        $devices = $this->devices ?? new UserDeviceModel();
        $device = $devices->where('token_hash', hash('sha256', $rawToken))->first();

        if ($device === null || ! (bool) $device['is_trusted'] || $device['revoked_at'] !== null) {
            return false;
        }

        $expiresAt = $device['expires_at'] === null ? false : strtotime((string) $device['expires_at']);
        if ($expiresAt === false || $expiresAt <= time()) {
            $devices->skipValidation(true)->update((int) $device['id'], [
                'is_trusted' => false,
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);

            return false;
        }

        /** @var User|null $child */
        $child = ($this->users ?? new UserModel())->find((int) $device['user_id']);
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD) {
            return false;
        }

        $family = ($this->families ?? new FamilyService())->currentFamilyForUser((int) $child->id);
        if ($family === null) {
            return false;
        }

        $lastUsedAt = $device['last_used_at'] === null ? null : strtotime((string) $device['last_used_at']);
        if ($lastUsedAt === null || $lastUsedAt < time() - 300) {
            $now = date('Y-m-d H:i:s');
            $devices->skipValidation(true)->update((int) $device['id'], ['last_used_at' => $now]);
            $device['last_used_at'] = $now;
        }

        unset($device['token_hash']);
        $context->set($child, $family, $device);

        return true;
    }

    public function devicesForChild(int $parentUserId, int $childUserId): array
    {
        $this->assertParentCanManageChild($parentUserId, $childUserId);

        return ($this->devices ?? new UserDeviceModel())
            ->select('id, user_id, device_name, device_type, is_trusted, expires_at, last_used_at, revoked_at, created_by_user_id, created_at, updated_at')
            ->where('user_id', $childUserId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function attachCookie(ResponseInterface $response, string $rawToken): ResponseInterface
    {
        $cookie = config(CookieConfig::class);

        return $response->setCookie([
            'name' => self::COOKIE_NAME,
            'value' => $rawToken,
            'expire' => self::COOKIE_LIFETIME_SECONDS,
            'path' => '/',
            'secure' => $cookie->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function clearCookie(ResponseInterface $response): ResponseInterface
    {
        $cookie = config(CookieConfig::class);

        return $response->setCookie(new Cookie(self::COOKIE_NAME, '', [
            'prefix' => $cookie->prefix,
            'expires' => time() - 3_600,
            'path' => '/',
            'secure' => $cookie->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]));
    }

    public static function requestCookieName(): string
    {
        return config(CookieConfig::class)->prefix . self::COOKIE_NAME;
    }

    private function assertParentCanManageChild(int $parentUserId, int $childUserId): void
    {
        if (! ($this->authorization ?? new FamilyAuthorizationService())->parentCanManageChild($parentUserId, $childUserId)) {
            throw new AuthorizationException('Ibu bapa tidak boleh mengurus anak ini.');
        }
    }

    private function lockChild(int $childUserId): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->db->query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$childUserId]);
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
