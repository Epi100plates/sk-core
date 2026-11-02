<?php

declare(strict_types=1);

use App\Support\Logging\RedactLogRecords;
use App\Support\Logging\RedactSensitiveData;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;

/*
|--------------------------------------------------------------------------
| F-51 / D-38 — no customer data in log files
|--------------------------------------------------------------------------
*/

it('attaches the redaction processor to every application log channel', function (string $channel) {
    expect(config('logging.channels.'.$channel.'.tap'))->toContain(RedactLogRecords::class);

    $logger = Log::channel($channel);
    $monolog = $logger->getLogger();

    $hasProcessor = false;

    foreach ($monolog->getProcessors() as $processor) {
        if ($processor instanceof RedactSensitiveData) {
            $hasProcessor = true;
        }
    }

    expect($hasProcessor)->toBeTrue('Kanał '.$channel.' nie ma procesora maskującego dane klientów.');
})->with(['single', 'daily']);

it('masks phone numbers and passwords in log records', function () {
    $processor = new RedactSensitiveData;

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'testing',
        level: Level::Info,
        message: 'Klient 505 123 456 zgłosił usterkę',
        context: [
            'device_password' => 'tajne-haslo',
            'note' => 'kontakt +48 600 100 200',
            'model' => 'Dell Latitude',
            'nested' => ['phone' => '600 100 200', 'status' => 'pending'],
        ],
    );

    $result = $processor($record);

    expect($result->message)->toBe('Klient [phone] zgłosił usterkę')
        ->and($result->context['device_password'])->toBe('[redacted]')
        ->and($result->context['note'])->toBe('kontakt [phone]')
        ->and($result->context['model'])->toBe('Dell Latitude')
        ->and($result->context['nested'])->toBe(['phone' => '[redacted]', 'status' => 'pending']);
});

it('leaves unrelated values untouched', function () {
    $processor = new RedactSensitiveData;

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'testing',
        level: Level::Info,
        message: 'Zapisano zlecenie SRV-2026-000123',
        context: ['reference' => 'SRV-2026-000123', 'status' => 'pending'],
    );

    $result = $processor($record);

    expect($result->message)->toBe('Zapisano zlecenie SRV-2026-000123')
        ->and($result->context['reference'])->toBe('SRV-2026-000123')
        ->and($result->context['status'])->toBe('pending');
});
