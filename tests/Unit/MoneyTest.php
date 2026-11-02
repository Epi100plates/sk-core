<?php

declare(strict_types=1);

use App\Domain\ValueObjects\Money;

it('accepts a comma as the decimal separator (defect D-51)', function () {
    expect(Money::fromInput('199,99')->toDecimalString())->toBe('199.99')
        ->and(Money::fromInput('199.99')->toDecimalString())->toBe('199.99')
        ->and(Money::fromInput(' 1 234,50 zł')->toDecimalString())->toBe('1234.50')
        ->and(Money::fromInput('12,5')->toDecimalString())->toBe('12.50');
});

it('keeps grosze when the value comes as a float', function () {
    expect(Money::fromInput(199.99)->toDecimalString())->toBe('199.99')
        ->and(Money::fromInput(0.1)->toDecimalString())->toBe('0.10');
});

it('formats amounts the Polish way', function () {
    expect(Money::fromInput('1234,5')->format())->toBe('1 234,50 zł')
        ->and(Money::fromDecimalString('0.00')->format())->toBe('0,00 zł');
});

it('rejects a value that is not a number', function () {
    expect(Money::tryFromInput('abc'))->toBeNull();
});

it('treats an empty value as zero', function () {
    expect(Money::fromInput('')->toDecimalString())->toBe('0.00')
        ->and(Money::fromInput(null)->toDecimalString())->toBe('0.00');
});
