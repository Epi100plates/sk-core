<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Install\Installer;
use Illuminate\Console\Command;

/**
 * Creates the schema (JSON files or MySQL tables) and the first admin account
 * (feature F-52). Safe to run twice.
 */
final class InstallApp extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Inicjalizuje magazyn danych i tworzy konto administratora';

    public function handle(Installer $installer): int
    {
        $result = $installer->install();

        $this->info('Magazyn danych: '.$result->driver);

        if ($result->adminAlreadyExisted) {
            $this->line('Konta już istnieją — nie zmieniam haseł.');

            return self::SUCCESS;
        }

        if ($result->createdAdmin()) {
            $this->warn('Konto administratora utworzone. Zapisz hasło — nie będzie pokazane ponownie:');
            $this->line('  login:  '.$result->adminLogin);
            $this->line('  hasło:  '.$result->adminPassword);
            $this->line('Przy pierwszym logowaniu aplikacja wymusi zmianę hasła.');
        }

        return self::SUCCESS;
    }
}
