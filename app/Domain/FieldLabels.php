<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Enums\ServiceStatus;
use App\Domain\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Polish field labels and value formatting for the audit panel (F-38 / D-57).
 * The old application printed raw column names (`planowany_czas_wydania`) and
 * ISO timestamps; everything the employee sees now comes from here.
 */
final class FieldLabels
{
    /** @var array<string, string> */
    private const LABELS = [
        'reference' => 'Numer zlecenia',
        'legacy_id' => 'ID w starym systemie',
        'phone' => 'Telefon',
        'phone_normalized' => 'Telefon (znormalizowany)',
        'device_model' => 'Model urządzenia',
        'serial_number' => 'Numer seryjny',
        'issue' => 'Usterka',
        'device_password' => 'Hasło do urządzenia',
        'notes' => 'Uwagi',
        'is_express' => 'Express',
        'planned_at' => 'Termin realizacji',
        'price' => 'Cena dla klienta',
        'cost' => 'Koszt części',
        'seal_number' => 'Numer plomby',
        'status' => 'Status',
        'accepted_by' => 'Przyjął',
        'accepted_at' => 'Data przyjęcia',
        'confirmation_issued_at' => 'Potwierdzenie przyjęcia wydane',
        'released_at' => 'Data wydania',
        'released_by' => 'Wydał',
        'cancelled_at' => 'Data anulowania',
        'cancelled_by' => 'Anulował',
        'cancellation_reason' => 'Powód anulowania',
    ];

    /** @var list<string> */
    private const DATE_FIELDS = [
        'planned_at',
        'accepted_at',
        'confirmation_issued_at',
        'released_at',
        'cancelled_at',
        'created_at',
        'updated_at',
    ];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }

    public static function format(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return self::formatDate($value);
        }

        if ($field === 'price' || $field === 'cost') {
            return is_scalar($value) ? Money::formatDecimalString((string) $value) : '—';
        }

        if ($field === 'is_express') {
            return self::isTruthy($value) ? 'Tak' : 'Nie';
        }

        if ($field === 'status') {
            $status = $value instanceof ServiceStatus ? $value : ServiceStatus::tryFrom((string) $value);

            return $status?->label() ?? (string) $value;
        }

        return is_scalar($value) ? (string) $value : '—';
    }

    public static function formatDate(mixed $value): string
    {
        try {
            if ($value instanceof CarbonImmutable) {
                $date = $value;
            } elseif ($value instanceof \DateTimeInterface) {
                $date = CarbonImmutable::instance($value);
            } elseif (is_scalar($value)) {
                $date = CarbonImmutable::parse((string) $value, 'UTC');
            } else {
                return '—';
            }
        } catch (Throwable) {
            return is_scalar($value) ? (string) $value : '—';
        }

        return $date->timezone(self::displayTimezone())->format('d.m.Y H:i');
    }

    public static function displayTimezone(): string
    {
        $timezone = config('app.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Europe/Warsaw';
    }

    private static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'on', 'yes', 'Tak'], true);
    }
}
