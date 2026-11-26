<?php

declare(strict_types=1);

namespace App\Domain\Data;

use App\Domain\Enums\ServiceStatus;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlRoutable;

/**
 * Storage independent representation of a service order.
 *
 * Both data stores produce this object, so actions, controllers and views never
 * depend on Eloquent or on JSON arrays (section 5.7 of the plan). Keys used by
 * `toArray()`/`fromArray()` are the storage column names from section 6.1.
 *
 * It is `UrlRoutable` so `route('services.show', $service)` works like it does
 * for a model, without leaking Eloquent into the domain.
 */
final class ServiceRecord implements UrlRoutable
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $legacyId,
        public readonly string $reference,
        public readonly string $phone,
        public readonly ?string $phoneNormalized,
        public readonly string $deviceModel,
        public readonly ?string $serialNumber,
        public readonly string $issue,
        public readonly ?string $devicePassword,
        public readonly ?string $notes,
        public readonly bool $isExpress,
        public readonly CarbonImmutable $plannedAt,
        public readonly string $price,
        public readonly string $cost,
        public readonly ?string $sealNumber,
        public readonly ServiceStatus $status,
        public readonly int $acceptedBy,
        public readonly CarbonImmutable $acceptedAt,
        public readonly ?CarbonImmutable $confirmationIssuedAt,
        public readonly ?CarbonImmutable $releasedAt,
        public readonly ?int $releasedBy,
        public readonly ?CarbonImmutable $cancelledAt,
        public readonly ?int $cancelledBy,
        public readonly ?string $cancellationReason,
        public readonly int $version,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
        public readonly ?CarbonImmutable $deletedAt = null,
        public readonly ?string $clientStatusUrl = null,
        public readonly ?CarbonImmutable $clientStatusSyncedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $now = CarbonImmutable::now('UTC');
        $acceptedAt = self::date($data, 'accepted_at') ?? $now;

        return new self(
            id: self::int($data, 'id'),
            legacyId: self::nullableInt($data, 'legacy_id'),
            reference: self::string($data, 'reference'),
            phone: self::string($data, 'phone'),
            phoneNormalized: self::nullableString($data, 'phone_normalized'),
            deviceModel: self::string($data, 'device_model'),
            serialNumber: self::nullableString($data, 'serial_number'),
            issue: self::string($data, 'issue'),
            devicePassword: self::nullableString($data, 'device_password'),
            notes: self::nullableString($data, 'notes'),
            isExpress: self::bool($data, 'is_express'),
            plannedAt: self::date($data, 'planned_at') ?? $acceptedAt,
            price: Money::fromInput(self::scalarString($data, 'price', '0'))->toDecimalString(),
            cost: Money::fromInput(self::scalarString($data, 'cost', '0'))->toDecimalString(),
            sealNumber: self::nullableString($data, 'seal_number'),
            status: ServiceStatus::tryFrom(self::scalarString($data, 'status', '')) ?? ServiceStatus::Pending,
            acceptedBy: self::int($data, 'accepted_by'),
            acceptedAt: $acceptedAt,
            confirmationIssuedAt: self::date($data, 'confirmation_issued_at'),
            releasedAt: self::date($data, 'released_at'),
            releasedBy: self::nullableInt($data, 'released_by'),
            cancelledAt: self::date($data, 'cancelled_at'),
            cancelledBy: self::nullableInt($data, 'cancelled_by'),
            cancellationReason: self::nullableString($data, 'cancellation_reason'),
            version: self::int($data, 'version'),
            createdAt: self::date($data, 'created_at') ?? $acceptedAt,
            updatedAt: self::date($data, 'updated_at') ?? $acceptedAt,
            deletedAt: self::date($data, 'deleted_at'),
            clientStatusUrl: self::nullableString($data, 'client_status_url'),
            clientStatusSyncedAt: self::date($data, 'client_status_synced_at'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'legacy_id' => $this->legacyId,
            'reference' => $this->reference,
            'phone' => $this->phone,
            'phone_normalized' => $this->phoneNormalized,
            'device_model' => $this->deviceModel,
            'serial_number' => $this->serialNumber,
            'issue' => $this->issue,
            'device_password' => $this->devicePassword,
            'notes' => $this->notes,
            'is_express' => $this->isExpress,
            'planned_at' => self::formatDate($this->plannedAt),
            'price' => $this->price,
            'cost' => $this->cost,
            'seal_number' => $this->sealNumber,
            'status' => $this->status->value,
            'accepted_by' => $this->acceptedBy,
            'accepted_at' => self::formatDate($this->acceptedAt),
            'confirmation_issued_at' => self::formatDate($this->confirmationIssuedAt),
            'released_at' => self::formatDate($this->releasedAt),
            'released_by' => $this->releasedBy,
            'cancelled_at' => self::formatDate($this->cancelledAt),
            'cancelled_by' => $this->cancelledBy,
            'cancellation_reason' => $this->cancellationReason,
            'version' => $this->version,
            'created_at' => self::formatDate($this->createdAt),
            'updated_at' => self::formatDate($this->updatedAt),
            'deleted_at' => self::formatDate($this->deletedAt),
            'client_status_url' => $this->clientStatusUrl,
            'client_status_synced_at' => self::formatDate($this->clientStatusSyncedAt),
        ];
    }

    /**
     * Immutable partial update. Only the given keys change, everything else is
     * carried over untouched — this is what stops the old application from
     * silently zeroing `cost` or shifting `planned_at` on every save (D-44/D-47).
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
    }

    public function withId(int $id, int $version): self
    {
        return $this->with(['id' => $id, 'version' => $version]);
    }

    public function priceMoney(): Money
    {
        return Money::fromDecimalString($this->price);
    }

    public function costMoney(): Money
    {
        return Money::fromDecimalString($this->cost);
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function hasIssuedConfirmation(): bool
    {
        return $this->confirmationIssuedAt !== null;
    }

    public function displayPhone(): string
    {
        return Phone::fromInput($this->phone)->display();
    }

    public function getRouteKey(): int
    {
        return $this->id;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return null; // binding is handled by routes/web.php through the repository
    }

    public function resolveChildRouteBinding($childType, $value, $field): ?self
    {
        return null;
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** @param array<string, mixed> $data */
    private static function scalarString(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @param array<string, mixed> $data */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param array<string, mixed> $data */
    private static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $data */
    private static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'on', 'yes'], true);
    }

    /** @param array<string, mixed> $data */
    private static function date(array $data, string $key): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC')->utc();
    }

    private static function formatDate(?CarbonImmutable $value): ?string
    {
        return $value?->utc()->format('Y-m-d H:i:s');
    }
}
