<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\AcceptService;
use App\Actions\ServiceDetails;
use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\TransactionManager;
use App\Contracts\UserRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use App\Infrastructure\Json\JsonStore;
use App\Support\Audit\AuditContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** Static flag so the MySQL schema is migrated once per process. */
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $store = (string) env('TEST_DATA_STORE', '');

        if ($store !== '') {
            $this->useStore($store);
        }
    }

    /**
     * Switches the active data store for the current test. The whole suite is
     * run twice (dataset 'json' and 'mysql') so "works on files but not on the
     * database" cannot happen (plan R18).
     */
    public function useStore(string $driver): void
    {
        config(['persistence.driver' => $driver]);

        if ($driver === 'mysql') {
            $this->prepareMySql();
        } else {
            $this->prepareJson();
        }

        $this->forgetStoreBindings();
    }

    private function prepareJson(): void
    {
        $path = storage_path('framework/testing/data-'.Str::random(8));
        config(['persistence.json.path' => $path]);

        app()->forgetInstance(JsonStore::class);
        app(JsonStore::class)->initialize();
    }

    private function prepareMySql(): void
    {
        if (! self::$migrated) {
            $this->artisan('migrate:fresh', ['--force' => true]);
            self::$migrated = true;
        }

        DB::table('service_events')->delete();
        DB::table('service_photos')->delete();
        DB::table('services')->delete();
        DB::table('users')->delete();
    }

    private function forgetStoreBindings(): void
    {
        foreach ([
            ServiceRepository::class,
            ServiceEventRepository::class,
            ServicePhotoRepository::class,
            UserRepository::class,
            TransactionManager::class,
        ] as $contract) {
            app()->forgetInstance($contract);
        }
    }

    protected function createUser(
        string $login = 'pracownik',
        Role $role = Role::Employee,
        string $password = 'TajneHaslo123',
        bool $active = true,
    ): UserRecord {
        return app(UserRepository::class)->save(UserRecord::fromArray([
            'name' => ucfirst($login),
            'login' => $login,
            'password' => Hash::make($password),
            'role' => $role->value,
            'is_active' => $active,
            'must_change_password' => false,
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function acceptService(UserRecord $actor, array $overrides = []): ServiceRecord
    {
        $planned = $overrides['planned_at'] ?? null;
        $plannedAt = match (true) {
            $planned instanceof CarbonImmutable => $planned,
            $planned instanceof \DateTimeInterface => CarbonImmutable::instance($planned),
            default => CarbonImmutable::now('UTC')->addDay(),
        };

        $details = new ServiceDetails(
            phone: (string) ($overrides['phone'] ?? '505 123 456'),
            deviceModel: (string) ($overrides['device_model'] ?? 'Dell Latitude 5420'),
            serialNumber: $overrides['serial_number'] ?? null,
            issue: (string) ($overrides['issue'] ?? 'Nie ładuje się.'),
            devicePassword: $overrides['device_password'] ?? null,
            notes: $overrides['notes'] ?? null,
            isExpress: (bool) ($overrides['is_express'] ?? false),
            plannedAt: $plannedAt,
            price: (string) ($overrides['price'] ?? '150.00'),
            cost: (string) ($overrides['cost'] ?? '50.00'),
            sealNumber: $overrides['seal_number'] ?? null,
        );

        return app(AcceptService::class)->handle($details, $actor, AuditContext::console());
    }

    protected function actingAsUser(UserRecord $user): static
    {
        $this->be($user);

        return $this;
    }

    /**
     * An accepted service moved directly to a given stage (test shortcut; the
     * actions under test always move it through the workflow themselves).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function acceptServiceWithStatus(
        UserRecord $actor,
        ServiceStatus $status,
        array $overrides = [],
    ): ServiceRecord {
        $service = $this->acceptService($actor, $overrides);

        return app(ServiceRepository::class)->save($service->with(['status' => $status->value]));
    }

    /** Is a MySQL/MariaDB server reachable for the `mysql` dataset? */
    public function mysqlAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  list<ServiceStatus>  $statuses
     */
    protected function assertServiceStatus(ServiceRecord $service, ServiceStatus $expected): void
    {
        $fresh = app(ServiceRepository::class)->find($service->id);

        $this->assertNotNull($fresh, 'Zlecenie nie istnieje w magazynie danych.');
        $this->assertSame($expected, $fresh->status);
    }
}
