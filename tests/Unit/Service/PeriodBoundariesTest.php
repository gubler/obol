<?php

// ABOUTME: Unit tests for PeriodBoundaries - the first/last calendar day of the current week/month/year.
// ABOUTME: Week uses the US Sunday-start convention (the swappable default), so it ends on Saturday.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\ObligationTrendPeriod;
use App\Enum\PaymentPeriod;
use App\Service\PeriodBoundaries;
use App\Tests\Support\CalendarDateAssertions;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeriodBoundariesTest extends TestCase
{
    use CalendarDateAssertions;

    public function testEndOfMonthIsTheLastDayOfTheMonth(): void
    {
        self::assertSameDate(
            '2026-02-28',
            new PeriodBoundaries(0)->endOfPeriod(PaymentPeriod::Month, CalendarDate::fromString('2026-02-10')),
        );
    }

    public function testEndOfYearIsDecember31(): void
    {
        self::assertSameDate(
            '2026-12-31',
            new PeriodBoundaries(0)->endOfPeriod(PaymentPeriod::Year, CalendarDate::fromString('2026-06-12')),
        );
    }

    #[DataProvider('provideDaysWithinAWeek')]
    public function testEndOfWeekIsTheCurrentWeekSaturdayUsSundayStart(string $asOf): void
    {
        $start = CalendarDate::fromString($asOf);
        $end = new PeriodBoundaries(0)->endOfPeriod(PaymentPeriod::Week, $start);

        self::assertSame(6, $end->dayOfWeek());                 // Saturday - Sunday-start weeks end on Saturday
        self::assertTrue($end->isOnOrAfter($start));
        self::assertLessThan(7, $start->daysUntil($end));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideDaysWithinAWeek(): iterable
    {
        yield ['2026-06-14'];
        yield ['2026-06-15'];
        yield ['2026-06-18'];
        yield ['2026-06-20'];
    }

    public function testTheWeekStartDayIsConfigurableAMondayStartEndsTheWeekOnSunday(): void
    {
        $end = new PeriodBoundaries(1)->endOfPeriod(PaymentPeriod::Week, CalendarDate::fromString('2026-06-15'));

        self::assertSame(0, $end->dayOfWeek());                 // Sunday - Monday-start (ISO) weeks end on Sunday
    }

    public function testStartOfYearIsJanuaryFirst(): void
    {
        self::assertSameDate(
            '2026-01-01',
            new PeriodBoundaries(0)->startOfPeriod(ObligationTrendPeriod::Year, CalendarDate::fromString('2026-06-13')),
        );
    }

    public function testStartOfMonthIsTheFirstOfTheMonth(): void
    {
        self::assertSameDate(
            '2026-06-01',
            new PeriodBoundaries(0)->startOfPeriod(ObligationTrendPeriod::Month, CalendarDate::fromString('2026-06-13')),
        );
    }

    #[DataProvider('provideDaysWithinAWeek')]
    public function testStartOfWeekIsTheCurrentWeekStartDayUsSundayStart(string $asOf): void
    {
        $reference = CalendarDate::fromString($asOf);
        $start = new PeriodBoundaries(0)->startOfPeriod(ObligationTrendPeriod::Week, $reference);

        self::assertSame(0, $start->dayOfWeek());               // Sunday - the configured week start
        self::assertTrue($start->isOnOrBefore($reference));
        self::assertLessThan(7, $start->daysUntil($reference));
    }

    public function testStartOfWeekHonorsAConfigurableMondayStart(): void
    {
        $start = new PeriodBoundaries(1)->startOfPeriod(ObligationTrendPeriod::Week, CalendarDate::fromString('2026-06-18'));

        self::assertSame(1, $start->dayOfWeek());               // Monday - ISO week start
    }
}
