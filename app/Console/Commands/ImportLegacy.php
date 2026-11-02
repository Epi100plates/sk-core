<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Legacy\LegacyImporter;
use App\Support\Legacy\LegacyReport;
use App\Support\Legacy\SqlDumpParser;
use Illuminate\Console\Command;

/**
 * `php artisan legacy:import [--dry-run] [--source=...] [--uploads=...]`
 * (task T2.7, plan section 9). Optional: the application starts on empty data,
 * this is used the day the owner delivers a dump of the old database.
 */
final class ImportLegacy extends Command
{
    protected $signature = 'legacy:import
        {--source= : Ścieżka do zrzutu SQL starej bazy}
        {--uploads= : Katalog ze zdjęciami starej aplikacji}
        {--dry-run : Tylko raport, bez zapisu}
        {--report= : Ścieżka pliku CSV z raportem}';

    protected $description = 'Importuje dane ze starej aplikacji (idempotentnie, z raportem)';

    public function handle(SqlDumpParser $parser, LegacyImporter $importer): int
    {
        $source = $this->option('source');
        $source = is_string($source) && $source !== ''
            ? $source
            : base_path('tests/Fixtures/legacy-dump.sql');

        if (! is_file($source)) {
            $this->error('Nie znaleziono zrzutu: '.$source);
            $this->line('Podaj plik przez --source=... (zrzut z phpMyAdmin w formacie SQL).');

            return self::FAILURE;
        }

        $tables = $parser->parseFile($source);

        if ($tables === []) {
            $this->error('Zrzut nie zawiera żadnych instrukcji INSERT — sprawdź plik.');

            return self::FAILURE;
        }

        $uploads = $this->option('uploads');
        $uploadsPath = is_string($uploads) && $uploads !== '' ? $uploads : null;
        $dryRun = (bool) $this->option('dry-run');

        $report = $importer->run($tables, $dryRun, $uploadsPath);

        $this->renderReport($report);

        $csvPath = $this->option('report');

        if (is_string($csvPath) && $csvPath !== '') {
            file_put_contents($csvPath, $report->toCsv());
            $this->line('Raport CSV: '.$csvPath);
        }

        if ($dryRun) {
            $this->warn('Tryb --dry-run: nic nie zostało zapisane.');
        }

        return self::SUCCESS;
    }

    private function renderReport(LegacyReport $report): void
    {
        $rows = [];

        foreach ($report->counters() as $key => $value) {
            $rows[] = [$key, $value];
        }

        if ($rows !== []) {
            $this->table(['Pozycja', 'Liczba'], $rows);
        }

        foreach ($report->warnings() as $warning) {
            $this->warn($warning);
        }

        if ($report->warnings() === []) {
            $this->info('Brak ostrzeżeń — dane zamapowane bez wątpliwości.');
        }
    }
}
