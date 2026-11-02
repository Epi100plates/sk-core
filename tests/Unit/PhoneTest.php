<?php

declare(strict_types=1);

use App\Domain\ValueObjects\Phone;

it('normalises every spelling of the same phone number', function (string $input, string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['505 123 456', '+48505123456'],
    ['+48 505 123 456', '+48505123456'],
    ['0048505123456', '+48505123456'],
    ['48-505-123-456', '+48505123456'],
    ['(+48) 505 123 456', '+48505123456'],
    ['505123456', '+48505123456'],
]);

it('rejects values that are not a Polish phone number', function (string $input) {
    expect(Phone::normalize($input))->toBeNull();
})->with([
    [''],
    ['abc'],
    ['12345'],
    ['0505123456'],
    ['+1 555 123 4567'],
]);

it('keeps the raw spelling for display but normalises for search', function () {
    $phone = Phone::fromInput('505 123 456');

    expect($phone->raw)->toBe('505 123 456')
        ->and($phone->normalized)->toBe('+48505123456')
        ->and($phone->isValid())->toBeTrue()
        ->and($phone->display())->toBe('+48 505 123 456');
});
