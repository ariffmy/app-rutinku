<?php

namespace App\Database\Seeds;

use App\Enums\UserRole;
use RuntimeException;

class ProductionSeeder extends FamilyInitializationSeeder
{
    public function run(): void
    {
        // Validate every credential before any database insert.
        $familyName = $this->requiredValue('RUTINKU_FAMILY_NAME', 120);
        $parents = [];

        foreach ([1, 2] as $number) {
            $prefix = 'RUTINKU_PARENT' . $number . '_';
            $name = $this->requiredValue($prefix . 'NAME', 120);
            $email = mb_strtolower($this->requiredValue($prefix . 'EMAIL', 190));
            $password = $this->requiredValue($prefix . 'PASSWORD', null, false);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException($prefix . 'EMAIL must be a valid email address.');
            }
            if (strlen($password) > 72 || str_contains($password, "\0")) {
                throw new RuntimeException($prefix . 'PASSWORD must be at most 72 bytes and contain no NUL byte.');
            }

            $parents[] = [
                'name' => $name,
                'email' => $email,
                'username' => null,
                'password' => $password,
                'role' => UserRole::PARENT,
            ];
        }

        if ($parents[0]['email'] === $parents[1]['email']) {
            throw new RuntimeException('RUTINKU_PARENT1_EMAIL and RUTINKU_PARENT2_EMAIL must be different.');
        }

        $this->initializeFamily($familyName, $parents, true);
    }

    private function requiredValue(string $key, ?int $maxLength = null, bool $trim = true): string
    {
        $value = env($key);
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException($key . ' is required and must be a non-empty string.');
        }

        $value = $trim ? trim($value) : $value;
        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            throw new RuntimeException($key . ' exceeds the maximum length of ' . $maxLength . ' characters.');
        }

        return $value;
    }
}
