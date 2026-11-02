<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Contracts\TransactionManager;
use Illuminate\Support\Facades\DB;

final class EloquentTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return DB::transaction(static fn (): mixed => $callback(), 3);
    }
}
