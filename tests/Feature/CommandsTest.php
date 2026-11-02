<?php

declare(strict_types=1);

use App\Actions\AcceptService;
use App\Actions\AdvanceServiceStatus;
use App\Actions\ReleaseService;
use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Commands: store:check, app:install, store:json-to-mysql, photos:verify,
| icons:check, backup:database (plan B.4, feature F-57)
|--------------------------------------------------------------------------
*/

it('reports the active data store', function () {
    $this->useStore('json');

    $this->artisan('store:check')
        ->expectsOutputToContain('Aktywny magazyn: json')
        ->assertExitCode(0);
});

it('installs the application once and does not overwrite the admin', function () {
    $this->useStore('json');

    $this->artisan('app:install')->assertExitCode(0);

    $admin = app(UserRepository::class)->findByLogin('admin');

    expect($admin)->not->toBeNull()
        ->and($admin?->role)->toBe(Role::Admin)
        ->and($admin?->mustChangePassword)->toBeTrue()
        ->and($admin?->password)->not->toBe('admin');

    $hash = $admin?->password;

    $this->artisan('app:install')
        ->expectsOutputToContain('Konta już istnieją')
        ->assertExitCode(0);

    expect(app(UserRepository::class)->findByLogin('admin')?->password)->toBe($hash)
        ->and(app(UserRepository::class)->count())->toBe(1);
});

it('verifies that all manifest icons exist', function () {
    $this->artisan('icons:check')->assertExitCode(0);
});

it('verifies that photo metadata matches the files', function () {
    $this->useStore('json');
    // The private disk is faked so a developer's real photos cannot influence the result.
    Storage::fake('local');

    $this->artisan('photos:verify')->assertExitCode(0);
});

it('creates a backup archive with the JSON data', function () {
    $this->useStore('json');

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user);

    $output = storage_path('framework/testing/backups/'.uniqid());
    // The command creates the directory itself; creating it here would make the
    // mkdir inside the command fail on Windows ("File exists").
    $this->artisan('backup:database', ['--output' => $output])->assertExitCode(0);

    $archives = glob($output.'/backup-*.zip');

    expect($archives)->not->toBeEmpty();

    $zip = new ZipArchive;
    $zip->open((string) $archives[0]);

    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = (string) $zip->getNameIndex($i);
    }

    $zip->close();

    expect($entries)->toContain('data/services.json');
});

it('moves the JSON data into MySQL idempotently', function () {
    if (! $this->mysqlAvailable()) {
        $this->markTestSkipped('MySQL nie jest dostępny.');
    }

    $this->useStore('json');

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_password' => 'tajne-haslo']);

    $jsonPath = (string) config('persistence.json.path');

    config(['persistence.driver' => 'mysql']);
    $this->artisan('migrate:fresh', ['--force' => true]);

    foreach ([
        ServiceRepository::class,
        ServiceEventRepository::class,
        ServicePhotoRepository::class,
        UserRepository::class,
    ] as $contract) {
        app()->forgetInstance($contract);
    }

    $this->artisan('store:json-to-mysql --verify')->assertExitCode(0);

    $mysqlServices = app(ServiceRepository::class);
    $mysqlEvents = app(ServiceEventRepository::class);

    expect($mysqlServices->count())->toBe(1)
        ->and($mysqlEvents->count())->toBeGreaterThan(0)
        ->and($mysqlServices->findByReference($service->reference)?->devicePassword)->toBe('tajne-haslo');

    // Second run must not duplicate anything.
    $this->artisan('store:json-to-mysql --verify')->assertExitCode(0);

    expect($mysqlServices->count())->toBe(1)
        ->and(app(UserRepository::class)->count())->toBe(1);

    // Restore the JSON configuration for any later test in this process.
    config(['persistence.json.path' => $jsonPath, 'persistence.driver' => 'json']);
});

it('refuses to seed demo data in production', function () {
    $this->useStore('json');
    $this->app['env'] = 'production';

    $seeder = new DemoSeeder;

    expect(fn () => $seeder->run(
        app(UserRepository::class),
        app(AcceptService::class),
        app(AdvanceServiceStatus::class),
        app(ReleaseService::class),
    ))->toThrow(RuntimeException::class);

    expect(app(ServiceRepository::class)->count())->toBe(0);
});

it('seeds 30 demo services in a non production environment', function () {
    $this->useStore('json');

    $this->artisan('db:seed', ['--class' => 'DemoSeeder'])->assertExitCode(0);

    $repo = app(ServiceRepository::class);

    expect($repo->count())->toBe(30);

    $counts = $repo->countsByStatus();

    expect(array_sum($counts))->toBe(30)
        ->and($counts[ServiceStatus::Released->value] ?? 0)->toBeGreaterThan(0);
});

it('keeps the admin seeder free of plaintext passwords', function () {
    $this->useStore('json');
    $this->artisan('app:install')->assertExitCode(0);

    $admin = app(UserRepository::class)->findByLogin('admin');

    expect(Hash::info((string) $admin?->password)['algo'])->not->toBeNull();
});
