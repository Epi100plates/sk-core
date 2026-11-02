<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Contracts\UserRepository;
use App\Domain\Data\Page;
use App\Domain\Data\UserRecord;
use App\Models\User;

final class EloquentUserRepository implements UserRepository
{
    public function find(int $id): ?UserRecord
    {
        $model = User::query()->find($id);

        return $model instanceof User ? self::toRecord($model) : null;
    }

    public function findByLogin(string $login): ?UserRecord
    {
        $model = User::query()->where('login', trim($login))->first();

        return $model instanceof User ? self::toRecord($model) : null;
    }

    public function save(UserRecord $user): UserRecord
    {
        if ($user->id === 0) {
            $model = new User;
            $model->fill(self::attributes($user));
            $model->save();

            return self::toRecord($model->refresh());
        }

        $model = User::query()->findOrFail($user->id);
        $model->fill(self::attributes($user));
        $model->save();

        return self::toRecord($model->refresh());
    }

    public function paginate(int $perPage = 20, int $page = 1): Page
    {
        $total = (int) User::query()->count();

        $models = User::query()
            ->orderBy('login')
            ->forPage($page, $perPage)
            ->get();

        $items = [];

        foreach ($models as $model) {
            $items[] = self::toRecord($model);
        }

        return Page::of($items, $total, $perPage, $page);
    }

    public function all(): array
    {
        $items = [];

        foreach (User::query()->orderBy('login')->get() as $model) {
            $items[] = self::toRecord($model);
        }

        return $items;
    }

    public function count(): int
    {
        return (int) User::query()->count();
    }

    private static function toRecord(User $model): UserRecord
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $model->attributesToArray();
        // `password` is in `$hidden` (so it never leaks from a JSON response),
        // but the repository still needs it to verify credentials.
        $raw = $model->getAttributes();
        $attributes['password'] = is_string($raw['password'] ?? null) ? $raw['password'] : '';

        return UserRecord::fromArray($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(UserRecord $user): array
    {
        return [
            'name' => $user->name,
            'login' => $user->login,
            'password' => $user->password,
            'role' => $user->role->value,
            'is_active' => $user->isActive,
            'must_change_password' => $user->mustChangePassword,
            'last_login_at' => $user->lastLoginAt?->utc()->format('Y-m-d H:i:s'),
        ];
    }
}
