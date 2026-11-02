<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Money is stored as a decimal string with two decimals ("12.50"), exactly the
 * way it lands in DECIMAL(10,2) or in the JSON files. Internally arithmetic is
 * done in grosze so nothing is lost, which closes defect D-51 ("199,99" was
 * saved as "199.00") and D-23 (rounded grosze on mobile).
 */
final class Money
{
    private function __construct(public readonly int $minor) {}

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * @throws InvalidArgumentException when the input is not a number
     */
    public static function fromInput(string|int|float|null $value): self
    {
        $parsed = self::tryFromInput($value);

        if ($parsed === null) {
            throw new InvalidArgumentException('Wartość nie jest poprawną kwotą.');
        }

        return $parsed;
    }

    public static function tryFromInput(string|int|float|null $value): ?self
    {
        if ($value === null) {
            return new self(0);
        }

        if (is_int($value)) {
            return new self($value * 100);
        }

        if (is_float($value)) {
            return new self((int) round($value * 100));
        }

        $clean = trim($value);

        if ($clean === '') {
            return new self(0);
        }

        $clean = str_replace(["\u{00A0}", ' ', 'zł', 'PLN'], '', $clean);
        $clean = str_replace(',', '.', $clean);

        if (! is_numeric($clean)) {
            return null;
        }

        return new self((int) round((float) $clean * 100));
    }

    public static function fromMinor(int $minor): self
    {
        return new self($minor);
    }

    public static function fromDecimalString(string $value): self
    {
        return self::tryFromInput($value) ?? self::zero();
    }

    /** Value for storage: always dot separated, two decimals. */
    public function toDecimalString(): string
    {
        return number_format($this->minor / 100, 2, '.', '');
    }

    /** Value for the UI: "12,50 zł". */
    public function format(): string
    {
        return self::formatMinor($this->minor);
    }

    public static function formatMinor(int $minor): string
    {
        return number_format($minor / 100, 2, ',', ' ').' zł';
    }

    public static function formatDecimalString(string $value): string
    {
        return self::fromDecimalString($value)->format();
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }
}
