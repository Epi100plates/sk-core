<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * State of a submission received from the public site form (2026-11-27).
 *
 * A submission is never deleted: it moves from `new` to `contacted`, and ends as
 * either `converted` (a repair order was created from it) or `rejected` (with a
 * short reason). The store keeps every row, so nothing silently disappears.
 */
enum IntakeStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::Contacted => 'W kontakcie',
            self::Converted => 'Przyjęte',
            self::Rejected => 'Odrzucone',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'primary',
            self::Converted => 'success',
            self::Rejected => 'secondary',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Converted || $this === self::Rejected;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function fromLegacy(?string $value): self
    {
        return match (mb_strtolower(trim((string) $value))) {
            'nowe', 'new' => self::New,
            'w_kontakcie', 'w kontakcie', 'contacted' => self::Contacted,
            'przyjete', 'przyjęte', 'converted' => self::Converted,
            'odrzucone', 'rejected' => self::Rejected,
            default => self::New,
        };
    }
}
