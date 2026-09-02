<?php

namespace App\Database\Seeds;

use App\Enums\UserRole;
use RuntimeException;

class DemoSeeder extends FamilyInitializationSeeder
{
    public function run(): void
    {
        if (ENVIRONMENT === 'production' || env('CI_ENVIRONMENT') === 'production') {
            throw new RuntimeException('DemoSeeder is disabled in production. Use ProductionSeeder for controlled first setup.');
        }

        $users = [
            ['name' => 'Parent One', 'email' => 'parent1@example.com', 'username' => null, 'password' => 'password', 'role' => UserRole::PARENT],
            ['name' => 'Parent Two', 'email' => 'parent2@example.com', 'username' => null, 'password' => 'password', 'role' => UserRole::PARENT],
            ['name' => 'Child One', 'email' => null, 'username' => 'child-one-internal', 'password' => bin2hex(random_bytes(32)), 'role' => UserRole::CHILD],
            ['name' => 'Child Two', 'email' => null, 'username' => 'child-two-internal', 'password' => bin2hex(random_bytes(32)), 'role' => UserRole::CHILD],
            ['name' => 'Child Three', 'email' => null, 'username' => 'child-three-internal', 'password' => bin2hex(random_bytes(32)), 'role' => UserRole::CHILD],
        ];

        $this->initializeFamily('Demo Family', $users, false);
    }
}
