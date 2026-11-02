<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * `php artisan backup:database` (task T7.5, decision D8 — data is kept forever,
 * so backups matter more than anonymisation).
 *
 * JSON mode: one ZIP with the JSON data files and the private photo store.
 * MySQL mode: a SQL file generated in PHP (no mysqldump needed on a shared host)
 * plus the same photo archive. A manifest is always inside, so even an empty
 * installation produces a valid archive.
 */
final class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--output= : Katalog docelowy (domyślnie storage/app/backups)}';

    protected $description = 'Tworzy kopię zapasową danych (JSON) albo zrzut bazy (MySQL) wraz ze zdjęciami';

    public function handle(): int
    {
        $output = $this->option('output');
        $directory = is_string($output) && $output !== '' ? $output : storage_path('app/backups');

        File::ensureDirectoryExists($directory, 0700);

        $stamp = now()->utc()->format('Ymd-His');
        $driver = (string) config('persistence.driver', 'json');
        $zipPath = $directory.DIRECTORY_SEPARATOR.'backup-'.$stamp.'.zip';

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Nie można utworzyć archiwum: '.$zipPath);

            return self::FAILURE;
        }

        $files = $this->addDirectory($zip, storage_path('app/private/photos'), 'photos');
        $this->line('Zdjęcia w archiwum: '.$files);

        if ($driver === 'mysql') {
            $dump = $this->dumpMySql();

            if ($dump === null) {
                $zip->close();
                $this->error('Zrzut bazy nie powiódł się.');

                return self::FAILURE;
            }

            $zip->addFromString('database.sql', $dump);
            $this->line('Dodano zrzut bazy danych (database.sql).');
        } else {
            $dataPath = (string) config('persistence.json.path', storage_path('app/data'));
            $dataFiles = $this->addDirectory($zip, $dataPath, 'data');
            $this->line('Pliki JSON w archiwum: '.$dataFiles);
        }

        $zip->addFromString('manifest.json', (string) json_encode([
            'created_at' => now()->utc()->format('Y-m-d H:i:s'),
            'driver' => $driver,
            'app' => (string) config('app.name'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (! $zip->close()) {
            $this->error('Nie udało się zapisać archiwum: '.$zipPath);

            return self::FAILURE;
        }

        $this->info('Kopia zapasowa: '.$zipPath);

        return self::SUCCESS;
    }

    /** Adds a directory tree and returns the number of files added. */
    private function addDirectory(ZipArchive $zip, string $path, string $prefix): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $added = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = $prefix.'/'.str_replace('\\', '/', substr($absolute, strlen($path) + 1));

            if ($zip->addFile($absolute, $relative)) {
                $added++;
            }
        }

        return $added;
    }

    private function dumpMySql(): ?string
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();
        } catch (\Throwable $exception) {
            $this->error('Brak połączenia z bazą: '.$exception->getMessage());

            return null;
        }

        $sql = '-- Studio-Komp Serwis backup '.now()->utc()->format('Y-m-d H:i:s')." UTC\n\n";

        foreach (['users', 'services', 'service_photos', 'service_events'] as $table) {
            $rows = DB::table($table)->get();

            foreach ($rows as $row) {
                /** @var array<string, mixed> $values */
                $values = (array) $row;
                $columns = implode(', ', array_map(static fn (string $column): string => '`'.$column.'`', array_keys($values)));
                $placeholders = implode(', ', array_map([$this, 'quote'], array_values($values)));

                $sql .= sprintf("INSERT INTO `%s` (%s) VALUES (%s);\n", $table, $columns, $placeholders);
            }

            $sql .= "\n";
        }

        return $sql;
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'";
    }
}
