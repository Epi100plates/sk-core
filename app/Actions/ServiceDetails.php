<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Phone;
use Carbon\CarbonImmutable;

/**
 * Validated data of the acceptance form (F-07…F-18). Built from a FormRequest,
 * everything is already validated and normalised when this object exists.
 */
final class ServiceDetails
{
    public function __construct(
        public readonly string $phone,
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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $phone = Phone::fromInput(self::string($data, 'phone'));

        return new self(
            phone: $phone->raw,
            deviceModel: self::string($data, 'device_model'),
            serialNumber: self::nullable($data, 'serial_number'),
            issue: self::string($data, 'issue'),
            devicePassword: self::nullable($data, 'device_password'),
            notes: self::nullable($data, 'notes'),
            isExpress: self::bool($data, 'is_express'),
            plannedAt: self::date($data, 'planned_at'),
            price: Money::fromInput(self::raw($data, 'price', '0'))->toDecimalString(),
            cost: Money::fromInput(self::raw($data, 'cost', '0'))->toDecimalString(),
            sealNumber: self::nullable($data, 'seal_number'),
        );
    }

    /**
     * Partial update payload: only the keys present in the request change
     * (defect D-47 — a missing `cost` field must not zero the cost).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function changesFromArray(array $data): array
    {
        $changes = [];

        if (array_key_exists('phone', $data)) {
            $changes['phone'] = self::string($data, 'phone');
            $changes['phone_normalized'] = Phone::normalize(self::string($data, 'phone'));
        }

        foreach (['device_model', 'issue', 'serial_number', 'device_password', 'notes', 'seal_number'] as $key) {
            if (array_key_exists($key, $data)) {
                $changes[$key] = self::nullable($data, $key);
            }
        }

        if (array_key_exists('is_express', $data)) {
            $changes['is_express'] = self::bool($data, 'is_express');
        }

        if (array_key_exists('planned_at', $data)) {
            $changes['planned_at'] = self::date($data, 'planned_at')->format('Y-m-d H:i:s');
        }

        foreach (['price', 'cost'] as $moneyKey) {
            if (array_key_exists($moneyKey, $data)) {
                $changes[$moneyKey] = Money::fromInput(self::raw($data, $moneyKey, '0'))->toDecimalString();
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function raw(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        return trim(self::raw($data, $key, ''));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullable(array $data, string $key): ?string
    {
        $value = self::string($data, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'on', 'yes'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function date(array $data, string $key): CarbonImmutable
    {
        $value = $data[$key] ?? null;

        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value, (string) config('app.display_timezone'))->utc();
        }

        return CarbonImmutable::now('UTC');
    }
}
