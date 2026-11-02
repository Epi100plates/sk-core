<?php

declare(strict_types=1);

namespace App\Support\Install;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Infrastructure\Json\JsonStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * One-time application installation (F-52, decision D2).
 *
 * In `json` mode it creates the data files; in `mysql` mode it runs the
 * migrations. Either way it creates the first admin account with a random
 * password that is displayed exactly once. Running it twice is safe.
 */
final class Installer
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly JsonStore $store,
    ) {}

    public function install(): InstallResult
    {
        $driver = (string) config('persistence.driver', 'json');

        if ($driver === 'mysql') {
            $schemaCreated = $this->migrate();
        } else {
            $this->store->initialize();
            $schemaCreated = true;
        }

        if ($this->users->count() > 0) {
            return new InstallResult($driver, $schemaCreated, null, null, true);
        }

        $password = Str::password(16, symbols: false);
        $admin = UserRecord::fromArray([
            'name' => 'Administrator',
            'login' => 'admin',
            'password' => Hash::make($password),
            'role' => Role::Admin->value,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->users->save($admin);

        return new InstallResult($driver, $schemaCreated, 'admin', $password, false);
    }

    private function migrate(): bool
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Migracje nie powiodły się: '.$exception->getMessage(), 0, $exception);
        }

        return true;
    }
}
