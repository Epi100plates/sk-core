<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use App\Infrastructure\Json\JsonStore;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

/**
 * Browser tests (decision D11). They run against the real HTTP server
 * (`php artisan serve`) and a separate JSON data directory configured in
 * `.env.dusk.local`, so development data is never touched.
 */
abstract class DuskTestCase extends BaseTestCase
{
    private static bool $dataCleared = false;

    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The real login route allows 5 attempts / 15 minutes per login+IP, which a
        // browser suite exhausts within seconds. Clearing the file cache store resets
        // those counters without weakening the production rule.
        Cache::flush();

        if (! self::$dataCleared) {
            $this->clearBrowserTestData();
            self::$dataCleared = true;
        }
    }

    /** Each run starts from an empty workshop, otherwise old services pile up. */
    private function clearBrowserTestData(): void
    {
        $store = app(JsonStore::class);

        if (! $store->isInitialized()) {
            $store->initialize();

            return;
        }

        $store->withTransaction(static function ($transaction): void {
            $transaction->replace('services', []);
            $transaction->replace('service_events', []);
            $transaction->replace('service_photos', []);
        });
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--window-size=1366,768',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--disable-gpu',
            '--headless=new',
        ]);

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * A known account for the browser tests. Reuses the account when it exists,
     * so repeated runs stay idempotent.
     */
    protected function seedUser(
        string $login = 'dusk-admin',
        Role $role = Role::Admin,
        string $password = 'DuskHaslo1234',
    ): UserRecord {
        $users = app(UserRepository::class);
        $existing = $users->findByLogin($login);

        if ($existing instanceof UserRecord) {
            return $existing;
        }

        return $users->save(UserRecord::fromArray([
            'name' => 'Dusk Administrator',
            'login' => $login,
            'password' => Hash::make($password),
            'role' => $role->value,
            'is_active' => true,
            'must_change_password' => false,
        ]));
    }

    protected function login(Browser $browser, string $login = 'dusk-admin', string $password = 'DuskHaslo1234'): Browser
    {
        // Chrome keeps its cookies between tests, and the login route is behind the
        // `guest` middleware — without this a still-authenticated session would be
        // redirected away from the form.
        $browser->driver->manage()->deleteAllCookies();

        return $browser->visit('/login')
            ->type('login', $login)
            ->type('password', $password)
            ->press('Zaloguj')
            ->waitForText('Kolejka napraw');
    }

    /** A phone number that is unique for this run (the store has no cleanup between runs). */
    protected function uniquePhone(): string
    {
        static $counter = 0;
        $counter++;

        return sprintf('700 %03d %03d', random_int(100, 999), $counter);
    }

    protected function serviceByPhone(string $phone): ?ServiceRecord
    {
        foreach (app(ServiceRepository::class)->all() as $service) {
            if ($service->phone === $phone) {
                return $service;
            }
        }

        return null;
    }

    protected function moveToStatus(string $phone, ServiceStatus $status): void
    {
        $service = $this->serviceByPhone($phone);

        if ($service === null) {
            return;
        }

        app(ServiceRepository::class)->save($service->with(['status' => $status->value]));
    }
}
