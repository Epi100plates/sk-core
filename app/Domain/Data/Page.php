<?php

declare(strict_types=1);

namespace App\Domain\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Minimal, storage independent pagination result.
 *
 * @template TValue
 *
 * @implements Arrayable<string, mixed>
 */
final class Page implements Arrayable
{
    /**
     * @param  list<TValue>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $page,
    ) {}

    /**
     * @template TItem
     *
     * @param  list<TItem>  $items
     * @return Page<TItem>
     */
    public static function of(array $items, int $total, int $perPage, int $page): self
    {
        return new self($items, $total, max(1, $perPage), max(1, $page));
    }

    /**
     * @return Page<never>
     */
    public static function empty(int $perPage = 20, int $page = 1): self
    {
        return new self([], 0, $perPage, $page);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function lastPage(): int
    {
        if ($this->perPage <= 0) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasMore(): bool
    {
        return $this->page < $this->lastPage();
    }

    public function previousPage(): ?int
    {
        return $this->page > 1 ? $this->page - 1 : null;
    }

    public function nextPage(): ?int
    {
        return $this->hasMore() ? $this->page + 1 : null;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        return $this->total === 0 ? 0 : $this->from() + $this->count() - 1;
    }

    /**
     * @return array{items: list<TValue>, total: int, per_page: int, page: int}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ];
    }
}
