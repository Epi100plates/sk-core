<?php

declare(strict_types=1);

namespace App\Support\Intake;

/**
 * HMAC contract for submissions from the public CMS (2026-11-27).
 *
 * Sender and receiver compute the same value:
 *   base64url(HMAC-SHA256(secret, timestamp."\n".rawBody))
 * where `timestamp` is the unix time in the `X-SK-Timestamp` header and `rawBody`
 * is the exact JSON bytes that were signed. `base64url` replaces `+`/`/` with
 * `-`/`_` and drops the trailing `=`.
 */
final class IntakeSignature
{
    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return self::base64Url(hash_hmac('sha256', $timestamp."\n".$body, $secret, true));
    }

    public static function verify(string $secret, string $timestamp, string $body, string $signature): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $body), $signature);
    }

    private static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
