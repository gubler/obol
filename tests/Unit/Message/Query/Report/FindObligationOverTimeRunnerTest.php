<?php

// ABOUTME: Unit tests for FindObligationOverTimeRunner - the obligation trend read from the snapshot series.
// ABOUTME: Each bucket carries the latest snapshot on or before its start, converted at today's rate.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\ObligationSnapshot;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\ObligationTrendPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\FindObligationOverTimeQuery;
use App\Message\Query\Report\FindObligationOverTimeRunner;
use App\Message\Query\Report\ObligationSeries;
use App\Repository\ExchangeRateRepository;
use App\Repository\ObligationSnapshotRepository;
use App\Repository\UserRepository;
use App\Service\PeriodBoundaries;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class FindObligationOverTimeRunnerTest extends TestCase
{
    /**
     * @param array<string, int> $obligationsByCurrency
     */
    private static function trendSnapshot(array $obligationsByCurrency, string $recordedAt): ObligationSnapshot
    {
        return new ObligationSnapshot(new User(email: 'owner@example.com'), $obligationsByCurrency, CalendarDate::fromString($recordedAt));
    }

    /**
     * @param list<ObligationSnapshot> $snapshots
     * @param array<string, float>     $rates
     */
    private function runTrend(array $snapshots, string $now, ObligationTrendPeriod $period = ObligationTrendPeriod::Month, array $rates = [], ?User $viewer = null): ObligationSeries
    {
        $repository = self::createStub(ObligationSnapshotRepository::class);
        $repository->method('findAllOrderedByRecordedAtForOwner')->willReturn($snapshots);

        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository));

        // Default viewer is on UTC so the timezone-agnostic cases read "now" unchanged; the timezone
        // case passes an explicit non-UTC viewer.
        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')->willReturn($viewer ?? new User(email: 'owner@example.com', timezone: 'UTC'));

        $runner = new FindObligationOverTimeRunner($repository, $totaller, $userRepository, new PeriodBoundaries(0), new MockClock(new \DateTimeImmutable($now, new \DateTimeZone('UTC'))));

        return $runner(new FindObligationOverTimeQuery($period, new Ulid()));
    }

    /**
     * @return array<string, int> bucket label => obligation in display-currency minor units
     */
    private static function byLabel(ObligationSeries $series): array
    {
        $byLabel = [];
        foreach ($series->points as $point) {
            $byLabel[$point->label] = $point->amount->minorAmount;
        }

        return $byLabel;
    }

    public function testCarriesEachMonthStartObligationForwardFromTheLatestSnapshotOnOrBeforeIt(): void
    {
        // One snapshot mid-March 2026; "now" is mid-June 2026, within the 24-month window.
        $series = $this->runTrend([self::trendSnapshot(['USD' => 5000], '2026-03-15')], now: '2026-06-13');
        $byLabel = self::byLabel($series);

        self::assertInstanceOf(ObligationSeries::class, $series);
        self::assertCount(24, $series->points);                   // Month lookback
        self::assertSame(ObligationTrendPeriod::Month, $series->period);
        self::assertSame(0, $byLabel['Jan 2026']);                // before the snapshot
        self::assertSame(0, $byLabel['Mar 2026']);                // Mar 15 snapshot is after the Mar 1 anchor
        self::assertSame(5000, $byLabel['Apr 2026']);             // carried forward from Mar 15
        self::assertSame(5000, $byLabel['Jun 2026']);
        self::assertFalse($series->isApproximate);
    }

    public function testABucketCarriesTheLatestSnapshotWhenSeveralPrecedeItsStart(): void
    {
        $series = $this->runTrend(
            [
                self::trendSnapshot(['USD' => 5000], '2026-03-01'),  // <= every bucket from March on
                self::trendSnapshot(['USD' => 8000], '2026-05-10'),  // after May 1, before June 1
            ],
            now: '2026-06-13',
        );
        $byLabel = self::byLabel($series);

        self::assertSame(0, $byLabel['Feb 2026']);     // nothing yet
        self::assertSame(5000, $byLabel['Mar 2026']);  // Mar 1 snapshot is same-day, counts
        self::assertSame(5000, $byLabel['May 2026']);  // 8000 is May 10, after the May 1 anchor
        self::assertSame(8000, $byLabel['Jun 2026']);  // Jun 1 anchor now sees the May 10 snapshot
    }

    public function testIsAFlatZeroLineWhenThereIsNoSnapshotHistory(): void
    {
        $series = $this->runTrend([], now: '2026-06-13');

        $values = array_map(static fn (\App\Message\Query\Report\ObligationPoint $point): int => $point->amount->minorAmount, $series->points);

        self::assertCount(24, $series->points);
        self::assertSame([0], array_values(array_unique($values)));
        self::assertFalse($series->isApproximate);
    }

    public function testConvertsAMultiCurrencySnapshotAtTodayRateAndFlagsTheSeriesApproximate(): void
    {
        $series = $this->runTrend(
            [self::trendSnapshot(['USD' => 10000, 'EUR' => 5000], '2025-12-20')],
            now: '2026-06-13',
            rates: ['EUR' => 1.0, 'USD' => 1.08],
        );
        $byLabel = self::byLabel($series);

        // 10000 USD + (5000 EUR -> 5400 USD) = 15400, carried across every bucket from January 2026 on.
        self::assertSame(15400, $byLabel['Jan 2026']);
        self::assertSame(15400, $byLabel['Jun 2026']);
        self::assertTrue($series->isApproximate);
    }

    public function testAnchorsTheCurrentBucketInTheViewersTimezoneNotUtc(): void
    {
        // 2026-06-30 20:00 UTC is already 2026-07-01 in Tokyo (UTC+9), so the current month bucket the
        // series ends on is July, not June.
        $series = $this->runTrend(
            [],
            now: '2026-06-30 20:00:00',
            viewer: new User(email: 'jp@example.com', timezone: 'Asia/Tokyo'),
        );

        $labels = array_map(static fn (\App\Message\Query\Report\ObligationPoint $point): string => $point->label, $series->points);

        self::assertSame('Jul 2026', end($labels));
    }

    public function testAWeeklyTrendLooksBackOver52WeekStartBuckets(): void
    {
        $series = $this->runTrend([], now: '2026-06-13', period: ObligationTrendPeriod::Week);

        self::assertCount(52, $series->points);
        self::assertSame(ObligationTrendPeriod::Week, $series->period);
    }

    public function testAYearlyTrendLooksBackOver10YearStartBuckets(): void
    {
        $series = $this->runTrend([], now: '2026-06-13', period: ObligationTrendPeriod::Year);

        self::assertCount(10, $series->points);
        self::assertSame(ObligationTrendPeriod::Year, $series->period);
    }
}
