<?php

// ABOUTME: Unit tests for the Money value object - minor-unit amount plus Currency.
// ABOUTME: Verifies construction, same-currency arithmetic, equality, and per-currency formatting.

declare(strict_types=1);

use App\Enum\Currency;
use App\ValueObject\Money;

test('exposes its minor amount and currency', function (): void {
    $money = new Money(1599, Currency::USD);

    expect($money->minorAmount)->toBe(1599)
        ->and($money->currency)->toBe(Currency::USD)
    ;
});

test('adds two amounts of the same currency without mutating the originals', function (): void {
    $a = new Money(1599, Currency::USD);
    $b = new Money(401, Currency::USD);

    $sum = $a->add($b);

    expect($sum->minorAmount)->toBe(2000)
        ->and($sum->currency)->toBe(Currency::USD)
        ->and($a->minorAmount)->toBe(1599)
    ;
});

test('refuses to add across currencies', function (): void {
    (new Money(1000, Currency::USD))->add(new Money(1000, Currency::EUR));
})->throws(Assert\InvalidArgumentException::class);

test('equality compares amount and currency', function (): void {
    expect((new Money(1599, Currency::USD))->equals(new Money(1599, Currency::USD)))->toBeTrue()
        ->and((new Money(1599, Currency::USD))->equals(new Money(1599, Currency::EUR)))->toBeFalse()
        ->and((new Money(1599, Currency::USD))->equals(new Money(1600, Currency::USD)))->toBeFalse()
    ;
});

test('formats with the currency symbol and its fraction digits', function (): void {
    expect((new Money(1599, Currency::USD))->format())->toBe('$15.99')
        ->and((new Money(2000, Currency::JPY))->format())->toBe('¥2,000')
        ->and((new Money(1599, Currency::EUR))->format())->toBe('€15.99')
    ;
});
