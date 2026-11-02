<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\ValueObjects\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number must be a real Polish number, no matter how it is written
 * (F-07). The raw spelling is kept, only the comparable form is normalised.
 */
final class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Phone::normalize($value) === null) {
            $fail('Podaj numer telefonu w formacie np. 505 123 456 lub +48 505 123 456.');
        }
    }
}
