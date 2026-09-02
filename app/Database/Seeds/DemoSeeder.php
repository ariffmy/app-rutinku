<?php

namespace App\Database\Seeds;

use App\Enums\UserRole;
use CodeIgniter\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->transStart();

        $this->db->table('families')->insert([
            'name' => 'Demo Family',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $familyId = (int) $this->db->insertID();

        $users = [
            ['name' => 'Parent One', 'email' => 'parent1@example.com', 'username' => null, 'password' => 'password', 'role' => UserRole::PARENT],
            ['name' => 'Parent Two', 'email' => 'parent2@example.com', 'username' => null, 'password' => 'password', 'role' => UserRole::PARENT],
            ['name' => 'Child One', 'email' => null, 'username' => 'child-one-internal', 'password' => bin2hex(random_bytes(32)), 'role' => UserRole::CHILD],
            ['name' => 'Child Two', 'email' => null, 'username' => 'child-two-internal', 'password' => bin2hex(random_bytes(32)), 'role' => UserRole::CHILD],
            ['name' => 'Child Three', 'email' => null, 'username' => 'child-three-internal', 'password' => bin2hex(random_bytes(32)), 'role' => UserRole::CHILD],
        ];

        foreach ($users as $user) {
            $this->db->table('users')->insert([
                'name' => $user['name'],
                'email' => $user['email'],
                'username' => $user['username'],
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
                'role' => $user['role']->value,
                'is_active' => true,
                'last_login_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userId = (int) $this->db->insertID();

            $this->db->table('family_users')->insert([
                'family_id' => $familyId,
                'user_id' => $userId,
                'created_at' => $now,
            ]);

            if ($user['role'] === UserRole::CHILD) {
                $this->db->table('child_profiles')->insert([
                    'user_id' => $userId,
                    'avatar' => null,
                    'date_of_birth' => null,
                    'is_ranking_eligible' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException('Demo data could not be seeded.');
        }
    }
}
