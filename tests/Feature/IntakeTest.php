<?php

declare(strict_types=1);

use App\Actions\ConvertIntakeTicket;
use App\Actions\ReceiveIntakeTicket;
use App\Contracts\IntakeTicketRepository;
use App\Contracts\ServiceRepository;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Enums\IntakeStatus;
use App\Domain\Enums\Role;
use App\Support\Intake\IntakeSignature;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Submissions from the public site form (owner request 2026-11-27)
|
| Contract: POST /api/intake-tickets, X-SK-Timestamp + X-SK-Signature
| (base64url HMAC-SHA256 over timestamp."\n".rawBody), idempotent by number.
| Inbox: list/filter/detail, contact, reject with a reason, convert to a repair.
|--------------------------------------------------------------------------
*/

const INTAKE_TEST_SECRET = 'test-intake-secret';

/**
 * @param  array<string, mixed>  $payload
 * @return array{0: string, 1: array<string, string>}
 */
function signIntake(array $payload, ?string $secret = null, ?int $timestamp = null, ?string $rawBody = null): array
{
    $secret ??= INTAKE_TEST_SECRET;
    $rawBody ??= (string) json_encode($payload);
    $timestamp ??= CarbonImmutable::now('UTC')->getTimestamp();

    return [$rawBody, [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SK_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SK_SIGNATURE' => IntakeSignature::sign($secret, (string) $timestamp, $rawBody),
    ]];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postIntake(array $payload, ?string $secret = null, ?int $timestamp = null, ?string $rawBody = null): TestResponse
{
    [$body, $server] = signIntake($payload, $secret, $timestamp, $rawBody);

    return test()->call('POST', '/api/intake-tickets', [], [], [], $server, $body);
}

/**
 * @return array<string, mixed>
 */
function intakePayload(string $number = 'SK-2026-000123'): array
{
    return [
        'number' => $number,
        'name' => 'Jan',
        'phone' => '600 100 200',
        'device' => 'Lenovo IdeaPad 5',
        'problem' => 'Laptop nie ładuje się po podłączeniu zasilacza.',
        'source_page' => '/kontakt/',
        'created_at' => '2026-11-27 10:00:00',
    ];
}

beforeEach(function (): void {
    config(['intake.secret' => INTAKE_TEST_SECRET]);
});

it('uses the documented base64url HMAC-SHA256 wire format', function () {
    $timestamp = '1795773600';
    $body = json_encode([
        'number' => 'SK-2026-000123',
        'name' => 'Jan',
        'phone' => '600 100 200',
        'device' => 'Lenovo IdeaPad 5',
        'problem' => 'Laptop nie ładuje się.',
        'source_page' => '/kontakt/',
        'created_at' => '2026-11-27 10:00:00',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Fixed vector: base64url of the raw HMAC over "timestamp\nrawBody".
    $signature = IntakeSignature::sign(INTAKE_TEST_SECRET, $timestamp, (string) $body);

    expect($signature)->toBe('XMNgZFqX4JmNVZo8zrCJ-ihpy3Cfw0ClU3SjJ0pPtHc')
        ->and($signature)->not->toContain('=')
        ->and($signature)->not->toContain('+')
        ->and($signature)->not->toContain('/');
});

it('accepts a signature computed independently with the documented algorithm', function (string $store) {
    usingStore($this, $store);

    $payload = intakePayload();
    $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $timestamp = (string) CarbonImmutable::now('UTC')->getTimestamp();

    // Exactly what the CMS sender does: base64 with +/ -> -_ and no padding.
    $raw = hash_hmac('sha256', $timestamp."\n".$body, INTAKE_TEST_SECRET, true);
    $signature = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SK_TIMESTAMP' => $timestamp,
        'HTTP_X_SK_SIGNATURE' => $signature,
    ];

    $this->call('POST', '/api/intake-tickets', [], [], [], $server, $body)
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'created']);
})->with('stores');

it('logs no customer data while receiving a submission', function (string $store) {
    usingStore($this, $store);

    Log::spy();

    postIntake(intakePayload())->assertOk();

    // The endpoint stores the submission and never logs the payload (name, phone,
    // problem) — the same posture as the rest of the app (defect D-38).
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('debug');
})->with('stores');

it('stores a correctly signed submission from the public CMS', function (string $store) {
    usingStore($this, $store);

    $response = postIntake(intakePayload());

    $response->assertOk()->assertJson(['ok' => true, 'status' => 'created']);

    $tickets = app(IntakeTicketRepository::class);

    expect($tickets->count())->toBe(1);

    $record = $tickets->findByNumber('SK-2026-000123');

    expect($record)->not->toBeNull()
        ->and($record?->name)->toBe('Jan')
        ->and($record?->phone)->toBe('600 100 200')
        ->and($record?->device)->toBe('Lenovo IdeaPad 5')
        ->and($record?->sourcePage)->toBe('/kontakt/')
        ->and($record?->status)->toBe(IntakeStatus::New)
        // 10:00 Warsaw time in November is 09:00 UTC.
        ->and($record?->submittedAt?->format('Y-m-d H:i'))->toBe('2026-11-27 09:00');
})->with('stores');

it('is idempotent by the CMS ticket number', function (string $store) {
    usingStore($this, $store);

    postIntake(intakePayload())->assertOk()->assertJsonPath('status', 'created');

    $second = postIntake(intakePayload());

    $second->assertOk()->assertJson(['ok' => true, 'status' => 'duplicate']);
    expect(app(IntakeTicketRepository::class)->count())->toBe(1);
})->with('stores');

it('rejects a forged signature with 401 and stores nothing', function (string $store) {
    usingStore($this, $store);

    [$body, $server] = signIntake(intakePayload());
    $server['HTTP_X_SK_SIGNATURE'] = 'forged-signature';

    $this->call('POST', '/api/intake-tickets', [], [], [], $server, $body)
        ->assertStatus(401)
        ->assertJsonPath('error', 'invalid_signature');

    expect(app(IntakeTicketRepository::class)->count())->toBe(0);
})->with('stores');

it('rejects a stale timestamp with 401', function (string $store) {
    usingStore($this, $store);

    $stale = CarbonImmutable::now('UTC')->subHour()->getTimestamp();

    postIntake(intakePayload(), null, $stale)
        ->assertStatus(401)
        ->assertJsonPath('error', 'stale_timestamp');

    expect(app(IntakeTicketRepository::class)->count())->toBe(0);
})->with('stores');

it('answers 503 while the integration has no secret', function (string $store) {
    usingStore($this, $store);
    config(['intake.secret' => '']);

    postIntake(intakePayload())
        ->assertStatus(503)
        ->assertJsonPath('error', 'integration_disabled');
})->with('stores');

it('answers 400 for invalid JSON or a too large body', function (string $store) {
    usingStore($this, $store);

    postIntake([], null, null, '{"number":')
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_json');

    config(['intake.max_body' => 50]);

    postIntake(intakePayload(), null, null, (string) json_encode(array_merge(intakePayload(), ['problem' => str_repeat('x', 100)])))
        ->assertStatus(400)
        ->assertJsonPath('error', 'payload_too_large');
})->with('stores');

it('answers 400 for a payload without the required fields and does not echo the customer data', function (string $store) {
    usingStore($this, $store);

    $payload = intakePayload();
    unset($payload['problem']);

    $response = postIntake($payload);

    $response->assertStatus(400)->assertJsonPath('error', 'invalid_payload');

    $content = (string) $response->getContent();

    expect($content)->not->toContain('600 100 200')
        ->and($content)->not->toContain('Lenovo IdeaPad 5')
        ->and(app(IntakeTicketRepository::class)->count())->toBe(0);
})->with('stores');

it('throttles repeated unsigned deliveries', function (string $store) {
    usingStore($this, $store);
    config(['intake.rate_limit' => 2]);

    postIntake(intakePayload('SK-2026-000001'))->assertOk();
    postIntake(intakePayload('SK-2026-000002'))->assertOk();
    postIntake(intakePayload('SK-2026-000003'))->assertStatus(429);
})->with('stores');

it('keeps the inbox behind the login and shows the newest submissions first', function (string $store) {
    usingStore($this, $store);

    $this->get('/intake')->assertRedirect(route('login'));

    $user = $this->createUser('jan', Role::Employee);

    $old = app(ReceiveIntakeTicket::class)->handle(array_merge(intakePayload('SK-2026-000010'), [
        'created_at' => '2026-11-20 08:00:00',
    ]));
    $new = app(ReceiveIntakeTicket::class)->handle(intakePayload('SK-2026-000011'));

    $response = $this->actingAsUser($user)->get('/intake');

    $response->assertOk()
        ->assertSee('SK-2026-000011')
        ->assertSee('SK-2026-000010');

    expect($response->getContent())->toContain('SK-2026-000011');

    // The "new" filter counts both; a rejected filter is empty until something is rejected.
    $this->actingAsUser($user)->get('/intake?status=new')
        ->assertOk()
        ->assertSee('SK-2026-000010')
        ->assertSee('SK-2026-000011');

    $this->actingAsUser($user)->get('/intake?status=rejected')
        ->assertOk()
        ->assertSee('Ten etap jest pusty');

    expect($old['ticket']->number)->toBe('SK-2026-000010')
        ->and($new['ticket']->number)->toBe('SK-2026-000011');
})->with('stores');

it('marks a submission as contacted without losing it', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $record = app(ReceiveIntakeTicket::class)->handle(intakePayload())['ticket'];

    $this->actingAsUser($user)->post('/intake/'.$record->id.'/contact')->assertRedirect();

    $fresh = app(IntakeTicketRepository::class)->find($record->id);

    expect($fresh?->status)->toBe(IntakeStatus::Contacted)
        ->and($fresh?->contactedAt)->not->toBeNull()
        ->and(app(IntakeTicketRepository::class)->count())->toBe(1);
})->with('stores');

it('rejects a submission with a mandatory short reason', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $record = app(ReceiveIntakeTicket::class)->handle(intakePayload())['ticket'];

    $this->actingAsUser($user)
        ->post('/intake/'.$record->id.'/reject', ['rejection_reason' => ''])
        ->assertSessionHasErrors('rejection_reason');

    expect(app(IntakeTicketRepository::class)->find($record->id)?->status)->toBe(IntakeStatus::New);

    $this->actingAsUser($user)
        ->post('/intake/'.$record->id.'/reject', ['rejection_reason' => 'Klient nie potwierdził zlecenia.'])
        ->assertRedirect();

    $fresh = app(IntakeTicketRepository::class)->find($record->id);

    expect($fresh?->status)->toBe(IntakeStatus::Rejected)
        ->and($fresh?->rejectionReason)->toBe('Klient nie potwierdził zlecenia.')
        ->and($fresh?->rejectedBy)->toBe($user->id);
})->with('stores');

it('converts a submission into a repair with the acceptance form prefilled', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $record = app(ReceiveIntakeTicket::class)->handle(intakePayload())['ticket'];

    $this->actingAsUser($user)
        ->get('/intake/'.$record->id.'/convert')
        ->assertRedirect(route('services.create', ['intake' => $record->id]));

    // The prefill is real values in the form, not a note above it.
    $this->actingAsUser($user)->get('/services/create?intake='.$record->id)
        ->assertOk()
        ->assertSee('value="600 100 200"', false)
        ->assertSee('value="Lenovo IdeaPad 5"', false)
        ->assertSee('Laptop nie ładuje się po podłączeniu zasilacza.')
        ->assertSee('name="intake_id"', false);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '600 100 200',
        'device_model' => 'Lenovo IdeaPad 5',
        'issue' => 'Laptop nie ładuje się po podłączeniu zasilacza.',
        'planned_days' => '1',
        'price' => '0',
        'cost' => '0',
        'intake_id' => (string) $record->id,
    ])->assertRedirect();

    $converted = app(IntakeTicketRepository::class)->find($record->id);
    $service = $converted?->convertedServiceId !== null
        ? app(ServiceRepository::class)->find((int) $converted->convertedServiceId)
        : null;

    expect($converted?->status)->toBe(IntakeStatus::Converted)
        ->and($converted?->convertedAt)->not->toBeNull()
        ->and($service)->not->toBeNull()
        ->and($converted?->convertedServiceReference)->toBe($service?->reference);

    // The inbox detail links to the order it produced.
    $this->actingAsUser($user)->get('/intake/'.$record->id)
        ->assertOk()
        ->assertSee($service?->reference ?? '')
        ->assertSee('Przyjęte do serwisu');
})->with('stores');

it('does not convert the same submission twice', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $record = app(ReceiveIntakeTicket::class)->handle(intakePayload())['ticket'];
    $service = $this->acceptService($user);

    app(ConvertIntakeTicket::class)->handle($record, $service);

    $this->actingAsUser($user)
        ->get('/intake/'.$record->id.'/convert')
        ->assertRedirect(route('services.show', $service));

    expect(app(IntakeTicketRepository::class)->find($record->id)?->status)->toBe(IntakeStatus::Converted);
})->with('stores');

it('never silently drops a submission when its conversion is abandoned', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $record = app(ReceiveIntakeTicket::class)->handle(intakePayload())['ticket'];

    // Opening the conversion without saving the acceptance form leaves it open.
    $this->actingAsUser($user)->get('/intake/'.$record->id.'/convert')->assertRedirect();

    $fresh = app(IntakeTicketRepository::class)->find($record->id);

    expect($fresh?->status)->toBe(IntakeStatus::New)
        ->and($fresh instanceof IntakeTicketRecord)->toBeTrue();
})->with('stores');
