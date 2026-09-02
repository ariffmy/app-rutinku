<?php

namespace App\Entities;

final readonly class ProvisionedDevice
{
    public function __construct(
        public int $deviceId,
        public int $childUserId,
        public string $rawToken,
    ) {
    }
}
