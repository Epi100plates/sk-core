<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use App\Contracts\TransactionManager;

final class JsonTransactionManager implements TransactionManager
{
    public function __construct(private readonly JsonStore $store) {}

    public function run(callable $callback): mixed
    {
        return $this->store->withTransaction(static fn (): mixed => $callback());
    }
}
