<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\UserRepository;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Logins are unique inside the active store — checked through the repository so
 * it works the same on JSON files and on MySQL.
 */
final class UniqueLogin implements ValidationRule
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ?int $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $existing = $this->users->findByLogin($value);

        if ($existing !== null && $existing->id !== $this->ignoreUserId) {
            $fail('Ten login jest już zajęty.');
        }
    }
}
