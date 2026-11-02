<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Data\UserRecord;
use App\Infrastructure\Eloquent\EloquentServiceEventRepository;
use App\Infrastructure\Eloquent\EloquentServicePhotoRepository;
use App\Infrastructure\Eloquent\EloquentServiceRepository;
use App\Infrastructure\Eloquent\EloquentUserRepository;
use App\Infrastructure\Json\JsonServiceEventRepository;
use App\Infrastructure\Json\JsonServicePhotoRepository;
use App\Infrastructure\Json\JsonServiceRepository;
use App\Infrastructure\Json\JsonStore;
use App\Infrastructure\Json\JsonUserRepository;
use App\Support\Crypto\SecretBox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `php artisan store:json-to-mysql` — moves the JSON files into MySQL
 * (feature F-57). Idempotent: records are matched by id / reference / login, so
 * running it twice does not duplicate anything.
 */
final class JsonToMysql extends Command
{
    protected $signature = 'store:json-to-mysql {--verify : Porównuje liczby rekordów po migracji}';

    protected $description = 'Przenosi dane z plików JSON do bazy MySQL (idempotentnie)';

    public function handle(JsonStore $store, SecretBox $secrets): int
    {
        if ((string) config('persistence.driver') !== 'mysql') {
            $this->error('Ten tryb wymaga DATA_STORE=mysql w .env (oraz DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD).');

            return self::FAILURE;
        }

        if (! $store->isInitialized()) {
            $this->error('Brak katalogu z danymi JSON: '.$store->path());

            return self::FAILURE;
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->error('Brak połączenia z bazą: '.$exception->getMessage());

            return self::FAILURE;
        }

        $jsonServices = new JsonServiceRepository($store, $secrets);
        $jsonEvents = new JsonServiceEventRepository($store);
        $jsonPhotos = new JsonServicePhotoRepository($store);
        $jsonUsers = new JsonUserRepository($store);

        $mysqlServices = new EloquentServiceRepository;
        $mysqlEvents = new EloquentServiceEventRepository;
        $mysqlPhotos = new EloquentServicePhotoRepository;
        $mysqlUsers = new EloquentUserRepository;

        $counts = ['users' => 0, 'services' => 0, 'events' => 0, 'photos' => 0];

        foreach ($jsonUsers->all() as $user) {
            $existing = $mysqlUsers->findByLogin($user->login);
            $mysqlUsers->save($existing instanceof UserRecord
                ? $existing->with(['password' => $user->password, 'role' => $user->role->value, 'is_active' => $user->isActive])
                : $user->with(['id' => 0]));
            $counts['users']++;
        }

        $idMap = [];
        $created = [];

        foreach ($jsonServices->all() as $service) {
            $existing = $mysqlServices->findByReference($service->reference);

            if ($existing === null) {
                $saved = $mysqlServices->save($service->with(['id' => 0]));
                $created[$service->id] = true;
            } else {
                $data = $service->toArray();
                unset($data['id'], $data['version'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
                $saved = $mysqlServices->save($existing->with($data));
            }

            $idMap[$service->id] = $saved->id;
            $counts['services']++;
        }

        // Events and photos are only copied together with a service created in this
        // run, which keeps the command idempotent (a second run adds nothing).
        foreach ($jsonEvents->all() as $event) {
            $mappedId = $idMap[$event->serviceId] ?? null;

            if ($mappedId === null || ! isset($created[$event->serviceId])) {
                continue;
            }

            $mysqlEvents->append($event->withServiceId($mappedId));
            $counts['events']++;
        }

        foreach ($jsonPhotos->all() as $photo) {
            $mappedId = $idMap[$photo->serviceId] ?? null;

            if ($mappedId === null || ! isset($created[$photo->serviceId])) {
                continue;
            }

            $mysqlPhotos->save($photo->withId(0)->with(['service_id' => $mappedId]));
            $counts['photos']++;
        }

        $this->table(['Kolekcja', 'Przeniesione'], [
            ['Konta', $counts['users']],
            ['Zlecenia', $counts['services']],
            ['Zdarzenia', $counts['events']],
            ['Zdjęcia', $counts['photos']],
        ]);

        if ($this->option('verify')) {
            $problems = 0;

            $pairs = [
                'konta' => [$jsonUsers->count(), $mysqlUsers->count()],
                'zlecenia' => [$jsonServices->count(), $mysqlServices->count()],
                'zdarzenia' => [$jsonEvents->count(), $mysqlEvents->count()],
                'zdjęcia' => [$jsonPhotos->count(), $mysqlPhotos->count()],
            ];

            foreach ($pairs as $label => [$json, $mysql]) {
                if ($json !== $mysql) {
                    $this->error(sprintf('%s: JSON=%d, MySQL=%d', $label, $json, $mysql));
                    $problems++;
                }
            }

            if ($problems > 0) {
                return self::FAILURE;
            }

            $this->info('Weryfikacja: liczby rekordów są zgodne.');
        }

        return self::SUCCESS;
    }
}
