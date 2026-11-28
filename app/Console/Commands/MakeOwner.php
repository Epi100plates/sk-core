<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use Illuminate\Console\Command;

/**
 * `php artisan users:owner <login>` — gives one existing account the "Szef" role.
 *
 * The release approval flow needs exactly one role that accepts a release
 * request; existing `admin` accounts keep working unchanged. This command is the
 * safe migration path on a live installation: no data rewrite, only the role of
 * the account named by the owner.
 */
final class MakeOwner extends Command
{
    protected $signature = 'users:owner {login : Login istniejącego konta}';

    protected $description = 'Nadaje kontu rolę Szef (zatwierdza wydanie)';

    public function handle(UserRepository $users): int
    {
        $login = trim((string) $this->argument('login'));
        $user = $users->findByLogin($login);

        if (! $user instanceof UserRecord) {
            $this->error('Nie ma konta o loginie „'.$login.'”.');

            return self::FAILURE;
        }

        if ($user->role === Role::Owner) {
            $this->info('Konto „'.$user->login.'” ma już rolę Szef.');

            return self::SUCCESS;
        }

        $users->save($user->with(['role' => Role::Owner->value]));

        $this->info('Konto „'.$user->login.'” ma teraz rolę Szef i zatwierdza wydanie.');

        return self::SUCCESS;
    }
}
