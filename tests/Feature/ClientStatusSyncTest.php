<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\Role;
use App\Support\ClientStatus\ClientStatusSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| D19/0019 — signed status sync with the CMS (no real request ever leaves)
|--------------------------------------------------------------------------
|
| The CMS contract: POST {url}/api/service-sync.php with exactly ten JSON keys,
| `X-SK-Timestamp` (unix) and `X-SK-Signature` = base64url(HMAC-SHA256(secret,
| timestamp."\n".rawBody)). The app stores the returned link; any failure is a
| warning, never an exception that could lose an accepted order.
|
*/

const CLIENT_STATUS_SECRET = 'test-secret-not-a-real-one';

function enableClientStatus(): void
{
    config([
        'client-status.enabled' => true,
        'client-status.url' => 'https://cms.test',
        'client-status.secret' => CLIENT_STATUS_SECRET,
        'client-status.timeout' => 2,
    ]);
}

function clientStatusSuccess(string $url = 'https://sk.mati-it.pl/s/abc123'): void
{
    Http::fake(['*' => Http::response(['ok' => true, 'url' => $url], 200)]);
}

/** The request the app sent, decoded and with case-insensitive headers. */
function sentClientStatusRequest(): array
{
    $sent = Http::recorded();

    expect($sent)->not->toBeEmpty('Aplikacja nie wysłała żadnego żądania do CMS.');

    [$request] = $sent[0];

    /** @var array<string, list<string>> $headers */
    $headers = array_change_key_case($request->headers(), CASE_LOWER);

    $body = $request->body();

    return [
        'url' => $request->url(),
        'method' => $request->method(),
        'headers' => $headers,
        'body' => $body,
        'payload' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
    ];
}

function expectedSignature(string $timestamp, string $body): string
{
    $mac = hash_hmac('sha256', $timestamp."\n".$body, CLIENT_STATUS_SECRET, true);

    return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
}

it('sends the accepted order with a valid signature and stores the returned link', function (string $store) {
    usingStore($this, $store);
    enableClientStatus();
    clientStatusSuccess();

    $this->travelTo(CarbonImmutable::parse('2026-11-26 10:15:00', 'Europe/Warsaw'));

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => str_repeat('x', 2500),
        'is_express' => '1',
        'planned_at' => '2026-11-30T15:00',
    ])->assertRedirect();

    $service = app(ServiceRepository::class)->all()[0];

    expect($service->clientStatusUrl)->toBe('https://sk.mati-it.pl/s/abc123')
        ->and($service->clientStatusSyncedAt)->not->toBeNull()
        ->and($service->clientStatusSyncedAt?->utc()->format('Y-m-d H:i'))->toBe('2026-11-26 09:15');

    $sent = sentClientStatusRequest();
    $timestamp = $sent['headers']['x-sk-timestamp'][0] ?? '';

    expect($sent['method'])->toBe('POST')
        ->and($sent['url'])->toBe('https://cms.test/api/service-sync.php')
        ->and($timestamp)->toMatch('/^\d{10}$/')
        // 10:15 in Warsaw on 2026-11-26 is 09:15 UTC.
        ->and((int) $timestamp)->toBe(CarbonImmutable::parse('2026-11-26 09:15:00', 'UTC')->getTimestamp())
        ->and($sent['headers']['x-sk-signature'][0] ?? null)->toBe(expectedSignature($timestamp, $sent['body']))
        ->and($sent['headers']['accept'][0] ?? null)->toBe('application/json')
        ->and($sent['headers']['content-type'][0] ?? null)->toContain('application/json');

    // Exactly the keys the CMS whitelists — one extra key is a 400 unknown_field.
    expect(array_keys($sent['payload']))->toBe([
        'reference', 'phone', 'device', 'issue', 'status',
        'planned_at', 'accepted_at', 'express', 'event', 'note',
    ]);

    expect($sent['payload']['reference'])->toBe($service->reference)
        ->and($sent['payload']['phone'])->toBe('505 123 456')
        ->and($sent['payload']['device'])->toBe('Dell Latitude 5420')
        ->and($sent['payload']['issue'])->toHaveLength(2000)
        ->and($sent['payload']['status'])->toBe('pending')
        ->and($sent['payload']['planned_at'])->toBe('2026-11-30 15:00:00')
        ->and($sent['payload']['accepted_at'])->toBe('2026-11-26 10:15:00')
        ->and($sent['payload']['express'])->toBeTrue()
        ->and($sent['payload']['event'])->toBe(ClientStatusSync::EVENT_ACCEPTED)
        ->and($sent['payload']['note'])->toBe('');
})->with('stores');

it('keeps the order and warns when the CMS answers with an error', function (string $store) {
    usingStore($this, $store);
    enableClientStatus();
    Http::fake(static fn () => Http::response('', 500));

    $user = $this->createUser('jan', Role::Employee);

    $response = $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Tajna usterka klienta.',
        'planned_at' => '2026-11-30T15:00',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');

    $service = app(ServiceRepository::class)->all()[0];

    expect($service->clientStatusUrl)->toBeNull()
        ->and($service->clientStatusSyncedAt)->toBeNull();
})->with('stores');

it('survives a connection failure and never logs personal data', function (string $store) {
    usingStore($this, $store);
    enableClientStatus();

    Http::fake(static fn () => throw new ConnectionException('cURL error 28: timeout'));

    /** @var list<object{message: string, context: array<string, mixed>}> $records */
    $records = [];
    Log::listen(static function (object $record) use (&$records): void {
        $records[] = $record;
    });

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Tajna usterka klienta.',
        'planned_at' => '2026-11-30T15:00',
    ])->assertRedirect();

    $service = app(ServiceRepository::class)->all()[0];

    expect($service->clientStatusUrl)->toBeNull();

    $warnings = array_values(array_filter(
        $records,
        static fn (object $record): bool => $record->message === 'client-status sync failed',
    ));

    expect($warnings)->toHaveCount(1)
        ->and(array_keys($warnings[0]->context))->toBe(['reference', 'reason'])
        ->and($warnings[0]->context['reference'])->toBe($service->reference)
        ->and(json_encode($warnings[0]->context, JSON_THROW_ON_ERROR))
        ->not->toContain('505');
})->with('stores');

it('sends nothing at all while the integration is off', function (string $store) {
    usingStore($this, $store);
    config([
        'client-status.enabled' => false,
        'client-status.secret' => '',
    ]);
    Http::fake();

    $user = $this->createUser('jan', Role::Employee);

    $response = $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Nie ładuje się.',
        'planned_at' => '2026-11-30T15:00',
    ]);

    $response->assertRedirect();
    $response->assertSessionMissing('warning');

    expect(app(ServiceRepository::class)->all()[0]->clientStatusUrl)->toBeNull();

    Http::assertNothingSent();
})->with('stores');

it('re-creates the link from the order card', function (string $store) {
    usingStore($this, $store);
    enableClientStatus();
    clientStatusSuccess('https://sk.mati-it.pl/s/retry');

    $user = $this->createUser('jan', Role::Employee);
    // Accepted through the action, so no link exists yet (CMS was down).
    $service = $this->acceptService($user);

    expect($service->clientStatusUrl)->toBeNull();

    $response = $this->actingAsUser($user)->post('/services/'.$service->id.'/client-status');

    $response->assertRedirect(route('services.show', $service));
    $response->assertSessionHas('status');

    $fresh = app(ServiceRepository::class)->find($service->id);

    expect($fresh?->clientStatusUrl)->toBe('https://sk.mati-it.pl/s/retry')
        ->and($fresh?->clientStatusSyncedAt)->not->toBeNull()
        ->and(sentClientStatusRequest()['payload']['event'])->toBe(ClientStatusSync::EVENT_STATUS_CHANGED);
})->with('stores');

it('tells the customer page about a stage change, a release and a cancellation', function (string $store) {
    usingStore($this, $store);
    enableClientStatus();
    clientStatusSuccess();

    $user = $this->createUser('jan', Role::Employee);

    $first = $this->acceptService($user);

    $this->actingAsUser($user)
        ->postJson('/services/'.$first->id.'/advance')
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    $this->actingAsUser($user)
        ->post('/services/'.$first->id.'/release', [
            'released_at' => '2026-11-20T12:30',
            'price' => '100',
            'cost' => '30',
        ])
        ->assertRedirect();

    $second = $this->acceptService($user);

    $this->actingAsUser($user)
        ->post('/services/'.$second->id.'/cancel', ['cancellation_reason' => 'Klient zrezygnował.'])
        ->assertRedirect();

    $sentEvents = [];
    $sentStatuses = [];

    foreach (Http::recorded() as [$request]) {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
        $sentEvents[] = $payload['event'];
        $sentStatuses[] = $payload['status'];
    }

    expect($sentEvents)->toBe([
        ClientStatusSync::EVENT_STATUS_CHANGED,
        ClientStatusSync::EVENT_RELEASED,
        ClientStatusSync::EVENT_CANCELLED,
    ])->and($sentStatuses)->toBe(['in_progress', 'released', 'cancelled']);
})->with('stores');

it('creates the missing links with the artisan command', function (string $store) {
    usingStore($this, $store);
    enableClientStatus();
    clientStatusSuccess('https://sk.mati-it.pl/s/command');

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user);
    $this->acceptService($user);

    $this->artisan('services:sync-status')
        ->expectsOutputToContain('Wysłano: 2, pominięto: 0, błędy: 0')
        ->assertExitCode(0);

    $services = app(ServiceRepository::class)->all();

    expect($services)->toHaveCount(2);

    foreach ($services as $service) {
        /** @var ServiceRecord $service */
        expect($service->clientStatusUrl)->toBe('https://sk.mati-it.pl/s/command');
    }

    // A second run has nothing to do: orders that already have a link are skipped.
    $this->artisan('services:sync-status')
        ->expectsOutputToContain('Wysłano: 0, pominięto: 2, błędy: 0')
        ->assertExitCode(0);
})->with('stores');

it('does nothing at all when the command runs without configuration', function (string $store) {
    usingStore($this, $store);
    config([
        'client-status.enabled' => false,
        'client-status.secret' => '',
    ]);
    Http::fake();

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user);

    $this->artisan('services:sync-status')
        ->expectsOutputToContain('jest wyłączona')
        ->assertExitCode(0);

    Http::assertNothingSent();
})->with('stores');
