<?php

declare(strict_types=1);

namespace App\Domain\Data;

use App\Domain\Enums\IntakeStatus;

/**
 * Query object for the submissions inbox. Both stores translate it, so the
 * filter, the search and "newest first" have exactly one meaning.
 */
final class IntakeQuery
{
    /** @param  list<IntakeStatus>  $statuses */
    public function __construct(
        public readonly array $statuses = [],
        public readonly ?string $term = null,
        public readonly int $perPage = 30,
        public readonly int $page = 1,
    ) {}

    public static function newest(?IntakeStatus $status = null, int $perPage = 30, int $page = 1): self
    {
        return new self(
            statuses: $status instanceof IntakeStatus ? [$status] : [],
            perPage: $perPage,
            page: $page,
        );
    }

    public function offset(): int
    {
        return (max(1, $this->page) - 1) * $this->perPage;
    }

    public function hasSearchTerm(): bool
    {
        return $this->term !== null && mb_strlen($this->term) >= 3;
    }

    /** @return list<string> */
    public function statusValues(): array
    {
        return array_map(static fn (IntakeStatus $status): string => $status->value, $this->statuses);
    }
}
