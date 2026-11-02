<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Phone handling for feature F-07 / defect D-20.
 *
 * The raw value typed by the employee is kept untouched so the printed receipt
 * looks the way the customer wrote it, while `normalized` gives one comparable
 * form (E.164, e.g. +48505123456) used for searching.
 */
final class Phone
{
    private function __construct(
        public readonly string $raw,
        public readonly ?string $normalized,
    ) {}

    public static function fromInput(?string $value): self
    {
        $raw = trim((string) $value);

        return new self($raw, self::normalize($raw));
    }

    /**
     * Accepts "505 123 456", "+48 505 123 456", "0048505123456",
     * "48-505-123-456", "(+48) 505 123 456" and returns "+48505123456".
     * Anything that does not end up as a 9 digit Polish number returns null.
     */
    public static function normalize(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === null || $digits === '') {
            return null;
        }

        if ($hasPlus && str_starts_with($digits, '48')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0048')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '48') && strlen($digits) === 11) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) !== 9 || str_starts_with($digits, '0')) {
            return null;
        }

        return '+48'.$digits;
    }

    public function isValid(): bool
    {
        return $this->normalized !== null;
    }

    public function display(): string
    {
        if ($this->normalized === null) {
            return $this->raw;
        }

        $digits = substr($this->normalized, 3);

        return sprintf('+48 %s %s %s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 3));
    }
}
