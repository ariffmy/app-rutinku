<?php

namespace App\Services;

use App\Models\FamilyModel;
use App\Models\FamilyUserModel;
use App\Models\UserModel;

class FamilyService
{
    public function __construct(
        private readonly ?FamilyModel $families = null,
        private readonly ?FamilyUserModel $memberships = null,
        private readonly ?UserModel $users = null,
    ) {
    }

    public function currentFamilyForUser(int $userId): ?array
    {
        $memberships = ($this->memberships ?? new FamilyUserModel())
            ->where('user_id', $userId)
            ->findAll(2);

        if (count($memberships) !== 1) {
            return null;
        }

        return ($this->families ?? new FamilyModel())->find((int) $memberships[0]['family_id']);
    }

    public function children(int $familyId): array
    {
        return ($this->users ?? new UserModel())
            ->select('users.id, users.name, users.is_active, child_profiles.avatar, child_profiles.date_of_birth, child_profiles.is_ranking_eligible')
            ->join('family_users', 'family_users.user_id = users.id')
            ->join('child_profiles', 'child_profiles.user_id = users.id', 'left')
            ->where('family_users.family_id', $familyId)
            ->where('users.role', 'child')
            ->orderBy('users.name', 'ASC')
            ->asArray()
            ->findAll();
    }
}
