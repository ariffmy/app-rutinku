<?php

namespace App\Entities;

use App\Enums\UserRole;
use CodeIgniter\Entity\Entity;

class User extends Entity
{
    protected $dates = ['last_login_at', 'created_at', 'updated_at'];
    protected $casts = [
        'id'        => 'integer',
        'is_active' => 'boolean',
    ];

    public function roleEnum(): ?UserRole
    {
        return UserRole::tryFrom((string) $this->attributes['role']);
    }

    public function isParent(): bool
    {
        return $this->roleEnum() === UserRole::PARENT;
    }

    public function isChild(): bool
    {
        return $this->roleEnum() === UserRole::CHILD;
    }
}
