<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Eloquent model used only by the `mysql` data store. Application code talks to
 * `Contracts\UserRepository` and `Domain\Data\UserRecord` instead.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'login',
        'password',
        'role',
        'is_active',
        'must_change_password',
        'last_login_at',
    ];

    /** @var list<string> */
    protected $hidden = ['password'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // No `hashed` cast on purpose: passwords are always hashed explicitly
            // by the repository callers, and re-hashing an existing hash would
            // silently break every login (the MySQL store would then mismatch).
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
