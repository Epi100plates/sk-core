<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use App\Contracts\IntakeTicketRepository;
use App\Domain\Data\IntakeQuery;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\Page;
use App\Domain\Exceptions\DataStoreException;
use Carbon\CarbonImmutable;

final class JsonIntakeTicketRepository implements IntakeTicketRepository
{
    use ReadsJsonCollections;

    private const COLLECTION = 'intake_tickets';

    public function __construct(private readonly JsonStore $store) {}

    public function find(int $id): ?IntakeTicketRecord
    {
        foreach ($this->rows(self::COLLECTION) as $row) {
            if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $id) {
                return IntakeTicketRecord::fromArray($row);
            }
        }

        return null;
    }

    public function findByNumber(string $number): ?IntakeTicketRecord
    {
        $needle = mb_strtolower(trim($number));

        foreach ($this->rows(self::COLLECTION) as $row) {
            if (mb_strtolower(trim((string) ($row['number'] ?? ''))) === $needle) {
                return IntakeTicketRecord::fromArray($row);
            }
        }

        return null;
    }

    public function save(IntakeTicketRecord $ticket): IntakeTicketRecord
    {
        return $this->store->withTransaction(function (JsonTransaction $transaction) use ($ticket): IntakeTicketRecord {
            $rows = $transaction->rows(self::COLLECTION);
            $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

            if ($ticket->id === 0) {
                // The CMS ticket number is the idempotency key. A concurrent
                // delivery that slipped past the action's lookup is rejected
                // instead of producing a second row.
                foreach ($rows as $row) {
                    if (mb_strtolower(trim((string) ($row['number'] ?? ''))) === mb_strtolower(trim($ticket->number))) {
                        throw new DataStoreException('Zgłoszenie '.$ticket->number.' już istnieje.');
                    }
                }

                $stored = $ticket->with([
                    'id' => $transaction->nextId(self::COLLECTION),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $transaction->replace(self::COLLECTION, [...$rows, $stored->toArray()]);

                return $stored;
            }

            $stored = $ticket->with(['updated_at' => $now]);
            $result = [];
            $replaced = false;

            foreach ($rows as $row) {
                if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $stored->id) {
                    $result[] = $stored->toArray();
                    $replaced = true;

                    continue;
                }

                $result[] = $row;
            }

            if (! $replaced) {
                throw new DataStoreException('Zgłoszenie #'.$stored->id.' nie istnieje już w magazynie danych.');
            }

            $transaction->replace(self::COLLECTION, $result);

            return $stored;
        });
    }

    public function paginate(IntakeQuery $query): Page
    {
        $statuses = $query->statusValues();
        $term = $query->hasSearchTerm() ? mb_strtolower(trim((string) $query->term)) : null;

        $rows = array_values(array_filter(
            $this->rows(self::COLLECTION),
            static function (array $row) use ($statuses, $term): bool {
                if ($statuses !== [] && ! in_array((string) ($row['status'] ?? ''), $statuses, true)) {
                    return false;
                }

                if ($term === null || $term === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['number'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['device'] ?? ''),
                    (string) ($row['problem'] ?? ''),
                ]));

                return str_contains($haystack, $term);
            },
        ));

        usort($rows, static fn (array $a, array $b): int => self::compareDates($b['submitted_at'] ?? null, $a['submitted_at'] ?? null)
            ?: ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0)));

        $total = count($rows);
        $slice = array_slice($rows, $query->offset(), $query->perPage);

        return Page::of(
            array_map(static fn (array $row): IntakeTicketRecord => IntakeTicketRecord::fromArray($row), $slice),
            $total,
            $query->perPage,
            $query->page,
        );
    }

    public function countsByStatus(): array
    {
        $counts = [];

        foreach ($this->rows(self::COLLECTION) as $row) {
            $status = (string) ($row['status'] ?? '');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return list<IntakeTicketRecord> */
    public function all(): array
    {
        $rows = $this->rows(self::COLLECTION);

        usort($rows, static fn (array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));

        return array_map(
            static fn (array $row): IntakeTicketRecord => IntakeTicketRecord::fromArray($row),
            $rows,
        );
    }

    public function count(): int
    {
        return count($this->rows(self::COLLECTION));
    }

    private static function compareDates(mixed $a, mixed $b): int
    {
        $left = is_string($a) && $a !== '' ? CarbonImmutable::parse($a, 'UTC') : null;
        $right = is_string($b) && $b !== '' ? CarbonImmutable::parse($b, 'UTC') : null;

        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $left <=> $right;
    }
}
