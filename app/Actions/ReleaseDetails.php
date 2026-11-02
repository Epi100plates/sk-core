<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * Data of the release form (F-39). `released_at` comes from the form with the
 * default "now" — the old application ignored it and always used the server time
 * (defects D-11, D-56).
 */
final class ReleaseDetails
{
    public function __construct(
        public readonly CarbonImmutable $releasedAt,
        public readonly string $price,
        public readonly string $cost,
        public readonly ?string $notes,
        public readonly ?string $sealNumber,
        public readonly ?string $devicePassword,
        public readonly ?string $serialNumber,
        public readonly bool $printProtocol,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $price = $data['price'] ?? '0';
        $cost = $data['cost'] ?? '0';

        return new self(
            releasedAt: self::date($data['released_at'] ?? null),
            price: Money::fromInput(is_scalar($price) ? (string) $price : '0')->toDecimalString(),
            cost: Money::fromInput(is_scalar($cost) ? (string) $cost : '0')->toDecimalString(),
            notes: self::nullable($data['notes'] ?? null),
            sealNumber: self::nullable($data['seal_number'] ?? null),
            devicePassword: self::nullable($data['device_password'] ?? null),
            serialNumber: self::nullable($data['serial_number'] ?? null),
            printProtocol: in_array($data['print_protocol'] ?? false, [true, 1, '1', 'on', 'yes'], true),
        );
    }

    private static function nullable(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function date(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value, (string) config('app.display_timezone'))->utc();
        }

        return CarbonImmutable::now('UTC');
    }
}
