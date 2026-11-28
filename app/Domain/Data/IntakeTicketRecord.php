<?php

declare(strict_types=1);

namespace App\Domain\Data;

use App\Domain\Enums\IntakeStatus;
use App\Domain\ValueObjects\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlRoutable;

/**
 * Storage independent submission from the public site form (2026-11-27).
 *
 * Both data stores produce this object. `number` is the CMS ticket number and the
 * idempotency key; `submitted_at` is when the customer sent the form, while
 * `created_at` is when this application stored it.
 */
final class IntakeTicketRecord implements UrlRoutable
{
    public function __construct(
        public readonly int $id,
        public readonly string $number,
        public readonly string $name,
        public readonly string $phone,
        public readonly string $device,
        public readonly string $problem,
        public readonly ?string $sourcePage,
        public readonly CarbonImmutable $submittedAt,
        public readonly IntakeStatus $status,
        public readonly ?string $rejectionReason,
        public readonly ?CarbonImmutable $contactedAt,
        public readonly ?CarbonImmutable $rejectedAt,
        public readonly ?int $rejectedBy,
        public readonly ?CarbonImmutable $convertedAt,
        public readonly ?int $convertedServiceId,
        public readonly ?string $convertedServiceReference,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $now = CarbonImmutable::now('UTC');
        $submitted = self::date($data, 'submitted_at') ?? $now;

        return new self(
            id: self::int($data, 'id'),
            number: self::string($data, 'number'),
            name: self::string($data, 'name'),
            phone: self::string($data, 'phone'),
            device: self::string($data, 'device'),
            problem: self::string($data, 'problem'),
            sourcePage: self::nullableString($data, 'source_page'),
            submittedAt: $submitted,
            status: IntakeStatus::fromLegacy(self::scalarString($data, 'status', 'new')),
            rejectionReason: self::nullableString($data, 'rejection_reason'),
            contactedAt: self::date($data, 'contacted_at'),
            rejectedAt: self::date($data, 'rejected_at'),
            rejectedBy: self::nullableInt($data, 'rejected_by'),
            convertedAt: self::date($data, 'converted_at'),
            convertedServiceId: self::nullableInt($data, 'converted_service_id'),
            convertedServiceReference: self::nullableString($data, 'converted_service_reference'),
            createdAt: self::date($data, 'created_at') ?? $now,
            updatedAt: self::date($data, 'updated_at') ?? $now,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'phone' => $this->phone,
            'device' => $this->device,
            'problem' => $this->problem,
            'source_page' => $this->sourcePage,
            'submitted_at' => self::formatDate($this->submittedAt),
            'status' => $this->status->value,
            'rejection_reason' => $this->rejectionReason,
            'contacted_at' => self::formatDate($this->contactedAt),
            'rejected_at' => self::formatDate($this->rejectedAt),
            'rejected_by' => $this->rejectedBy,
            'converted_at' => self::formatDate($this->convertedAt),
            'converted_service_id' => $this->convertedServiceId,
            'converted_service_reference' => $this->convertedServiceReference,
            'created_at' => self::formatDate($this->createdAt),
            'updated_at' => self::formatDate($this->updatedAt),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
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
