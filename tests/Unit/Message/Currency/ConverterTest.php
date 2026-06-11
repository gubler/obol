<?php

// ABOUTME: Unit tests for the currency Converter - identity, direct, cross-pivot, and missing-rate paths.
// ABOUTME: Mocks the rate repository; rates are EUR-pivot (units of the currency per 1 EUR).

declare(strict_types=1);

use App\Enum\Currency;
use App\Message\Currency\Converter;
use App\Repository\ExchangeRateRepository;
use App\ValueObject\Money;

test('returns the same money unchanged when converting to its own currency', function (): void {
    $repository = $this->createMock(ExchangeRateRepository::class);
    $repository->expects($this->never())->method('latestRate');

    $converted = (new Converter($repository))->convert(new Money(500, Currency::USD), Currency::USD);

    expect($converted->equals(new Money(500, Currency::USD)))->toBeTrue();
});

test('converts directly between two currencies via the EUR pivot', function (): void {
    // 1 EUR = 1.08 USD; $10.80 is 10.00 EUR -> 1000 minor units.
    $repository = $this->createMock(ExchangeRateRepository::class);
    $repository->method('latestRate')->willReturnMap([
        [Currency::USD, null, 1.08],
        [Currency::EUR, null, 1.0],
    ]);

    $converted = (new Converter($repository))->convert(new Money(1080, Currency::USD), Currency::EUR);

    expect($converted->equals(new Money(1000, Currency::EUR)))->toBeTrue();
});

test('cross-converts a pair neither of which is EUR', function (): void {
    // 1 EUR = 1.08 USD = 162.0 JPY. $10.80 -> 10.00 EUR -> 1620 yen (JPY has no minor units).
    $repository = $this->createMock(ExchangeRateRepository::class);
    $repository->method('latestRate')->willReturnMap([
        [Currency::USD, null, 1.08],
        [Currency::JPY, null, 162.0],
    ]);

    $converted = (new Converter($repository))->convert(new Money(1080, Currency::USD), Currency::JPY);

    expect($converted->equals(new Money(1620, Currency::JPY)))->toBeTrue();
});

test('passes an as-of date through to the rate lookup', function (): void {
    $asOf = new DateTimeImmutable('2024-01-01');
    $repository = $this->createMock(ExchangeRateRepository::class);
    $repository->method('latestRate')->willReturnMap([
        [Currency::USD, $asOf, 1.08],
        [Currency::EUR, $asOf, 1.0],
    ]);

    $converted = (new Converter($repository))->convert(new Money(1080, Currency::USD), Currency::EUR, $asOf);

    expect($converted->equals(new Money(1000, Currency::EUR)))->toBeTrue();
});

test('throws when a rate is missing for either currency', function (): void {
    $repository = $this->createMock(ExchangeRateRepository::class);
    $repository->method('latestRate')->willReturn(null);

    (new Converter($repository))->convert(new Money(1080, Currency::USD), Currency::JPY);
})->throws(Assert\InvalidArgumentException::class);
