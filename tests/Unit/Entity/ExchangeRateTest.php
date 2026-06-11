<?php

// ABOUTME: Unit tests for the ExchangeRate entity - a EUR-pivot rate for a currency on a date.
// ABOUTME: Verifies construction and the positive-rate invariant.

declare(strict_types=1);

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use Symfony\Component\Uid\Ulid;

test('stores a EUR-pivot rate for a currency on a date', function (): void {
    $rate = new ExchangeRate(Currency::USD, 1.0732, new DateTimeImmutable('2024-06-10'));

    expect($rate->currency)->toBe(Currency::USD)
        ->and($rate->rate)->toBe(1.0732)
        ->and($rate->asOf)->toEqual(new DateTimeImmutable('2024-06-10'))
        ->and($rate->id)->toBeInstanceOf(Ulid::class)
    ;
});

test('rejects a non-positive rate', function (): void {
    new ExchangeRate(Currency::USD, 0.0, new DateTimeImmutable('2024-06-10'));
})->throws(Assert\InvalidArgumentException::class);
