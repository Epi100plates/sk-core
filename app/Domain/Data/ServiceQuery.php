<?php

declare(strict_types=1);

namespace App\Domain\Data;

use App\Domain\Enums\ServiceStatus;
use Carbon\CarbonImmutable;

/**
 * One query object for the queue, the archive and the search page (F-21, F-26,
 * F-27, F-32). Both stores translate it, so sorting and filtering have exactly
 * one meaning (defect D-19: three different sort priorities).
 */
final class ServiceQuery
{
    public const SORT_QUEUE = 'queue';

    public const SORT_RELEASED = 'released';

    public const SORT_ACCEPTED = 'accepted';

    /**
     * @param  list<ServiceStatus>  $statuses
     */
    public function __construct(
        public readonly array $statuses = [],
        public readonly ?string $term = null,
        public readonly ?CarbonImmutable $releasedFrom = null,
        public readonly ?CarbonImmutable $releasedTo = null,
        public readonly string $sort = self::SORT_QUEUE,
        public readonly int $perPage = 50,
        public readonly int $page = 1,
    ) {}

    /**
     * @param  list<ServiceStatus>  $statuses
     */
    public static function queue(array $statuses = [], int $perPage = 50, int $page = 1): self
    {
        return new self(
            statuses: $statuses,
            sort: self::SORT_QUEUE,
            perPage: $perPage,
            page: $page,
        );
    }

    /**
     * @param  list<ServiceStatus>  $statuses
     */
    public static function forStatuses(array $statuses, int $perPage = 50, int $page = 1): self
    {
        return new self(statuses: $statuses, sort: self::SORT_ACCEPTED, perPage: $perPage, page: $page);
    }

    public static function archive(int $perPage = 25, int $page = 1): self
    {
        return new self(
            statuses: [ServiceStatus::Released],
            sort: self::SORT_RELEASED,
            perPage: $perPage,
            page: $page,
        );
    }

    public static function search(string $term, int $perPage = 25, int $page = 1): self
    {
        return new self(term: $term, sort: self::SORT_ACCEPTED, perPage: $perPage, page: $page);
    }

    /**
     * @param  list<ServiceStatus>  $statuses
     */
    public function withStatuses(array $statuses): self
    {
        return new self(
            statuses: $statuses,
            term: $this->term,
            releasedFrom: $this->releasedFrom,
            releasedTo: $this->releasedTo,
            sort: $this->sort,
            perPage: $this->perPage,
            page: $this->page,
        );
    }

    public function withPage(int $page): self
    {
        return new self(
            statuses: $this->statuses,
            term: $this->term,
            releasedFrom: $this->releasedFrom,
            releasedTo: $this->releasedTo,
            sort: $this->sort,
            perPage: $this->perPage,
            page: max(1, $page),
        );
    }

    public function withReleasedRange(?CarbonImmutable $from, ?CarbonImmutable $to): self
    {
        return new self(
            statuses: $this->statuses,
            term: $this->term,
            releasedFrom: $from,
            releasedTo: $to,
            sort: $this->sort,
            perPage: $this->perPage,
            page: $this->page,
        );
    }

    /** Search only makes sense from three characters (F-27, defect D-21). */
    public function hasSearchTerm(): bool
    {
        return $this->term !== null && mb_strlen($this->term) >= 3;
    }

    /** @return list<string> */
    public function statusValues(): array
    {
        return array_map(static fn (ServiceStatus $status): string => $status->value, $this->statuses);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
