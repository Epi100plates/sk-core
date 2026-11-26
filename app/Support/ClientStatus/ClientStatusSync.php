<?php

declare(strict_types=1);

namespace App\Support\ClientStatus;

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceRecord;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Signed bridge to the CMS that owns the public status page (decision D19/0019).
 *
 * The workshop app stays the source of truth: after every acceptance or status
 * change one snapshot is posted to `{url}/api/service-sync.php`, the CMS answers
 * with the customer link, and the link is stored on the order. Nothing here may
 * block a workshop action — a missing secret, a timeout or a 500 is reported as
 * `ok => false` and logged without personal data (no phone, no issue text).
 */
final class ClientStatusSync
{
    /** Endpoint appended to `client-status.url`. */
    public const ENDPOINT = '/api/service-sync.php';

    /** `tickets.issue` in the CMS is shorter than the field in this app. */
    private const ISSUE_LIMIT = 2000;

    /** CMS statuses/events; anything else is rejected by the CMS whitelist. */
    public const EVENT_ACCEPTED = 'accepted';

    public const EVENT_STATUS_CHANGED = 'status_changed';

    public const EVENT_RELEASED = 'released';

    public const EVENT_CANCELLED = 'cancelled';

    public function __construct(private readonly ServiceRepository $services) {}

    /** The integration only runs with a switch, a base address and a secret. */
    public function enabled(): bool
    {
        return (bool) config('client-status.enabled', false)
            && trim((string) config('client-status.url', '')) !== ''
            && trim((string) config('client-status.secret', '')) !== '';
    }

    /**
     * Sends the current snapshot of the order and stores the returned link.
     *
     * @return array{ok: bool, url: string|null, error: string|null}
     */
    public function sync(ServiceRecord $service, string $event): array
    {
        if (! $this->enabled()) {
            // Normal state on installations without a CMS: nothing to report and
            // nothing to log on every single acceptance.
            return $this->failure('Integracja statusu klienta jest wyłączona.');
        }

        $json = json_encode(
            $this->payload($service, $event),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if ($json === false) {
            return $this->fail($service, 'Nie udało się zakodować żądania.');
        }

        $timestamp = (string) Carbon::now('UTC')->getTimestamp();
        $secret = (string) config('client-status.secret', '');
        $endpoint = rtrim(trim((string) config('client-status.url', '')), '/').self::ENDPOINT;
        $timeout = max(1, (int) config('client-status.timeout', 5));

        try {
            $response = Http::withBody($json, 'application/json')
                ->withHeaders([
                    'X-SK-Timestamp' => $timestamp,
                    'X-SK-Signature' => self::signature($secret, $timestamp, $json),
                    'Accept' => 'application/json',
                ])
                ->timeout($timeout)
                ->post($endpoint);
        } catch (ConnectionException $exception) {
            return $this->fail($service, 'Brak połączenia z CMS: '.$exception::class);
        }

        if ($response->status() !== 200) {
            return $this->fail($service, 'CMS odpowiedział kodem '.$response->status().'.');
        }

        $payload = $response->json();
        $url = is_array($payload) ? ($payload['url'] ?? null) : null;

        if (! is_array($payload) || ($payload['ok'] ?? null) !== true || ! is_string($url) || trim($url) === '') {
            return $this->fail($service, 'CMS nie zwrócił adresu strony statusu.');
        }

        $url = trim($url);

        // The record passed in comes from a finished action; reading it again keeps
        // the optimistic version current, so the link write cannot conflict (D-17).
        $current = $this->services->find($service->id) ?? $service;

        try {
            $this->services->save($current->with([
                'client_status_url' => $url,
                'client_status_synced_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
            ]));
        } catch (Throwable $exception) {
            return $this->fail($service, 'Nie udało się zapisać linku statusu: '.$exception::class);
        }

        return ['ok' => true, 'url' => $url, 'error' => null];
    }

    /**
     * `X-SK-Signature`: base64url without padding over `timestamp\nrawBody`.
     */
    public static function signature(string $secret, string $timestamp, string $body): string
    {
        $mac = hash_hmac('sha256', $timestamp."\n".$body, $secret, true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    /**
     * Exactly the ten keys the CMS accepts; anything else is a 400 unknown_field.
     *
     * @return array<string, mixed>
     */
    private function payload(ServiceRecord $service, string $event): array
    {
        $timezone = (string) config('app.display_timezone', 'Europe/Warsaw');

        return [
            'reference' => $service->reference,
            'phone' => $service->phone,
            'device' => $service->deviceModel,
            'issue' => Str::limit($service->issue, self::ISSUE_LIMIT, ''),
            'status' => $service->status->value,
            'planned_at' => $service->plannedAt->timezone($timezone)->format('Y-m-d H:i:s'),
            'accepted_at' => $service->acceptedAt->timezone($timezone)->format('Y-m-d H:i:s'),
            'express' => $service->isExpress,
            'event' => $event,
            'note' => '',
        ];
    }

    /**
     * @return array{ok: false, url: null, error: string}
     */
    private function failure(string $reason): array
    {
        return ['ok' => false, 'url' => null, 'error' => $reason];
    }

    /**
     * @return array{ok: false, url: null, error: string}
     */
    private function fail(ServiceRecord $service, string $reason): array
    {
        // Reference only: the log must never carry the phone or the issue text.
        Log::warning('client-status sync failed', [
            'reference' => $service->reference,
            'reason' => $reason,
        ]);

        return $this->failure($reason);
    }
}
