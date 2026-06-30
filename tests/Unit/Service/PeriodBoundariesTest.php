<?php

// ABOUTME: Unit tests for PeriodBoundaries - the inclusive end of the current calendar week/month/year.
// ABOUTME: Week uses the US Sunday-start convention (the swappable default), so it ends on Saturday.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\ObligationTrendPeriod;
use App\Enum\PaymentPeriod;
use App\Service\PeriodBoundaries;
use App\Tests\Support\InstantAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeriodBoundariesTest extends TestCase
{
    use InstantAssertions;

    public function testEndOfMonthIsTheLastDayOfTheMonthAtEndOfDay(): void
    {
        self::assertSameInstant(
            new \DateTimeImmutable('2026-02-28 23:59:59'),
            new PeriodBoundaries(0)->endOfPeriod(PaymentPeriod::Month, new \DateTimeImmutable('2026-02-10')),
        );
    }

    public function testEndOfYearIsDecember31AtEndOfDay(): void
    {
        self::assertSameInstant(
            new \DateTimeImmutable('2026-12-31 23:59:59'),
            new PeriodBoundaries(0)->endOfPeriod(PaymentPeriod::Year, new \DateTimeImmutable('2026-06-12')),
        );
    }

    #[DataProvider('provideEndOfWeekIsTheCurrentWeekSaturdayAtEndOfDayUsSundayStartCases')]
    public function testEndOfWeekIsTheCurrentWeekSaturdayAtEndOfDayUsSundayStart(string $asOf): void
    {
        $end = new PeriodBoundaries(0)->endOfPeriod(PaymentPeriod::Week, new \DateTimeImmutable($asOf));

        self::assertSame('6', $end->format('w'));                  // Saturday - Sunday-start weeks end on Saturday
        self::assertSame('23:59:59', $end->format('H:i:s'));
        self::assertGreaterThanOrEqual(new \DateTimeImmutable($asOf), $end);
        self::assertLessThan(7, $end->diff(new \DateTimeImmutable($asOf))->days);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideEndOfWeekIsTheCurrentWeekSaturdayAtEndOfDayUsSundayStartCases(): iterable
    {
        yield ['2026-06-14'];
        yield ['2026-06-15'];
        yield ['2026-06-18'];
        yield ['2026-06-20'];
    }

    public function testTheWeekStartDayIsConfigurableAMondayStartEndsTheWeekOnSunday(): void
    {
        $end = new PeriodBoundaries(1)->endOfPeriod(PaymentPeriod::Week, new \DateTimeImmutable('2026-06-15'));

        self::assertSame('0', $end->format('w'));                  // Sunday - Monday-start (ISO) weeks end on Sunday
        self::assertSame('23:59:59', $end->format('H:i:s'));
    }

    public function testStartOfYearIsJanuaryFirstAtMidnight(): void
    {
        self::assertSameInstant(
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            new PeriodBoundaries(0)->startOfPeriod(ObligationTrendPeriod::Year, new \DateTimeImmutable('2026-06-13 15:30:45')),
        );
    }

    public function testStartOfMonthIsTheFirstOfTheMonthAtMidnight(): void
    {
        self::assertSameInstant(
            new \DateTimeImmutable('2026-06-01 00:00:00'),
            new PeriodBoundaries(0)->startOfPeriod(ObligationTrendPeriod::Month, new \DateTimeImmutable('2026-06-13 15:30:45')),
        );
    }

    #[DataProvider('provideStartOfWeekIsTheCurrentWeekStartDayAtMidnightUsSundayStartCases')]
    public function testStartOfWeekIsTheCurrentWeekStartDayAtMidnightUsSundayStart(string $asOf): void
    {
        $start = new PeriodBoundaries(0)->startOfPeriod(ObligationTrendPeriod::Week, new \DateTimeImmutable($asOf));

        self::assertSame('0', $start->format('w'));                // Sunday - the configured week start
        self::assertSame('00:00:00', $start->format('H:i:s'));
        self::assertLessThanOrEqual(new \DateTimeImmutable($asOf), $start);
        self::assertLessThan(7, new \DateTimeImmutable($asOf)->diff($start)->days);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideStartOfWeekIsTheCurrentWeekStartDayAtMidnightUsSundayStartCases(): iterable
    {
        yield ['2026-06-14'];
        yield ['2026-06-15'];
        yield ['2026-06-18'];
        yield ['2026-06-20'];
    }

    public function testStartOfWeekHonorsAConfigurableMondayStart(): void
    {
        $start = new PeriodBoundaries(1)->startOfPeriod(ObligationTrendPeriod::Week, new \DateTimeImmutable('2026-06-18 12:00:00'));

        self::assertSame('1', $start->format('w'));                // Monday - ISO week start
        self::assertSame('00:00:00', $start->format('H:i:s'));
    }
}
