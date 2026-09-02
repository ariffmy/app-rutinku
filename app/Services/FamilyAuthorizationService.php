<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\FamilyUserModel;
use App\Models\RoutineModel;
use App\Models\RoutineTaskModel;
use App\Models\UserModel;

class FamilyAuthorizationService
{
    public function __construct(
        private readonly ?FamilyUserModel $memberships = null,
        private readonly ?UserModel $users = null,
        private readonly ?RoutineModel $routines = null,
        private readonly ?RoutineTaskModel $routineTasks = null,
    ) {
    }

    public function userBelongsToFamily(int $userId, int $familyId): bool
    {
        return ($this->memberships ?? new FamilyUserModel())
            ->where('user_id', $userId)
            ->where('family_id', $familyId)
            ->countAllResults() === 1;
    }

    public function parentCanManageChild(int $parentUserId, int $childUserId): bool
    {
        $users = $this->users ?? new UserModel();
        $parent = $users->find($parentUserId);
        $child = $users->find($childUserId);

        if ($parent === null || $child === null || ! $parent->is_active || ! $child->is_active
            || $parent->roleEnum() !== UserRole::PARENT || $child->roleEnum() !== UserRole::CHILD) {
            return false;
        }

        $memberships = $this->memberships ?? new FamilyUserModel();
        $parentFamilyIds = array_column($memberships->where('user_id', $parentUserId)->findAll(), 'family_id');
        $childFamilyIds = array_column($memberships->where('user_id', $childUserId)->findAll(), 'family_id');

        return array_intersect($parentFamilyIds, $childFamilyIds) !== [];
    }

    public function parentCanManageRoutine(int $parentUserId, int $routineId): bool
    {
        $routine = ($this->routines ?? new RoutineModel())->find($routineId);

        return $routine !== null
            && $this->parentCanManageChild($parentUserId, (int) $routine['child_user_id']);
    }

    public function parentCanManageRoutineTask(int $parentUserId, int $routineTaskId): bool
    {
        $task = ($this->routineTasks ?? new RoutineTaskModel())->find($routineTaskId);

        return $task !== null
            && $this->parentCanManageRoutine($parentUserId, (int) $task['routine_id']);
    }
}
