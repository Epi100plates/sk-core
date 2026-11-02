<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use App\Contracts\UserRepository;
use App\Domain\Data\Page;
use App\Domain\Data\UserRecord;
use App\Domain\Exceptions\DataStoreException;
use Carbon\CarbonImmutable;

final class JsonUserRepository implements UserRepository
{
    use ReadsJsonCollections;

    private const COLLECTION = 'users';

    public function __construct(private readonly JsonStore $store) {}

    public function find(int $id): ?UserRecord
    {
        foreach ($this->rows(self::COLLECTION) as $row) {
            if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $id) {
                return UserRecord::fromArray($row);
            }
        }

        return null;
    }

    public function findByLogin(string $login): ?UserRecord
    {
        $needle = mb_strtolower(trim($login));

        foreach ($this->rows(self::COLLECTION) as $row) {
            if (mb_strtolower(trim((string) ($row['login'] ?? ''))) === $needle) {
                return UserRecord::fromArray($row);
            }
        }

        return null;
    }

    public function save(UserRecord $user): UserRecord
    {
        return $this->store->withTransaction(function (JsonTransaction $transaction) use ($user): UserRecord {
            $rows = $transaction->rows(self::COLLECTION);
            $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

            if ($user->id === 0) {
                $stored = $user->with([
                    'id' => $transaction->nextId(self::COLLECTION),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $transaction->replace(self::COLLECTION, [...$rows, $stored->toArray()]);

                return $stored;
            }

            $stored = $user->with(['updated_at' => $now]);
            $result = [];
            $replaced = false;

            foreach ($rows as $row) {
                if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $stored->id) {
                    $result[] = $stored->toArray();
                    $replaced = true;

                    continue;
                }

                $result[] = $row;
            }

            if (! $replaced) {
                throw new DataStoreException('Konto #'.$stored->id.' nie istnieje już w magazynie danych.');
            }

            $transaction->replace(self::COLLECTION, $result);

            return $stored;
        });
    }

    public function paginate(int $perPage = 20, int $page = 1): Page
    {
        $rows = $this->rows(self::COLLECTION);

        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($a['login'] ?? ''), (string) ($b['login'] ?? '')));

        $total = count($rows);
        $slice = array_slice($rows, max(0, ($page - 1) * $perPage), $perPage);

        return Page::of(
            array_map(static fn (array $row): UserRecord => UserRecord::fromArray($row), $slice),
            $total,
            $perPage,
            $page,
        );
    }

    public function all(): array
    {
        return array_map(
            static fn (array $row): UserRecord => UserRecord::fromArray($row),
            $this->rows(self::COLLECTION),
        );
    }

    public function count(): int
    {
        return count($this->rows(self::COLLECTION));
    }
}
