<?php

declare(strict_types=1);

namespace App\Auth;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication provider that reads accounts from the active data store
 * (JSON files or MySQL) instead of an Eloquent model, so login works before a
 * database exists (decision D13).
 */
final class RepositoryUserProvider implements UserProvider
{
    public function __construct(private readonly UserRepository $users) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        if (! is_numeric($identifier)) {
            return null;
        }

        $user = $this->users->find((int) $identifier);

        return $user instanceof UserRecord && $user->isActive ? $user : null;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null; // remember-me is intentionally not implemented (defect D-07)
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // no remember-me token is stored
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $login = $credentials['login'] ?? null;

        if (! is_string($login) || trim($login) === '') {
            return null;
        }

        $user = $this->users->findByLogin($login);

        return $user instanceof UserRecord && $user->isActive ? $user : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return false;
        }

        if ($user instanceof UserRecord) {
            return Hash::check($password, $user->password);
        }

        if ($user instanceof GenericUser) {
            $hash = $user->getAuthPassword();

            return is_string($hash) && Hash::check($password, $hash);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(
        Authenticatable $user,
        #[\SensitiveParameter] array $credentials,
        bool $force = false,
    ): void {
        // hashes are created by the installer/user panel; nothing to rehash here
    }
}
