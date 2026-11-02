<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Monolog\LogRecord;

/**
 * Removes customer data from log records (defect D-38: the old `errors.log`
 * contained SQL dumps with phone numbers and laptop passwords).
 *
 * Two layers of protection:
 *  - keys that obviously hold secrets are replaced by "[redacted]",
 *  - every remaining string is scanned for phone numbers and masked.
 *
 * The application itself never logs a phone number or a password on purpose; this
 * processor makes sure that a future `Log::info($payload)` cannot leak them either.
 */
final class RedactSensitiveData
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'device_password',
        'haslo',
        'token',
        '_token',
        'authorization',
        'phone',
        'nr_tel',
    ];

    private const REDACTED = '[redacted]';

    private const PHONE_PATTERN = '/(?<![\d+])(?:\+?48[\s-]?)?(?:\d[\s-]?){8}\d(?![\d])/u';

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $this->scrubString($record->message);
        $context = $this->scrubArray($record->context);
        $extra = $this->scrubArray($record->extra);

        return $record->with(message: $message, context: $context, extra: $extra);
    }

    private function scrubString(string $value): string
    {
        return (string) preg_replace(self::PHONE_PATTERN, '[phone]', $value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scrubArray(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $normalizedKey = is_string($key) ? mb_strtolower($key) : $key;

            if (is_string($normalizedKey) && in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $clean[$key] = self::REDACTED;

                continue;
            }

            $clean[$key] = match (true) {
                is_array($value) => $this->scrubArray($value),
                is_string($value) => $this->scrubString($value),
                default => $value,
            };
        }

        return $clean;
    }
}
