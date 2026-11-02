<?php

declare(strict_types=1);

namespace App\Domain\Data;

use App\Domain\Enums\ServiceEventType;
use Carbon\CarbonImmutable;

/**
 * One immutable audit entry (F-38, F-50). There is no `updated_at`: an entry is
 * never edited or deleted, which is what makes it usable as an audit trail.
 */
final class ServiceEventRecord
{
    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function __construct(
        public readonly int $id,
        public readonly int $serviceId,
        public readonly ServiceEventType $type,
        public readonly ?int $actorId,
        public readonly ?string $fromStatus,
        public readonly ?string $toStatus,
        public readonly array $changes,
        public readonly ?string $description,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly CarbonImmutable $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $changes = $data['changes'] ?? [];

        if (is_string($changes) && $changes !== '') {
            $decoded = json_decode($changes, true);
            $changes = is_array($decoded) ? $decoded : [];
        }

        /** @var array<string, array{from: mixed, to: mixed}> $normalized */
        $normalized = [];

        if (is_array($changes)) {
            foreach ($changes as $field => $diff) {
                if (! is_string($field) || ! is_array($diff)) {
                    continue;
                }

                $normalized[$field] = [
                    'from' => $diff['from'] ?? null,
                    'to' => $diff['to'] ?? null,
                ];
            }
        }

        return new self(
            id: is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0,
            serviceId: is_numeric($data['service_id'] ?? null) ? (int) $data['service_id'] : 0,
            type: ServiceEventType::tryFrom((string) ($data['type'] ?? '')) ?? ServiceEventType::Updated,
            actorId: is_numeric($data['actor_id'] ?? null) ? (int) $data['actor_id'] : null,
            fromStatus: self::nullableString($data['from_status'] ?? null),
            toStatus: self::nullableString($data['to_status'] ?? null),
            changes: $normalized,
            description: self::nullableString($data['description'] ?? null),
            ip: self::nullableString($data['ip'] ?? null),
            userAgent: self::nullableString($data['user_agent'] ?? null),
            createdAt: self::toDate($data['created_at'] ?? null) ?? CarbonImmutable::now('UTC'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->serviceId,
            'type' => $this->type->value,
            'actor_id' => $this->actorId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'changes' => $this->changes,
            'description' => $this->description,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'created_at' => $this->createdAt->utc()->format('Y-m-d H:i:s'),
        ];
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            serviceId: $this->serviceId,
            type: $this->type,
            actorId: $this->actorId,
            fromStatus: $this->fromStatus,
            toStatus: $this->toStatus,
            changes: $this->changes,
            description: $this->description,
            ip: $this->ip,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt,
        );
    }

    public function withServiceId(int $serviceId): self
    {
        return new self(
            id: 0,
            serviceId: $serviceId,
            type: $this->type,
            actorId: $this->actorId,
            fromStatus: $this->fromStatus,
            toStatus: $this->toStatus,
            changes: $this->changes,
            description: $this->description,
            ip: $this->ip,
            userAgent: $this->userAgent,
            createdAt: $this->createdAt,
        );
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function toDate(mixed $value): ?CarbonImmutable
    {
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
}
