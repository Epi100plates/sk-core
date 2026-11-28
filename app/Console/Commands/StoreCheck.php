<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\IntakeTicketRepository;
use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Infrastructure\Json\JsonStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `php artisan store:check` — which data store is active, is it healthy and how
 * much data does it hold (feature F-57).
 */
final class StoreCheck extends Command
{
    protected $signature = 'store:check';

    protected $description = 'Pokazuje aktywny magazyn danych, jego stan i liczbę rekordów';

    public function handle(
        ServiceRepository $services,
        ServiceEventRepository $events,
        ServicePhotoRepository $photos,
        UserRepository $users,
        IntakeTicketRepository $intakes,
        JsonStore $store,
    ): int {
        $driver = (string) config('persistence.driver', 'json');

        $this->info('Aktywny magazyn: '.$driver);

        if ($driver === 'mysql') {
            return $this->checkMySql($services, $events, $photos, $users, $intakes);
        }

        return $this->checkJson($services, $events, $photos, $users, $intakes, $store);
    }

    private function checkJson(
        ServiceRepository $services,
        ServiceEventRepository $events,
        ServicePhotoRepository $photos,
        UserRepository $users,
        IntakeTicketRepository $intakes,
        JsonStore $store,
    ): int {
        $path = $store->path();
        $this->line('Katalog danych: '.$path);

        // Idempotent: an installation created before the submissions inbox gets
        // the new empty collection file here, without touching existing rows.
        $store->initialize();

        if (! $store->isInitialized()) {
            $this->error('Magazyn nie jest zainicjalizowany. Uruchom: php artisan app:install');

            return self::FAILURE;
        }

        if (! is_writable($path)) {
            $this->error('Katalog danych nie jest zapisywalny — aplikacja nie może zapisywać zleceń.');

            return self::FAILURE;
        }

        $this->line('Wersja schematu: '.$store->readMeta()['schema_version']);
        $this->line('Blokada zapisu (flock): '.(config('persistence.json.lock') ? 'tak' : 'nie'));

        $this->reportCounts($services, $events, $photos, $users, $intakes);

        $count = $services->count();
        $limit = (int) config('persistence.json.scale_warning', 5000);

        if ($count > $limit) {
            $this->warn(sprintf(
                'Zleceń: %d — powyżej progu %d. Rozważ przełączenie na MySQL (DATA_STORE=mysql + store:json-to-mysql).',
                $count,
                $limit,
            ));
        }

        return self::SUCCESS;
    }

    private function checkMySql(
        ServiceRepository $services,
        ServiceEventRepository $events,
        ServicePhotoRepository $photos,
        UserRepository $users,
        IntakeTicketRepository $intakes,
    ): int {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->error('Brak połączenia z bazą: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Połączenie: '.config('database.default'));

        try {
            $this->reportCounts($services, $events, $photos, $users, $intakes);
        } catch (Throwable $exception) {
            $this->error('Tabele nie są gotowe — uruchom `php artisan migrate --force`.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reportCounts(
        ServiceRepository $services,
        ServiceEventRepository $events,
        ServicePhotoRepository $photos,
        UserRepository $users,
        IntakeTicketRepository $intakes,
    ): void {
        $this->line('Zlecenia: '.$services->count());
        $this->line('Zdarzenia audytu: '.$events->count());
        $this->line('Zdjęcia: '.$photos->count());
        $this->line('Konta: '.$users->count());
        $this->line('Zgłoszenia z formularza: '.$intakes->count());

        $byStatus = $services->countsByStatus();

        if ($byStatus !== []) {
            $this->line('Statusy: '.implode(', ', array_map(
                static fn (string $status, int $count): string => $status.'='.$count,
                array_keys($byStatus),
                array_values($byStatus),
            )));
        }
    }
}
