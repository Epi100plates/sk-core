<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Enums\ServiceStatus;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Phone;
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
        'approval_remarks' => 'Uwagi szefa',
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

    /**
     * Fields the history screen already shows somewhere else, or that are audit
     * plumbing rather than a fact a workshop employee needs to read twice:
     * status and dates have their own line, actor ids and legacy keys mean
     * nothing in the UI.
     *
     * @var list<string>
     */
    private const HISTORY_HIDDEN = [
        'reference',
        'legacy_id',
        'phone_normalized',
        'status',
        'confirmation_issued_at',
        'released_at',
        'released_by',
        'accepted_by',
        'cancelled_by',
        'cancellation_reason',
        'created_at',
        'updated_at',
    ];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }

    public static function hiddenInHistory(string $field): bool
    {
        return in_array($field, self::HISTORY_HIDDEN, true);
    }

    /**
     * Polish plural with the count in front: `1 zlecenie`, `3 zlecenia`,
     * `22 zlecenia`, `0 zleceń` (the old screens printed `1 zleceń`).
     */
    public static function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($count === 1) {
            $form = $one;
        } elseif ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            $form = $few;
        } else {
            $form = $many;
        }

        return $count.' '.$form;
    }

    public static function format(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        // A device password must never be readable in the audit trail (it is
        // stored encrypted in the record) — the history only tells you whether
        // one is set, not what it is.
        if ($field === 'device_password') {
            return '••••';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return self::formatDate($value);
        }

        if ($field === 'price' || $field === 'cost') {
            return is_scalar($value) ? Money::formatDecimalString((string) $value) : '—';
        }

        if ($field === 'phone') {
            return is_scalar($value) ? Phone::fromInput((string) $value)->display() : '—';
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
        $date = self::toDisplayDate($value);

        return $date instanceof \DateTimeInterface ? $date->format('d.m.Y H:i') : self::fallback($value);
    }

    /**
     * Date without the time — for weekday/day group headers, where "01:00" would
     * only be an artefact of the UTC midnight.
     */
    public static function formatDay(mixed $value): string
    {
        $date = self::toDisplayDate($value);

        return $date instanceof \DateTimeInterface ? $date->format('d.m.Y') : self::fallback($value);
    }

    private static function toDisplayDate(mixed $value): ?\DateTimeInterface
    {
        try {
            if ($value instanceof CarbonImmutable) {
                $date = $value;
            } elseif ($value instanceof \DateTimeInterface) {
                $date = CarbonImmutable::instance($value);
            } elseif (is_scalar($value)) {
                $date = CarbonImmutable::parse((string) $value, 'UTC');
            } else {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $date->timezone(self::displayTimezone());
    }

    private static function fallback(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '—';
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
