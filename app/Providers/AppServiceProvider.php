<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\RepositoryUserProvider;
use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\TransactionManager;
use App\Contracts\UserRepository;
use App\Domain\Data\ServicePhotoRecord;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Infrastructure\Eloquent\EloquentServiceEventRepository;
use App\Infrastructure\Eloquent\EloquentServicePhotoRepository;
use App\Infrastructure\Eloquent\EloquentServiceRepository;
use App\Infrastructure\Eloquent\EloquentTransactionManager;
use App\Infrastructure\Eloquent\EloquentUserRepository;
use App\Infrastructure\Json\JsonServiceEventRepository;
use App\Infrastructure\Json\JsonServicePhotoRepository;
use App\Infrastructure\Json\JsonServiceRepository;
use App\Infrastructure\Json\JsonStore;
use App\Infrastructure\Json\JsonTransactionManager;
use App\Infrastructure\Json\JsonUserRepository;
use App\Policies\ServicePhotoPolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use App\Support\Crypto\SecretBox;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bind every storage contract to the implementation selected by
     * `config('persistence.driver')`. Actions, controllers and views only ever
     * see the contracts, which is what makes JSON <-> MySQL a configuration
     * change instead of a rewrite (decision D13).
     */
    public function register(): void
    {
        $this->app->singleton(SecretBox::class);

        $this->app->singleton(JsonStore::class, static function (): JsonStore {
            $path = config('persistence.json.path');
            $pretty = config('persistence.json.pretty');
            $lock = config('persistence.json.lock');

            return new JsonStore(
                is_string($path) ? $path : storage_path('app/data'),
                $pretty === true,
                $lock !== false,
            );
        });

        $this->app->bind(ServiceRepository::class, function (Application $app): ServiceRepository {
            return $this->usesMySql()
                ? new EloquentServiceRepository
                : new JsonServiceRepository($app->make(JsonStore::class), $app->make(SecretBox::class));
        });

        $this->app->bind(ServiceEventRepository::class, function (): ServiceEventRepository {
            return $this->usesMySql()
                ? new EloquentServiceEventRepository
                : new JsonServiceEventRepository($this->app->make(JsonStore::class));
        });

        $this->app->bind(ServicePhotoRepository::class, function (): ServicePhotoRepository {
            return $this->usesMySql()
                ? new EloquentServicePhotoRepository
                : new JsonServicePhotoRepository($this->app->make(JsonStore::class));
        });

        $this->app->bind(UserRepository::class, function (): UserRepository {
            return $this->usesMySql()
                ? new EloquentUserRepository
                : new JsonUserRepository($this->app->make(JsonStore::class));
        });

        $this->app->bind(TransactionManager::class, function (): TransactionManager {
            return $this->usesMySql()
                ? new EloquentTransactionManager
                : new JsonTransactionManager($this->app->make(JsonStore::class));
        });
    }

    public function boot(): void
    {
        $this->registerAuthDriver();
        $this->registerPolicies();
        $this->registerRateLimiters();
    }

    private function usesMySql(): bool
    {
        return config('persistence.driver') === 'mysql';
    }

    /**
     * Session guard on top of the repository user provider, so `Auth::attempt()`,
     * the `auth` middleware and the policies all work without an Eloquent user.
     */
    private function registerAuthDriver(): void
    {
        Auth::provider('serwis', static function (Application $app): RepositoryUserProvider {
            return new RepositoryUserProvider($app->make(UserRepository::class));
        });

        Auth::extend('serwis', static function (Application $app, string $name, array $config): SessionGuard {
            /** @var UserProvider $provider */
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            $guard = new SessionGuard($name, $provider, $app['session.store'], $app['request']);
            // A guard created through extend() is not wired by AuthManager, so the
            // cookie jar and event dispatcher have to be provided here — without
            // them Auth::logout() cannot clear the session cookie.
            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);

            return $guard;
        });
    }

    private function registerPolicies(): void
    {
        Gate::policy(ServiceRecord::class, ServicePolicy::class);
        Gate::policy(ServicePhotoRecord::class, ServicePhotoPolicy::class);
        Gate::policy(UserRecord::class, UserPolicy::class);
    }

    private function registerRateLimiters(): void
    {
        // 5 attempts / 15 minutes per login+IP (defect D-07).
        RateLimiter::for('login', static function (Request $request): Limit {
            $login = (string) $request->input('login', '');

            return Limit::perMinutes(15, 5)->by(mb_strtolower($login).'|'.$request->ip());
        });

        // 60 mutations per minute for a signed-in employee.
        RateLimiter::for('mutations', static function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(60)->by($user instanceof UserRecord ? (string) $user->id : (string) $request->ip());
        });
    }
}
