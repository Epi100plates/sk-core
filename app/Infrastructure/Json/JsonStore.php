<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use App\Domain\Exceptions\DataStoreCorruptedException;
use App\Domain\Exceptions\DataStoreException;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * File based data store (decision D13, feature F-57).
 *
 * Every read-modify-write cycle happens inside one exclusive `flock`, and every
 * file is replaced through `rename()` of a temporary file, so a crash never
 * leaves a half written collection behind. Schema version mismatches produce a
 * `.bak` copy plus a controlled exception instead of silent data loss.
 */
final class JsonStore
{
    public const SCHEMA_VERSION = 1;

    /** @var list<string> */
    public const COLLECTIONS = ['services', 'service_events', 'service_photos', 'users'];

    private const SEPARATOR = DIRECTORY_SEPARATOR;

    private int $depth = 0;

    private ?JsonTransaction $transaction = null;

    public function __construct(
        private readonly string $path,
        private readonly bool $pretty = false,
        private readonly bool $useLock = true,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function file(string $collection): string
    {
        return $this->path.self::SEPARATOR.$collection.'.json';
    }

    public function metaFile(): string
    {
        return $this->path.self::SEPARATOR.'meta.json';
    }

    public function lockFile(): string
    {
        return $this->path.self::SEPARATOR.'store.lock';
    }

    public function isInitialized(): bool
    {
        if (! is_file($this->metaFile())) {
            return false;
        }

        foreach (self::COLLECTIONS as $collection) {
            if (! is_file($this->file($collection))) {
                return false;
            }
        }

        return true;
    }

    /** Creates the directory, `meta.json` and empty collections. Idempotent. */
    public function initialize(): void
    {
        $this->ensureDirectory();

        if (! is_file($this->metaFile())) {
            $this->writeJson($this->metaFile(), $this->freshMeta());
        }

        foreach (self::COLLECTIONS as $collection) {
            if (! is_file($this->file($collection))) {
                $this->writeJson($this->file($collection), $this->payload([]));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function freshMeta(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'next_ids' => array_fill_keys(self::COLLECTIONS, 1),
            'reference_year' => (int) CarbonImmutable::now('UTC')->format('Y'),
            'reference_counter' => 1,
            'last_write' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readMeta(): array
    {
        $decoded = $this->readJson($this->metaFile());

        if ($decoded === null) {
            return $this->freshMeta();
        }

        $this->assertSchemaVersion($this->metaFile(), $decoded);

        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readCollection(string $collection): array
    {
        $active = $this->transaction;

        if ($active !== null) {
            return $active->rows($collection);
        }

        return $this->readCollectionFromDisk($collection);
    }

    /**
     * Reads the collection straight from the file, ignoring any open transaction.
     * Used by the transaction itself when it loads a collection for the first time.
     *
     * @return list<array<string, mixed>>
     */
    public function readCollectionFromDisk(string $collection): array
    {
        $decoded = $this->readJson($this->file($collection));

        if ($decoded === null) {
            return [];
        }

        $this->assertSchemaVersion($this->file($collection), $decoded);
        $rows = $decoded['rows'] ?? [];

        return is_array($rows) ? $this->normalizeRows($rows) : [];
    }

    /**
     * @template TReturn
     *
     * @param  callable(JsonTransaction): TReturn  $callback
     * @return TReturn
     */
    public function withTransaction(callable $callback): mixed
    {
        if ($this->depth > 0 && $this->transaction instanceof JsonTransaction) {
            $this->depth++;

            try {
                return $callback($this->transaction);
            } finally {
                $this->depth--;
            }
        }

        $this->initialize();
        $handle = $this->acquireLock();
        $this->depth = 1;
        $this->transaction = new JsonTransaction($this);

        try {
            /** @var TReturn $result */
            $result = $callback($this->transaction);
            $this->transaction->flush();

            return $result;
        } finally {
            $this->transaction = null;
            $this->depth = 0;
            $this->releaseLock($handle);
        }
    }

    public function activeTransaction(): ?JsonTransaction
    {
        return $this->transaction;
    }

    public function inTransaction(): bool
    {
        return $this->transaction instanceof JsonTransaction;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function writeJson(string $file, array $payload): void
    {
        $this->ensureDirectory();
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($this->pretty ? JSON_PRETTY_PRINT : 0)
        );

        if ($json === false) {
            throw new DataStoreException('Nie udało się zakodować danych do JSON: '.$file);
        }

        $temporary = $file.'.tmp'.bin2hex(random_bytes(4));

        if (file_put_contents($temporary, $json) === false) {
            throw new DataStoreException('Nie udało się zapisać pliku danych: '.$file);
        }

        if (! @rename($temporary, $file)) {
            // Windows keeps the target locked for a moment in rare cases.
            @unlink($file);

            if (! @rename($temporary, $file)) {
                @unlink($temporary);

                throw new DataStoreException('Nie udało się podmienić pliku danych: '.$file);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function payload(array $rows): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'rows' => array_values($rows),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            throw new DataStoreException('Nie można odczytać pliku danych: '.$file);
        }

        try {
            if ($this->useLock) {
                @flock($handle, LOCK_SH);
            }

            $contents = stream_get_contents($handle);
        } finally {
            if ($this->useLock) {
                @flock($handle, LOCK_UN);
            }

            fclose($handle);
        }

        if ($contents === false || trim($contents) === '') {
            $this->handleCorruption($file, 'plik jest pusty');

            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            $this->handleCorruption($file, 'niepoprawny JSON: '.json_last_error_msg());

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertSchemaVersion(string $file, array $payload): void
    {
        $version = $payload['schema_version'] ?? null;

        if ($version === self::SCHEMA_VERSION) {
            return;
        }

        $this->backup($file, 'schema-'.(is_scalar($version) ? (string) $version : 'missing'));

        throw new DataStoreCorruptedException(sprintf(
            'Plik %s ma nieobsługiwaną wersję schematu (%s). Kopia zapasowa została zapisana obok — uruchom `php artisan store:check`.',
            $file,
            is_scalar($version) ? (string) $version : 'brak',
        ));
    }

    private function handleCorruption(string $file, string $reason): void
    {
        $this->backup($file, 'corrupt');

        throw new DataStoreCorruptedException(sprintf(
            'Plik danych %s jest uszkodzony (%s). Kopia zapasowa została zapisana obok; aplikacja nie nadpisała danych.',
            $file,
            $reason,
        ));
    }

    private function backup(string $file, string $suffix): void
    {
        if (! is_file($file)) {
            return;
        }

        $target = $file.'.'.$suffix.'.bak';

        if (is_file($target)) {
            $target = $file.'.'.$suffix.'-'.CarbonImmutable::now('UTC')->format('YmdHis').'.bak';
        }

        @copy($file, $target);
    }

    /**
     * @param  array<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->path)) {
            return;
        }

        if (! @mkdir($this->path, 0700, true) && ! is_dir($this->path)) {
            throw new DataStoreException('Nie można utworzyć katalogu danych: '.$this->path);
        }
    }

    /**
     * @return resource
     */
    private function acquireLock(): mixed
    {
        $this->ensureDirectory();
        $handle = @fopen($this->lockFile(), 'c+');

        if ($handle === false) {
            throw new DataStoreException('Nie można otworzyć pliku blokady: '.$this->lockFile());
        }

        if ($this->useLock && ! @flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new RuntimeException('Nie udało się zablokować pliku danych.');
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function releaseLock(mixed $handle): void
    {
        if (is_resource($handle)) {
            if ($this->useLock) {
                @flock($handle, LOCK_UN);
            }

            fclose($handle);
        }
    }
}
