<?php

// ABOUTME: Unit tests for FindObligationOverTimeRunner - the obligation trend read from the snapshot series.
// ABOUTME: Each bucket carries the latest snapshot on or before its start, converted at today's rate.

declare(strict_types=1);

use App\Entity\ObligationSnapshot;
use App\Enum\Currency;
use App\Enum\ObligationTrendPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\FindObligationOverTimeQuery;
use App\Message\Query\Report\FindObligationOverTimeRunner;
use App\Message\Query\Report\ObligationSeries;
use App\Repository\ExchangeRateRepository;
use App\Repository\ObligationSnapshotRepository;
use App\Service\DisplayCurrencyProvider;
use App\Service\PeriodBoundaries;
use Symfony\Component\Clock\MockClock;

/**
 * @param array<string, int> $obligationsByCurrency
 */
function trendSnapshot(array $obligationsByCurrency, string $recordedAt): ObligationSnapshot
{
    return new ObligationSnapshot($obligationsByCurrency, new DateTimeImmutable($recordedAt));
}

/**
 * @param list<ObligationSnapshot> $snapshots
 * @param array<string, float>     $rates
 */
function runTrend(array $snapshots, string $now, ObligationTrendPeriod $period = ObligationTrendPeriod::Month, array $rates = []): ObligationSeries
{
    $repository = test()->createMock(ObligationSnapshotRepository::class);
    $repository->method('findAllOrderedByRecordedAt')->willReturn($snapshots);

    $exchangeRateRepository = test()->createMock(ExchangeRateRepository::class);
    $exchangeRateRepository->method('latestRate')
        ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
    ;
    $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));

    $runner = new FindObligationOverTimeRunner($repository, $totaller, new PeriodBoundaries(0), new MockClock(new DateTimeImmutable($now)));

    return $runner(new FindObligationOverTimeQuery($period));
}

test('carries each month-start obligation forward from the latest snapshot on or before it', function (): void {
    // One snapshot mid-March; "now" is mid-June, so the trend spans Jan..Jun.
    $series = runTrend([trendSnapshot(['USD' => 5000], '2026-03-15')], now: '2026-06-13');

    $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);
    $labels = array_map(static fn ($point): string => $point->label, $series->points);

    expect($series)->toBeInstanceOf(ObligationSeries::class)
        ->and($series->points)->toHaveCount(6)                       // Month lookback
        ->and($values)->toBe([0, 0, 0, 5000, 5000, 5000])           // nothing before Mar 15; carried forward after
        ->and($labels)->toBe(['Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026'])
        ->and($series->isApproximate)->toBeFalse()
        ->and($series->period)->toBe(ObligationTrendPeriod::Month)
    ;
});

test('a bucket carries the latest snapshot when several precede its start', function (): void {
    $series = runTrend(
        [
            trendSnapshot(['USD' => 5000], '2026-03-01'),  // <= every bucket from April on
            trendSnapshot(['USD' => 8000], '2026-05-10'),  // after May 1, before June 1
        ],
        now: '2026-06-13',
    );

    $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);

    // Jan, Feb: nothing yet. Mar 1: snapshot is same-day, counts. Apr, May 1: still 5000 (8000 is May 10). Jun 1: 8000.
    expect($values)->toBe([0, 0, 5000, 5000, 5000, 8000]);
});

test('is a flat zero line when there is no snapshot history', function (): void {
    $series = runTrend([], now: '2026-06-13');

    $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);

    expect($values)->toBe([0, 0, 0, 0, 0, 0])
        ->and($series->isApproximate)->toBeFalse()
    ;
});

test('converts a multi-currency snapshot at today rate and flags the series approximate', function (): void {
    $series = runTrend(
        [trendSnapshot(['USD' => 10000, 'EUR' => 5000], '2025-12-20')],
        now: '2026-06-13',
        rates: ['EUR' => 1.0, 'USD' => 1.08],
    );

    $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);

    // 10000 USD + (5000 EUR -> 5400 USD) = 15400, carried across every bucket (snapshot predates them all).
    expect($values)->toBe([15400, 15400, 15400, 15400, 15400, 15400])
        ->and($series->isApproximate)->toBeTrue()
    ;
});

test('a weekly trend looks back over eight week-start buckets', function (): void {
    $series = runTrend([], now: '2026-06-13', period: ObligationTrendPeriod::Week);

    expect($series->points)->toHaveCount(8)
        ->and($series->period)->toBe(ObligationTrendPeriod::Week)
    ;
});
