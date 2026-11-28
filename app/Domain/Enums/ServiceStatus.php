<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Single source of truth for service statuses (decision D18: the workflow is
 * enforced, not just suggested). Legacy values are mapped on import only.
 *
 * The release approval flow (owner request, 2026-11-27) adds two stages:
 *  - awaiting_approval: the technician finished the repair and asked the boss to
 *    accept the release ("Gotowy do wydania"),
 *  - with_remarks: the boss returned the laptop to the repair queue with remarks.
 * `ready_for_pickup` keeps its old meaning: the release counter, where the
 * laptop is handed over exactly like before.
 */
enum ServiceStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingParts = 'awaiting_parts';
    case AwaitingApproval = 'awaiting_approval';
    case WithRemarks = 'with_remarks';
    case ReadyForPickup = 'ready_for_pickup';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Oczekujące',
            self::InProgress => 'W trakcie naprawy',
            self::AwaitingParts => 'Czeka na części',
            self::AwaitingApproval => 'Oczekuje na akceptację',
            self::WithRemarks => 'Z uwagami',
            self::ReadyForPickup => 'Do wydania',
            self::Released => 'Wydane',
            self::Cancelled => 'Anulowane',
        };
    }

    /**
     * One-word labels for the phone filter strip. The full name stays in the
     * accessible label; this only keeps the stages on one row.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Pending => 'Oczek.',
            self::InProgress => 'Naprawa',
            self::AwaitingParts => 'Części',
            self::AwaitingApproval => 'Akcept.',
            self::WithRemarks => 'Uwagi',
            self::ReadyForPickup => 'Got.',
            self::Released => 'Wydane',
            self::Cancelled => 'Anul.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'primary',
            self::AwaitingParts => 'info',
            self::AwaitingApproval => 'warning',
            self::WithRemarks => 'danger',
            self::ReadyForPickup => 'success',
            self::Released => 'dark',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Order of the queue (feature F-22). Stages that wait for one person to act
     * come first (remarks and the boss acceptance), then active repairs; final
     * states always sort last so a released express service can never jump above
     * active repairs (defect D-46).
     */
    public function queuePriority(): int
    {
        return match ($this) {
            self::WithRemarks => 1,
            self::InProgress => 2,
            self::AwaitingApproval => 3,
            self::Pending => 4,
            self::AwaitingParts => 5,
            self::ReadyForPickup => 6,
            self::Released => 7,
            self::Cancelled => 8,
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
            self::InProgress => [self::AwaitingParts, self::AwaitingApproval, self::Cancelled],
            self::AwaitingParts => [self::InProgress, self::AwaitingApproval, self::Cancelled],
            self::AwaitingApproval => [self::ReadyForPickup, self::WithRemarks, self::Cancelled],
            self::WithRemarks => [self::InProgress, self::AwaitingParts, self::Cancelled],
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
     * generic step left: the boss acceptance, releasing (F-39) and final states
     * all have their own action or no action at all.
     */
    public function nextStatus(): ?self
    {
        return match ($this) {
            self::Pending => self::InProgress,
            self::InProgress => self::AwaitingApproval,
            self::AwaitingParts => self::InProgress,
            self::WithRemarks => self::InProgress,
            self::AwaitingApproval, self::ReadyForPickup, self::Released, self::Cancelled => null,
        };
    }

    public function advanceLabel(): string
    {
        return match ($this) {
            self::Pending => 'Rozpocznij naprawę',
            self::InProgress => 'Gotowy do wydania',
            self::AwaitingParts => 'Wznów naprawę',
            self::WithRemarks => 'Wznów naprawę',
            self::AwaitingApproval, self::ReadyForPickup, self::Released, self::Cancelled => '',
        };
    }

    public static function fromLegacy(?string $legacy): self
    {
        return match (mb_strtolower(trim((string) $legacy))) {
            'w_trakcie', 'w trakcie' => self::InProgress,
            'czeka_na_czesci', 'czeka na czesci', 'czeka na części' => self::AwaitingParts,
            'oczekuje_na_akceptacje', 'oczekuje na akceptację', 'oczekuje na akceptacje' => self::AwaitingApproval,
            'z_uwagami', 'z uwagami' => self::WithRemarks,
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
