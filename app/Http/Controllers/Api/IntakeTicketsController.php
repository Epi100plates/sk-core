<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\ReceiveIntakeTicket;
use App\Http\Controllers\Controller;
use App\Support\Intake\IntakeSignature;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * `POST /api/intake-tickets` — submissions from the public site form (2026-11-27).
 *
 * Wire contract (shared with the CMS sender):
 *   headers: X-SK-Timestamp (unix seconds), X-SK-Signature (base64url HMAC-SHA256)
 *   body:    {"number","name","phone","device","problem","source_page","created_at"}
 *   answers: {"ok":true,"status":"created"|"duplicate"} or {"ok":false,"error":...}
 *
 * Security: no session, CSRF-exempt (api group), HMAC over the exact raw body,
 * timestamp window, body-size limit and a rate limiter. Customer data (name,
 * phone, problem) is never written to the log and is never echoed in an error —
 * validation errors name the offending fields only.
 */
final class IntakeTicketsController extends Controller
{
    public function __invoke(Request $request, ReceiveIntakeTicket $receive): JsonResponse
    {
        $secret = config('intake.secret');

        if (! is_string($secret) || trim($secret) === '') {
            return self::error('integration_disabled', 'Integracja zgłoszeń jest wyłączona.', 503);
        }

        $timestamp = $request->header('X-SK-Timestamp');
        $signature = $request->header('X-SK-Signature');

        if (! is_string($timestamp) || ! ctype_digit($timestamp)
            || ! is_string($signature) || trim($signature) === '') {
            return self::error('invalid_signature', 'Brak podpisu żądania.', 401);
        }

        $skew = max(1, (int) config('intake.clock_skew', 300));
        $now = CarbonImmutable::now('UTC')->getTimestamp();

        if (abs($now - (int) $timestamp) > $skew) {
            return self::error('stale_timestamp', 'Nieaktualny znacznik czasu.', 401);
        }

        $body = $request->getContent();
        $maxBody = (int) config('intake.max_body', 20000);

        if ($body === '') {
            return self::error('invalid_json', 'Puste ciało żądania.', 400);
        }

        if ($maxBody > 0 && strlen($body) > $maxBody) {
            return self::error('payload_too_large', 'Zgłoszenie jest za duże.', 400);
        }

        if (! IntakeSignature::verify($secret, $timestamp, $body, $signature)) {
            return self::error('invalid_signature', 'Nieprawidłowy podpis żądania.', 401);
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return self::error('invalid_json', 'Ciało żądania nie jest poprawnym JSON-em.', 400);
        }

        $validator = Validator::make($decoded, [
            'number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'device' => ['nullable', 'string', 'max:100'],
            'problem' => ['required', 'string', 'max:10000'],
            'source_page' => ['nullable', 'string', 'max:255'],
            'created_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_payload',
                'fields' => array_keys($validator->errors()->toArray()),
            ], 400);
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();
        $result = $receive->handle($data);

        return response()->json([
            'ok' => true,
            'status' => $result['created'] ? 'created' : 'duplicate',
            'number' => $result['ticket']->number,
        ]);
    }

    private static function error(string $error, string $message, int $status): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => $error,
            'message' => $message,
        ], $status);
    }
}
