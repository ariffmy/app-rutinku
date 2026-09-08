<?php

namespace App\Services;

use App\Entities\User;
use App\Enums\UserRole;
use App\Models\UserModel;
use CodeIgniter\Session\SessionInterface;
use Config\Services;

class AuthService
{
    private const STANDARD_SESSION_SECONDS = 7_200;
    private const REMEMBER_SESSION_SECONDS = 2_592_000;
    private const DUMMY_PASSWORD_HASH = '$2y$12$mYIwfUzpBPkISi9YDg/8G.GZOzUSx5MqcZ1dmT9M6ed5HgYw5HIom';

    public function __construct(
        private readonly ?UserModel $users = null,
        private readonly ?FamilyService $families = null,
        private readonly ?SessionInterface $session = null,
    ) {
    }

    public function loginParent(string $email, string $password, bool $remember = false): bool
    {
        return $this->loginByEmailForRoles($email, $password, $remember, [UserRole::PARENT]) === UserRole::PARENT;
    }

    public function loginByEmail(string $email, string $password, bool $remember = false): ?UserRole
    {
        return $this->loginByEmailForRoles($email, $password, $remember, [UserRole::PARENT, UserRole::CHILD]);
    }

    /** @param list<UserRole> $allowedRoles */
    private function loginByEmailForRoles(string $email, string $password, bool $remember, array $allowedRoles): ?UserRole
    {
        Services::trustedChildContext()->clear();
        $users = $this->users ?? new UserModel();
        /** @var User|null $user */
        $user = $users->where('email', mb_strtolower(trim($email)))->first();

        $role = $user?->roleEnum();
        $eligibleUser = $user !== null && $user->is_active && $role !== null && in_array($role, $allowedRoles, true);
        $passwordMatches = password_verify(
            $password,
            $eligibleUser ? (string) $user->password_hash : self::DUMMY_PASSWORD_HASH,
        );

        if (! $eligibleUser || ! $passwordMatches) {
            return null;
        }

        $family = ($this->families ?? new FamilyService())->currentFamilyForUser((int) $user->id);
        if ($family === null) {
            return false;
        }

        $session = $this->session ?? service('session');
        $session->regenerate(true);
        $session->set([
            'user_id'         => (int) $user->id,
            'user_role'       => $role->value,
            'family_id'       => (int) $family['id'],
            'auth_expires_at' => time() + ($remember ? self::REMEMBER_SESSION_SECONDS : self::STANDARD_SESSION_SECONDS),
        ]);

        $users->skipValidation(true)->update($user->id, ['last_login_at' => date('Y-m-d H:i:s')]);

        return $role;
    }

    public function logoutParent(): void
    {
        $this->logout();
    }

    public function logout(): void
    {
        Services::trustedChildContext()->clear();
        $session = $this->session ?? service('session');
        $session->remove(['user_id', 'user_role', 'family_id', 'auth_expires_at']);
        $session->destroy();
    }

    public function currentUser(): ?User
    {
        $childContext = Services::trustedChildContext();
        if ($childContext->isResolved()) {
            return $childContext->child();
        }

        $session = $this->session ?? service('session');
        $userId = (int) $session->get('user_id');
        $expiresAt = (int) $session->get('auth_expires_at');

        if ($userId < 1 || $expiresAt < time()) {
            return null;
        }

        /** @var User|null $user */
        $user = ($this->users ?? new UserModel())->find($userId);
        $sessionRole = UserRole::tryFrom((string) $session->get('user_role'));

        return $user !== null && $user->is_active && $sessionRole !== null && $user->roleEnum() === $sessionRole ? $user : null;
    }

    public function currentFamily(): ?array
    {
        $childContext = Services::trustedChildContext();
        if ($childContext->isResolved()) {
            return $childContext->family();
        }

        $user = $this->currentUser();
        if ($user === null) {
            return null;
        }

        $family = ($this->families ?? new FamilyService())->currentFamilyForUser((int) $user->id);
        if ($family === null || (int) $family['id'] !== (int) ($this->session ?? service('session'))->get('family_id')) {
            return null;
        }

        return $family;
    }

    public function isParent(): bool
    {
        return $this->currentUser()?->roleEnum() === UserRole::PARENT && $this->currentFamily() !== null;
    }

    public function isChild(): bool
    {
        if (! Services::trustedChildContext()->isResolved()) {
            $this->resolveChildSessionIntoContext();
        }

        return Services::trustedChildContext()->isResolved()
            && $this->currentUser()?->roleEnum() === UserRole::CHILD
            && $this->currentFamily() !== null;
    }

    public function resolveChildSessionIntoContext(): bool
    {
        $context = Services::trustedChildContext();
        if ($context->isResolved()) {
            return $context->child()->roleEnum() === UserRole::CHILD;
        }

        $session = $this->session ?? service('session');
        if ($session->get('user_role') !== UserRole::CHILD->value
            || (int) $session->get('auth_expires_at') < time()) {
            return false;
        }

        /** @var User|null $child */
        $child = ($this->users ?? new UserModel())->find((int) $session->get('user_id'));
        if ($child === null || ! $child->is_active || $child->roleEnum() !== UserRole::CHILD) {
            return false;
        }
        $family = ($this->families ?? new FamilyService())->currentFamilyForUser((int) $child->id);
        if ($family === null || (int) $family['id'] !== (int) $session->get('family_id')) {
            return false;
        }

        $context->set($child, $family, ['authentication' => 'session']);

        return true;
    }
}
