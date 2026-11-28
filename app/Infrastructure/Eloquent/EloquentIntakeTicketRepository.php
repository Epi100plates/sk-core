<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Contracts\IntakeTicketRepository;
use App\Domain\Data\IntakeQuery;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\Page;
use App\Domain\Exceptions\DataStoreException;
use App\Models\IntakeTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MySQL implementation of the submissions inbox (2026-11-27). Behaves exactly
 * like `Infrastructure\Json\JsonIntakeTicketRepository`; the shared test suite
 * runs against both.
 */
final class EloquentIntakeTicketRepository implements IntakeTicketRepository
{
    public function find(int $id): ?IntakeTicketRecord
    {
        $model = IntakeTicket::query()->find($id);

        return $model instanceof IntakeTicket ? self::toRecord($model) : null;
    }

    public function findByNumber(string $number): ?IntakeTicketRecord
    {
        $model = IntakeTicket::query()->where('number', trim($number))->first();

        return $model instanceof IntakeTicket ? self::toRecord($model) : null;
    }

    public function save(IntakeTicketRecord $ticket): IntakeTicketRecord
    {
        if ($ticket->id === 0) {
            $model = new IntakeTicket;
            $model->fill(self::attributes($ticket));
            $model->save();

            return self::toRecord($model->refresh());
        }

        return DB::transaction(function () use ($ticket): IntakeTicketRecord {
            $model = IntakeTicket::query()->lockForUpdate()->find($ticket->id);

            if (! $model instanceof IntakeTicket) {
                throw new DataStoreException('Zgłoszenie #'.$ticket->id.' nie istnieje już w magazynie danych.');
            }

            $model->fill(self::attributes($ticket));
            $model->save();

            return self::toRecord($model->refresh());
        });
    }

    public function paginate(IntakeQuery $query): Page
    {
        $builder = IntakeTicket::query();

        if ($query->statusValues() !== []) {
            $builder->whereIn('status', $query->statusValues());
        }

        if ($query->hasSearchTerm()) {
            $term = '%'.self::escapeLike(trim((string) $query->term)).'%';

            $builder->where(function (Builder $inner) use ($term): void {
                foreach (['number', 'name', 'phone', 'device', 'problem'] as $column) {
                    $inner->orWhere($column, 'like', $term);
                }
            });
        }

        $total = (int) (clone $builder)->count();

        $models = $builder
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->forPage(max(1, $query->page), $query->perPage)
            ->get();

        $items = [];

        foreach ($models as $model) {
            $items[] = self::toRecord($model);
        }

        return Page::of($items, $total, $query->perPage, $query->page);
    }

    public function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        $rows = IntakeTicket::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            /** @var object{status: string, aggregate: int|string} $row */
            $counts[(string) $row->status] = (int) $row->aggregate;
        }

        return $counts;
    }

    public function count(): int
    {
        return (int) IntakeTicket::query()->count();
    }

    /** @return list<IntakeTicketRecord> */
    public function all(): array
    {
        $items = [];

        foreach (IntakeTicket::query()->orderBy('id')->get() as $model) {
            $items[] = self::toRecord($model);
        }

        return $items;
    }

    private static function toRecord(IntakeTicket $model): IntakeTicketRecord
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $model->attributesToArray();

        return IntakeTicketRecord::fromArray($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(IntakeTicketRecord $ticket): array
    {
        $attributes = $ticket->toArray();

        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);

        return $attributes;
    }

    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
