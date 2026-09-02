<?php

namespace App\Database\Seeds;

use App\Enums\UserRole;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

/** Shared, transactional persistence for the two explicit initialization seeders. */
abstract class FamilyInitializationSeeder extends Seeder
{
    protected function initializeFamily(string $familyName, array $users, bool $production): void
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Family initialization could not begin a database transaction.');
        }

        try {
            $familyId = $this->resolveFamily($familyName, $production);

            if ($production) {
                $emails = array_column($users, 'email');
                foreach ($this->db->table('users')->where('role', UserRole::PARENT->value)->get()->getResultArray() as $parent) {
                    if (! in_array(mb_strtolower((string) $parent['email']), $emails, true)) {
                        throw new RuntimeException('An existing Parent is not in the configured production pair. Review live accounts manually; no accounts were replaced.');
                    }
                }
            }

            $now = date('Y-m-d H:i:s');
            foreach ($users as $user) {
                $userId = $this->resolveUser($user, $now);
                $this->attachToFamily($userId, $familyId, $now);

                // Only DemoSeeder supplies Child records. Production never creates profiles.
                if ($user['role'] === UserRole::CHILD
                    && $this->db->table('child_profiles')->where('user_id', $userId)->countAllResults() === 0) {
                    $this->insertRow('child_profiles', [
                        'user_id' => $userId,
                        'avatar' => null,
                        'date_of_birth' => null,
                        'is_ranking_eligible' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Family initialization could not commit.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();

            if ($exception instanceof RuntimeException && ! $exception instanceof \CodeIgniter\Database\Exceptions\DatabaseException) {
                throw $exception;
            }

            // Do not surface SQL, hashes, or environment values through the CLI exception.
            throw new RuntimeException('Family initialization failed; all changes were rolled back. Check database availability, schema, and constraints.');
        }
    }

    private function resolveFamily(string $name, bool $production): int
    {
        $query = $this->db->table('families');
        if (! $production) {
            $query->where('name', $name);
        }
        $families = $query->get(2)->getResultArray();

        if (count($families) > 1) {
            throw new RuntimeException('Family initialization is ambiguous: multiple existing families match the setup. Review the data manually.');
        }

        if ($families !== []) {
            if ((string) $families[0]['name'] !== $name) {
                throw new RuntimeException('The existing family does not match RUTINKU_FAMILY_NAME. No family was renamed or replaced.');
            }

            return (int) $families[0]['id'];
        }

        $now = date('Y-m-d H:i:s');

        return $this->insertRow('families', ['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function resolveUser(array $user, string $now): int
    {
        $query = $this->db->table('users');
        if ($user['email'] !== null) {
            $query->where('LOWER(email)', $user['email']);
        } else {
            $query->where('username', $user['username']);
        }
        $existing = $query->get(2)->getResultArray();

        if (count($existing) > 1) {
            throw new RuntimeException('Multiple accounts match a seed identity. Review the accounts manually.');
        }

        if ($existing !== []) {
            if ($existing[0]['role'] !== $user['role']->value || ! (bool) $existing[0]['is_active']) {
                throw new RuntimeException('An existing seed identity has an incompatible role or is inactive. No account was promoted or reactivated.');
            }

            // Reruns are initialization, not password resets or profile updates.
            return (int) $existing[0]['id'];
        }

        return $this->insertRow('users', [
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
    }

    private function attachToFamily(int $userId, int $familyId, string $now): void
    {
        $memberships = $this->db->table('family_users')->where('user_id', $userId)->get()->getResultArray();
        foreach ($memberships as $membership) {
            if ((int) $membership['family_id'] !== $familyId) {
                throw new RuntimeException('An existing seed user belongs to another family. No membership was moved or added.');
            }
        }

        if ($memberships === []) {
            $this->insertRow('family_users', ['family_id' => $familyId, 'user_id' => $userId, 'created_at' => $now]);
        }
    }

    private function insertRow(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data)) {
            throw new RuntimeException('Family initialization could not insert a record into ' . $table . '.');
        }

        // Never read the connection's previous insert ID after a failed insert.
        $id = (int) $this->db->insertID();
        if ($id < 1) {
            throw new RuntimeException('Family initialization received an invalid insert ID for ' . $table . '.');
        }

        return $id;
    }
}
