<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\AcceptService;
use App\Actions\ServiceDetails;
use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\TransactionManager;
use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Http\Controllers\QueueController;
use App\Infrastructure\Json\JsonStore;
use App\Support\Audit\AuditContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

/**
 * `php artisan bench:queue` — performance smoke test (task T7.4, plan 5.5).
 *
 * Builds an isolated JSON data directory (never the real one), creates the given
 * number of services through the real action, then measures the queue screen
 * (repository query + Blade render) and reports p50/p95 in milliseconds.
 *
 * The measurement skips the HTTP/middleware layer on purpose: on a shared host the
 * page cost is dominated by the data access and the template, and this command has
 * to run without a browser or a web server.
 */
final class BenchQueue extends Command
{
    protected $signature = 'bench:queue {--services=200 : Liczba zleceń w kolejce} {--requests=20 : Liczba pomiarów}';

    protected $description = 'Mierzy czas renderowania kolejki dla wskazanej liczby zleceń (magazyn JSON, dane tymczasowe)';

    public function handle(): int
    {
        $serviceCount = max(1, (int) $this->option('services'));
        $requests = max(1, (int) $this->option('requests'));

        $temporary = storage_path('framework/testing/bench-'.Str::random(8));

        config(['persistence.driver' => 'json', 'persistence.json.path' => $temporary]);

        foreach ([
            JsonStore::class,
            ServiceRepository::class,
            ServiceEventRepository::class,
            ServicePhotoRepository::class,
            UserRepository::class,
            TransactionManager::class,
        ] as $contract) {
            app()->forgetInstance($contract);
        }

        app(JsonStore::class)->initialize();

        $employee = app(UserRepository::class)->save(UserRecord::fromArray([
            'name' => 'Benchmark',
            'login' => 'bench',
            'password' => Hash::make(Str::random(16)),
            'role' => Role::Employee->value,
            'is_active' => true,
            'must_change_password' => false,
        ]));

        $this->line('Tworzenie '.$serviceCount.' zleceń (magazyn JSON)…');

        $accept = app(AcceptService::class);
        $context = AuditContext::console();
        $now = CarbonImmutable::now('UTC');

        for ($i = 0; $i < $serviceCount; $i++) {
            $accept->handle(new ServiceDetails(
                phone: sprintf('+485%08d', 100000 + $i),
                deviceModel: 'Model '.($i % 17),
                serialNumber: 'SN-'.$i,
                issue: 'Usterka numer '.$i,
                devicePassword: null,
                notes: null,
                isExpress: $i % 11 === 0,
                plannedAt: $now->addDays($i % 7),
                price: '150.00',
                cost: '50.00',
                sealNumber: 'PLO-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
            ), $employee, $context);
        }

        $controller = app(QueueController::class);
        $repository = app(ServiceRepository::class);
        $durations = [];
        $renderedRows = 0;

        // The layout expects the values normally shared by the web middleware.
        app('view')->share('errors', new ViewErrorBag);
        Auth::guard('web')->setUser($employee);

        // One warm-up render outside the measurement: the first one also compiles
        // the Blade templates, which would otherwise be charged to the first
        // sample and make a two-sample p95 swing by hundreds of milliseconds.
        $controller(Request::create('/', 'GET')->setUserResolver(static fn (): UserRecord => $employee), $repository)
            ->render();

        for ($i = 0; $i < $requests; $i++) {
            $request = Request::create('/', 'GET');
            $request->setUserResolver(static fn (): UserRecord => $employee);

            $start = microtime(true);
            $view = $controller($request, $repository);
            $html = $view->render();
            $durations[] = (microtime(true) - $start) * 1000;

            $renderedRows = substr_count($html, 'class="app-card service-card');
        }

        sort($durations);

        $p50 = $durations[(int) floor(count($durations) * 0.5)] ?? 0;
        $p95 = $durations[min(count($durations) - 1, (int) floor(count($durations) * 0.95))] ?? 0;

        $this->table(['Metryka', 'Wartość'], [
            ['Zleceń w magazynie', $serviceCount],
            ['Kart na stronie', $renderedRows],
            ['Pomiarów', $requests],
            ['p50', number_format($p50, 1, ',', ' ').' ms'],
            ['p95', number_format($p95, 1, ',', ' ').' ms'],
            ['max', number_format((float) end($durations), 1, ',', ' ').' ms'],
        ]);

        File::deleteDirectory($temporary);

        if ($p95 > 300) {
            $this->warn('p95 powyżej celu 300 ms (plan 5.5) — rozważ MySQL albo mniejszą stronę kolejki.');

            return self::FAILURE;
        }

        $this->info('p95 poniżej celu 300 ms (plan 5.5).');

        return self::SUCCESS;
    }
}
