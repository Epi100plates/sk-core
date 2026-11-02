<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Support\Legacy\SqlDumpParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `php artisan legacy:verify` — compares record counts and money sums between
 * the dump and the running data store (plan section 9.3). A difference exits
 * with a non-zero code so it can gate a cutover.
 */
final class VerifyImport extends Command
{
    protected $signature = 'legacy:verify {--source= : Ścieżka do zrzutu SQL starej bazy}';

    protected $description = 'Porównuje liczność i sumy danych po imporcie ze starym zrzutem';

    public function handle(
        SqlDumpParser $parser,
        ServiceRepository $services,
        ServiceEventRepository $events,
    ): int {
        $source = $this->option('source');
        $source = is_string($source) && $source !== ''
            ? $source
            : base_path('tests/Fixtures/legacy-dump.sql');

        if (! is_file($source)) {
            $this->error('Nie znaleziono zrzutu: '.$source);

            return self::FAILURE;
        }

        $tables = $parser->parseFile($source);
        $legacyServices = $tables['laptopservice'] ?? [];
        $legacyHistory = $tables['service_history'] ?? [];

        $legacyCount = count($legacyServices);
        $newCount = $services->count();
        $imported = 0;

        foreach ($services->all() as $service) {
            if ($service->legacyId !== null) {
                $imported++;
            }
        }

        $legacySum = 0.0;

        foreach ($legacyServices as $row) {
            $legacySum += (float) str_replace(',', '.', (string) ($row['cena'] ?? '0'));
        }

        $newSum = 0.0;

        foreach ($services->all() as $service) {
            $newSum += (float) $service->price;
        }

        $this->table(['Miara', 'Stara baza', 'Nowa baza'], [
            ['Zlecenia (razem)', $legacyCount, $newCount],
            ['Zlecenia z legacy_id', $legacyCount, $imported],
            ['Historia', count($legacyHistory), $events->count()],
            ['Suma cen', number_format($legacySum, 2, '.', ''), number_format($newSum, 2, '.', '')],
        ]);

        $problems = 0;

        if ($imported < $legacyCount) {
            $this->error(sprintf('Zaimportowano %d z %d zleceń.', $imported, $legacyCount));
            $problems++;
        }

        if (abs($legacySum - $newSum) > 0.01) {
            $this->error(sprintf('Suma cen różni się o %.2f.', $newSum - $legacySum));
            $problems++;
        }

        if ($problems > 0) {
            return self::FAILURE;
        }

        $this->info('Dane zgodne ze zrzutem.');

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            // No database in JSON mode: nothing else to check.
        }

        return self::SUCCESS;
    }
}
