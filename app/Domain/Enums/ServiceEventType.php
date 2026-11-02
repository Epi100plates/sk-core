<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Audit event types (F-50, F-38). One event per real change — never the old
 * duplicated "ADD_SERVICE + SERVICE_ADDED" log pair (defect D-54).
 */
enum ServiceEventType: string
{
    case Accepted = 'accepted';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Released = 'released';
    case PhotoAdded = 'photo_added';
    case PhotoDeleted = 'photo_deleted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Przyjęto zlecenie',
            self::Updated => 'Zaktualizowano dane',
            self::StatusChanged => 'Zmiana statusu',
            self::Released => 'Wydano zlecenie',
            self::PhotoAdded => 'Dodano zdjęcie',
            self::PhotoDeleted => 'Usunięto zdjęcie',
            self::Cancelled => 'Anulowano zlecenie',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Accepted => 'primary',
            self::Updated => 'secondary',
            self::StatusChanged => 'info',
            self::Released => 'success',
            self::PhotoAdded => 'light',
            self::PhotoDeleted => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
