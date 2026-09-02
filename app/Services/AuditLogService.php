<?php

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\HTTP\IncomingRequest;

class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'token',
        'token_hash',
        'cookie',
        'secret',
    ];

    public function __construct(private readonly ?AuditLogModel $logs = null)
    {
    }

    public function record(
        string $action,
        ?int $actorUserId = null,
        ?int $targetUserId = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): int {
        $request = service('request');
        $ipAddress = null;
        $userAgent = null;

        if ($request instanceof IncomingRequest) {
            $ipAddress = $request->getIPAddress();
            $userAgent = mb_substr((string) $request->getUserAgent(), 0, 500);
        }

        $model = $this->logs ?? new AuditLogModel();
        $id = $model->insert([
            'user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'description' => $description,
            'old_values' => $this->encodeSanitized($oldValues),
            'new_values' => $this->encodeSanitized($newValues),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ], true);

        if ($id === false) {
            throw new \RuntimeException('Audit log could not be written.');
        }

        return (int) $id;
    }

    private function encodeSanitized(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        $sanitized = $this->sanitize($values);
        $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === '[]' ? null : $encoded;
    }

    private function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);
            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
