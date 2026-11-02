<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\ValueObjects\Money;

/**
 * Computes the audit diff between two service snapshots.
 *
 * Only fields that really changed appear in an event, which is what prevents the
 * old "edit saved, nothing changed, but history says the deadline moved"
 * behaviour (defect D-44) and makes `history` trustworthy.
 */
final class ServiceDiff
{
    /** @var list<string> */
    public const TRACKED = [
        'phone',
        'device_model',
        'serial_number',
        'issue',
        'device_password',
        'notes',
        'is_express',
        'planned_at',
        'price',
        'cost',
        'seal_number',
        'status',
        'confirmation_issued_at',
        'released_at',
        'released_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function between(array $before, array $after): array
    {
        $changes = [];

        foreach (self::TRACKED as $field) {
            $from = self::normalize($field, $before[$field] ?? null);
            $to = self::normalize($field, $after[$field] ?? null);

            if ($from === $to) {
                continue;
            }

            $changes[$field] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * Snapshot of the fields that actually carry a value when the service is
     * accepted (F-20). Empty optional fields are skipped so the history entry
     * stays readable instead of listing a dozen "— → —" lines (D-57).
     *
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: null, to: mixed}>
     */
    public static function snapshot(array $after): array
    {
        $snapshot = [];

        foreach (self::TRACKED as $field) {
            $value = self::normalize($field, $after[$field] ?? null);

            if ($value === null || $value === false) {
                continue;
            }

            $snapshot[$field] = ['from' => null, 'to' => $value];
        }

        return $snapshot;
    }

    private static function normalize(string $field, mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($field === 'price' || $field === 'cost') {
            return Money::fromDecimalString((string) $value)->toDecimalString();
        }

        if ($field === 'is_express') {
            return in_array($value, [true, 1, '1', 'true', 'on'], true);
        }

        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : $value;
    }
}
