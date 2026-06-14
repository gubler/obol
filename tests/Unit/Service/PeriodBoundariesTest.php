<?php

// ABOUTME: Unit tests for PeriodBoundaries - the inclusive end of the current calendar week/month/year.
// ABOUTME: Week uses the US Sunday-start convention (the swappable default), so it ends on Saturday.

declare(strict_types=1);

use App\Enum\ObligationTrendPeriod;
use App\Enum\PaymentPeriod;
use App\Service\PeriodBoundaries;

test('end of month is the last day of the month at end of day', function (): void {
    expect((new PeriodBoundaries(0))->endOfPeriod(PaymentPeriod::Month, new DateTimeImmutable('2026-02-10')))
        ->toEqual(new DateTimeImmutable('2026-02-28 23:59:59'))
    ;
});

test('end of year is December 31 at end of day', function (): void {
    expect((new PeriodBoundaries(0))->endOfPeriod(PaymentPeriod::Year, new DateTimeImmutable('2026-06-12')))
        ->toEqual(new DateTimeImmutable('2026-12-31 23:59:59'))
    ;
});

test('end of week is the current week Saturday at end of day (US Sunday-start)', function (string $asOf): void {
    $end = (new PeriodBoundaries(0))->endOfPeriod(PaymentPeriod::Week, new DateTimeImmutable($asOf));

    expect($end->format('w'))->toBe('6')                  // Saturday - Sunday-start weeks end on Saturday
        ->and($end->format('H:i:s'))->toBe('23:59:59')
        ->and($end >= new DateTimeImmutable($asOf))->toBeTrue()
        ->and($end->diff(new DateTimeImmutable($asOf))->days)->toBeLessThan(7)
    ;
})->with(['2026-06-14', '2026-06-15', '2026-06-18', '2026-06-20']);

test('the week-start day is configurable: a Monday start ends the week on Sunday', function (): void {
    $end = (new PeriodBoundaries(1))->endOfPeriod(PaymentPeriod::Week, new DateTimeImmutable('2026-06-15'));

    expect($end->format('w'))->toBe('0')                  // Sunday - Monday-start (ISO) weeks end on Sunday
        ->and($end->format('H:i:s'))->toBe('23:59:59')
    ;
});

test('start of day is midnight of the same day', function (): void {
    expect((new PeriodBoundaries(0))->startOfPeriod(ObligationTrendPeriod::Day, new DateTimeImmutable('2026-06-13 15:30:45')))
        ->toEqual(new DateTimeImmutable('2026-06-13 00:00:00'))
    ;
});

test('start of month is the first of the month at midnight', function (): void {
    expect((new PeriodBoundaries(0))->startOfPeriod(ObligationTrendPeriod::Month, new DateTimeImmutable('2026-06-13 15:30:45')))
        ->toEqual(new DateTimeImmutable('2026-06-01 00:00:00'))
    ;
});

test('start of week is the current week-start day at midnight (US Sunday-start)', function (string $asOf): void {
    $start = (new PeriodBoundaries(0))->startOfPeriod(ObligationTrendPeriod::Week, new DateTimeImmutable($asOf));

    expect($start->format('w'))->toBe('0')                // Sunday - the configured week start
        ->and($start->format('H:i:s'))->toBe('00:00:00')
        ->and($start <= new DateTimeImmutable($asOf))->toBeTrue()
        ->and((new DateTimeImmutable($asOf))->diff($start)->days)->toBeLessThan(7)
    ;
})->with(['2026-06-14', '2026-06-15', '2026-06-18', '2026-06-20']);

test('start of week honors a configurable Monday start', function (): void {
    $start = (new PeriodBoundaries(1))->startOfPeriod(ObligationTrendPeriod::Week, new DateTimeImmutable('2026-06-18 12:00:00'));

    expect($start->format('w'))->toBe('1')                // Monday - ISO week start
        ->and($start->format('H:i:s'))->toBe('00:00:00')
    ;
});
