<?php

// ABOUTME: Unit tests for FindObligationOverTimeRunner - the obligation trend read from the snapshot series.
// ABOUTME: Each bucket carries the latest snapshot on or before its start, converted at today's rate.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class FindObligationOverTimeRunnerTest extends TestCase
{
    /**
     * @param array<string, int> $obligationsByCurrency
     */
    private static function trendSnapshot(array $obligationsByCurrency, string $recordedAt): ObligationSnapshot
    {
        return new ObligationSnapshot($obligationsByCurrency, new \DateTimeImmutable($recordedAt));
    }

    /**
     * @param list<ObligationSnapshot> $snapshots
     * @param array<string, float>     $rates
     */
    private function runTrend(array $snapshots, string $now, ObligationTrendPeriod $period = ObligationTrendPeriod::Month, array $rates = []): ObligationSeries
    {
        $repository = $this->createMock(ObligationSnapshotRepository::class);
        $repository->method('findAllOrderedByRecordedAt')->willReturn($snapshots);

        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));

        $runner = new FindObligationOverTimeRunner($repository, $totaller, new PeriodBoundaries(0), new MockClock(new \DateTimeImmutable($now)));

        return $runner(new FindObligationOverTimeQuery($period));
    }

    public function testCarriesEachMonthStartObligationForwardFromTheLatestSnapshotOnOrBeforeIt(): void
    {
        // One snapshot mid-March; "now" is mid-June, so the trend spans Jan..Jun.
        $series = $this->runTrend([self::trendSnapshot(['USD' => 5000], '2026-03-15')], now: '2026-06-13');

        $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);
        $labels = array_map(static fn ($point): string => $point->label, $series->points);

        self::assertInstanceOf(ObligationSeries::class, $series);
        self::assertCount(6, $series->points);                       // Month lookback
        self::assertSame([0, 0, 0, 5000, 5000, 5000], $values);      // nothing before Mar 15; carried forward after
        self::assertSame(['Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026'], $labels);
        self::assertFalse($series->isApproximate);
        self::assertSame(ObligationTrendPeriod::Month, $series->period);
    }

    public function testABucketCarriesTheLatestSnapshotWhenSeveralPrecedeItsStart(): void
    {
        $series = $this->runTrend(
            [
                self::trendSnapshot(['USD' => 5000], '2026-03-01'),  // <= every bucket from April on
                self::trendSnapshot(['USD' => 8000], '2026-05-10'),  // after May 1, before June 1
            ],
            now: '2026-06-13',
        );

        $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);

        // Jan, Feb: nothing yet. Mar 1: snapshot is same-day, counts. Apr, May 1: still 5000 (8000 is May 10). Jun 1: 8000.
        self::assertSame([0, 0, 5000, 5000, 5000, 8000], $values);
    }

    public function testIsAFlatZeroLineWhenThereIsNoSnapshotHistory(): void
    {
        $series = $this->runTrend([], now: '2026-06-13');

        $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);

        self::assertSame([0, 0, 0, 0, 0, 0], $values);
        self::assertFalse($series->isApproximate);
    }

    public function testConvertsAMultiCurrencySnapshotAtTodayRateAndFlagsTheSeriesApproximate(): void
    {
        $series = $this->runTrend(
            [self::trendSnapshot(['USD' => 10000, 'EUR' => 5000], '2025-12-20')],
            now: '2026-06-13',
            rates: ['EUR' => 1.0, 'USD' => 1.08],
        );

        $values = array_map(static fn ($point): int => $point->amount->minorAmount, $series->points);

        // 10000 USD + (5000 EUR -> 5400 USD) = 15400, carried across every bucket (snapshot predates them all).
        self::assertSame([15400, 15400, 15400, 15400, 15400, 15400], $values);
        self::assertTrue($series->isApproximate);
    }

    public function testAWeeklyTrendLooksBackOverEightWeekStartBuckets(): void
    {
        $series = $this->runTrend([], now: '2026-06-13', period: ObligationTrendPeriod::Week);

        self::assertCount(8, $series->points);
        self::assertSame(ObligationTrendPeriod::Week, $series->period);
    }
}
