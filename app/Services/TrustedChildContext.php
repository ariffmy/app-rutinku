<?php

namespace App\Services;

use App\Entities\User;

class TrustedChildContext
{
    private ?User $child = null;
    private ?array $family = null;
    private ?array $device = null;

    public function set(User $child, array $family, array $device): void
    {
        $this->child = $child;
        $this->family = $family;
        $this->device = $device;
    }

    public function clear(): void
    {
        $this->child = null;
        $this->family = null;
        $this->device = null;
    }

    public function isResolved(): bool
    {
        return $this->child !== null && $this->family !== null && $this->device !== null;
    }

    public function child(): ?User
    {
        return $this->child;
    }

    public function family(): ?array
    {
        return $this->family;
    }

    public function device(): ?array
    {
        return $this->device;
    }
}
