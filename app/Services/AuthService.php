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
        Services::trustedChildContext()->clear();
        $users = $this->users ?? new UserModel();
        /** @var User|null $user */
        $user = $users->where('email', mb_strtolower(trim($email)))->first();

        $eligibleParent = $user !== null && $user->is_active && $user->roleEnum() === UserRole::PARENT;
        $passwordMatches = password_verify(
            $password,
            $eligibleParent ? (string) $user->password_hash : self::DUMMY_PASSWORD_HASH,
        );

        if (! $eligibleParent || ! $passwordMatches) {
            return false;
        }

        $family = ($this->families ?? new FamilyService())->currentFamilyForUser((int) $user->id);
        if ($family === null) {
            return false;
        }

        $session = $this->session ?? service('session');
        $session->regenerate(true);
        $session->set([
            'user_id'         => (int) $user->id,
            'user_role'       => UserRole::PARENT->value,
            'family_id'       => (int) $family['id'],
            'auth_expires_at' => time() + ($remember ? self::REMEMBER_SESSION_SECONDS : self::STANDARD_SESSION_SECONDS),
        ]);

        $users->skipValidation(true)->update($user->id, ['last_login_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    public function logoutParent(): void
    {
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

        return $user !== null && $user->is_active ? $user : null;
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
        return Services::trustedChildContext()->isResolved()
            && $this->currentUser()?->roleEnum() === UserRole::CHILD
            && $this->currentFamily() !== null;
    }
}
