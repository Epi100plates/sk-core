<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\IntakeTicketRepository;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Enums\IntakeStatus;
use Carbon\CarbonImmutable;

/**
 * Stores one submission delivered by the public CMS over the signed endpoint.
 *
 * Idempotency lives here: the CMS ticket number is the key, so a retried delivery
 * returns the already stored ticket with `created = false` instead of producing a
 * second row. The payload was validated by the controller; this action only maps
 * fields and applies the timestamps.
 */
final class ReceiveIntakeTicket
{
    public function __construct(private readonly IntakeTicketRepository $tickets) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ticket: IntakeTicketRecord, created: bool}
     */
    public function handle(array $payload): array
    {
        $number = trim((string) ($payload['number'] ?? ''));

        $existing = $this->tickets->findByNumber($number);

        if ($existing instanceof IntakeTicketRecord) {
            return ['ticket' => $existing, 'created' => false];
        }

        $submittedAt = self::submittedAt($payload['created_at'] ?? null);

        $stored = $this->tickets->save(IntakeTicketRecord::fromArray([
            'number' => $number,
            'name' => self::text($payload['name'] ?? null),
            'phone' => self::text($payload['phone'] ?? null),
            'device' => self::text($payload['device'] ?? null),
            'problem' => self::text($payload['problem'] ?? null),
            'source_page' => self::text($payload['source_page'] ?? null),
            'submitted_at' => $submittedAt->format('Y-m-d H:i:s'),
            'status' => IntakeStatus::New->value,
        ]));

        return ['ticket' => $stored, 'created' => true];
    }

    /**
     * The CMS sends its local wall clock. Interpreting it in the display timezone
     * (Europe/Warsaw) and storing UTC keeps the inbox times consistent with every
     * other date in this application.
     */
    private static function submittedAt(mixed $value): CarbonImmutable
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value, (string) config('app.display_timezone'))->utc();
            } catch (\Throwable) {
                // fall through to "now"
            }
        }

        return CarbonImmutable::now('UTC');
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
