<?php

declare(strict_types=1);

namespace App\Domain\Data;

use App\Domain\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\UrlRoutable;

/**
 * Storage independent user account. Passwords are always bcrypt hashes —
 * there is no code path that stores a plaintext password (defect D-01).
 */
final class UserRecord implements Authenticatable, UrlRoutable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $login,
        public readonly string $password,
        public readonly Role $role,
        public readonly bool $isActive,
        public readonly ?CarbonImmutable $lastLoginAt,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
        public readonly bool $mustChangePassword = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $now = CarbonImmutable::now('UTC');

        return new self(
            id: is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0,
            name: trim((string) ($data['name'] ?? '')),
            login: trim((string) ($data['login'] ?? '')),
            password: (string) ($data['password'] ?? ''),
            role: Role::tryFrom((string) ($data['role'] ?? '')) ?? Role::Employee,
            isActive: self::toBool($data['is_active'] ?? true),
            lastLoginAt: self::toDate($data['last_login_at'] ?? null),
            createdAt: self::toDate($data['created_at'] ?? null) ?? $now,
            updatedAt: self::toDate($data['updated_at'] ?? null) ?? $now,
            mustChangePassword: self::toBool($data['must_change_password'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'login' => $this->login,
            'password' => $this->password,
            'role' => $this->role->value,
            'is_active' => $this->isActive,
            'last_login_at' => $this->lastLoginAt?->utc()->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->utc()->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->utc()->format('Y-m-d H:i:s'),
            'must_change_password' => $this->mustChangePassword,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
    }

    public function can(string $ability): bool
    {
        return $this->isActive && $this->role->can($ability);
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Remember-me is intentionally not implemented (D-07).
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function getAuthName(): string
    {
        return $this->name;
    }

    public function getRouteKey(): int
    {
        return $this->id;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return null;
    }

    public function resolveChildRouteBinding($childType, $value, $field): ?self
    {
        return null;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'on', 'yes'], true);
    }

    private static function toDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC')->utc();
    }
}
