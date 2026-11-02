<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Data\Page;
use App\Domain\Data\UserRecord;

interface UserRepository
{
    public function find(int $id): ?UserRecord;

    public function findByLogin(string $login): ?UserRecord;

    /** Creates the account when `id` is 0, updates it otherwise. */
    public function save(UserRecord $user): UserRecord;

    /**
     * @return Page<UserRecord>
     */
    public function paginate(int $perPage = 20, int $page = 1): Page;

    /**
     * @return list<UserRecord>
     */
    public function all(): array;

    public function count(): int;
}
