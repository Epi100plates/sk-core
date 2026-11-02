<?php

declare(strict_types=1);

namespace App\Support\Crypto;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wraps Laravel's encrypter for the device password of a customer laptop.
 *
 * MySQL uses the `encrypted` cast; the JSON store uses this helper so a stolen
 * `services.json` file does not expose passwords in plain text either. The
 * owner decided the value is visible in the UI, but writing it in clear text to
 * disk would be a gratuitous risk (defect D-09).
 */
final class SecretBox
{
    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        return Crypt::encryptString($plaintext);
    }

    public function decrypt(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        try {
            return Crypt::decryptString($ciphertext);
        } catch (Throwable) {
            // A value written by an older version or with a rotated APP_KEY:
            // keep it visible instead of losing the customer's password, but
            // never leave the problem silent.
            Log::warning('Nie można odszyfrować hasła urządzenia — wartość pozostawiona bez zmian.');

            return $ciphertext;
        }
    }
}
