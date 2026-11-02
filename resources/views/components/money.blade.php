@props(['value' => '0'])

{{ \App\Domain\ValueObjects\Money::formatDecimalString(is_scalar($value) ? (string) $value : '0') }}
