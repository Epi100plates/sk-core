<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Single source of truth for service statuses (decision D18: the workflow is
 * enforced, not just suggested). Legacy values are mapped on import only.
 */
enum ServiceStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingParts = 'awaiting_parts';
    case ReadyForPickup = 'ready_for_pickup';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Oczekujące',
            self::InProgress => 'W trakcie naprawy',
            self::AwaitingParts => 'Czeka na części',
            self::ReadyForPickup => 'Do wydania',
            self::Released => 'Wydane',
            self::Cancelled => 'Anulowane',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'primary',
            self::AwaitingParts => 'info',
            self::ReadyForPickup => 'success',
            self::Released => 'dark',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Order of the queue (feature F-22). Final states always sort last so a
     * released express service can never jump above active repairs (defect D-46).
     */
    public function queuePriority(): int
    {
        return match ($this) {
            self::InProgress => 1,
            self::Pending => 2,
            self::AwaitingParts => 3,
            self::ReadyForPickup => 4,
            self::Released => 5,
            self::Cancelled => 6,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Released || $this === self::Cancelled;
    }

    public function isActive(): bool
    {
        return ! $this->isFinal();
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress, self::AwaitingParts, self::ReadyForPickup, self::Cancelled],
            self::InProgress => [self::AwaitingParts, self::ReadyForPickup, self::Cancelled],
            self::AwaitingParts => [self::InProgress, self::ReadyForPickup, self::Cancelled],
            self::ReadyForPickup => [self::InProgress, self::AwaitingParts, self::Cancelled, self::Released],
            self::Released => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Next step of the "następny etap" button (F-24). `null` means there is no
     * generic step left: releasing goes through the release action (F-39) and
     * final states have no action at all.
     */
    public function nextStatus(): ?self
    {
        return match ($this) {
            self::Pending => self::InProgress,
            self::InProgress => self::ReadyForPickup,
            self::AwaitingParts => self::InProgress,
            self::ReadyForPickup, self::Released, self::Cancelled => null,
        };
    }

    public function advanceLabel(): string
    {
        return match ($this) {
            self::Pending => 'Rozpocznij naprawę',
            self::InProgress => 'Oznacz do odbioru',
            self::AwaitingParts => 'Wznów naprawę',
            self::ReadyForPickup, self::Released, self::Cancelled => '',
        };
    }

    public static function fromLegacy(?string $legacy): self
    {
        return match (mb_strtolower(trim((string) $legacy))) {
            'w_trakcie', 'w trakcie' => self::InProgress,
            'czeka_na_czesci', 'czeka na czesci', 'czeka na części' => self::AwaitingParts,
            'do_wydania', 'do wydania' => self::ReadyForPickup,
            'wydany', 'wydane' => self::Released,
            'anulowane', 'anulowany', 'cancelled' => self::Cancelled,
            default => self::Pending,
        };
    }

    /** @return list<self> */
    public static function activeCases(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s): bool => $s->isActive()));
    }

    /** @return array<string, string> value => label, for select inputs */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return array<string, string> value => label, only statuses reachable from an edit form */
    public static function editableOptions(): array
    {
        $options = [];

        foreach (self::activeCases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
