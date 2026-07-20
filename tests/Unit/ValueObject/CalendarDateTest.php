<?php

// ABOUTME: Unit tests for the CalendarDate value object - a timezone-less calendar date (year/month/day).
// ABOUTME: Pins construction, the invariant, comparison, day arithmetic, and the datetime<->date reductions.

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\Tests\Support\PinsDefaultTimezone;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarDateTest extends TestCase
{
    use PinsDefaultTimezone;

    public function testExposesItsYearMonthAndDay(): void
    {
        $date = CalendarDate::for(2026, 8, 1);

        self::assertSame(2026, $date->year);
        self::assertSame(8, $date->month);
        self::assertSame(1, $date->day);
    }

    public function testParsesAStrictIsoString(): void
    {
        self::assertTrue(CalendarDate::fromString('2026-08-01')->equals(CalendarDate::for(2026, 8, 1)));
    }

    public function testAcceptsALeapDay(): void
    {
        self::assertTrue(CalendarDate::fromString('2024-02-29')->equals(CalendarDate::for(2024, 2, 29)));
    }

    public function testAcceptsTheFourHundredYearLeapDayButRejectsTheCenturyOne(): void
    {
        // 2000 is a leap year (divisible by 400); 1900 is not (divisible by 100 but not 400). This is
        // what proves checkdate() and not a bare `% 4`.
        self::assertTrue(CalendarDate::fromString('2000-02-29')->equals(CalendarDate::for(2000, 2, 29)));

        $this->expectException(\Assert\InvalidArgumentException::class);
        CalendarDate::for(1900, 2, 29);
    }

    #[DataProvider('provideRejectsAnInvalidStringCases')]
    public function testRejectsAnInvalidString(string $input): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);
        CalendarDate::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectsAnInvalidStringCases(): iterable
    {
        yield 'empty' => [''];
        yield 'a midnight time component' => ['2026-08-01 00:00:00'];
        yield 'a real time component' => ['2026-08-01 14:37'];
        yield 'an offset' => ['2026-08-01T00:00:00+00:00'];
        yield 'a Z suffix' => ['2026-08-01T00:00:00Z'];
        yield 'the relative word today' => ['today'];
        yield 'the relative word now' => ['now'];
        yield 'a relative expression' => ['+1 day'];
        yield 'a natural-language expression' => ['first day of this month'];
        yield 'a non-zero-padded month and day' => ['2026-8-1'];
        yield 'a two-digit year' => ['26-08-01'];
        yield 'a month above 12' => ['2026-13-01'];
        yield 'a zero month' => ['2026-00-01'];
        yield 'a day above the month length' => ['2026-08-32'];
        yield 'a zero day' => ['2026-08-00'];
        yield 'the 30th of February (must not silently roll)' => ['2026-02-30'];
        yield 'a non-leap February 29th' => ['2025-02-29'];
        yield 'the 31st of a 30-day month' => ['2026-04-31'];
        yield 'a century non-leap February 29th' => ['1900-02-29'];
        yield 'trailing junk' => ['2026-08-01x'];
        yield 'leading whitespace' => [' 2026-08-01'];
        yield 'a zero year' => ['0000-01-01'];
    }

    #[DataProvider('provideForRejectsTheSameSetAsFromStringCases')]
    public function testForRejectsTheSameSetAsFromString(int $year, int $month, int $day): void
    {
        // The invariant lives in the constructor, not the parser.
        $this->expectException(\Assert\InvalidArgumentException::class);
        CalendarDate::for($year, $month, $day);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function provideForRejectsTheSameSetAsFromStringCases(): iterable
    {
        yield 'month above 12' => [2026, 13, 1];
        yield 'the 30th of February' => [2026, 2, 30];
        yield 'a zero month' => [2026, 0, 1];
        yield 'a zero day' => [2026, 8, 0];
        yield 'a zero year' => [0, 1, 1];
    }

    public function testConstructionNeverReadsTheAmbientTimezone(): void
    {
        date_default_timezone_set('Pacific/Auckland');
        $underAuckland = CalendarDate::for(2026, 8, 1);

        date_default_timezone_set('UTC');
        $underUtc = CalendarDate::for(2026, 8, 1);

        self::assertTrue($underAuckland->equals($underUtc));
    }

    public function testEqualityComparesEachFieldIndependently(): void
    {
        $date = CalendarDate::for(2026, 8, 1);

        self::assertTrue($date->equals(CalendarDate::for(2026, 8, 1)));
        self::assertFalse($date->equals(CalendarDate::for(2026, 8, 2)), 'differs in day');
        self::assertFalse($date->equals(CalendarDate::for(2026, 9, 1)), 'differs in month');
        self::assertFalse($date->equals(CalendarDate::for(2027, 8, 1)), 'differs in year');
    }

    public function testComparisonFollowsYearThenMonthThenDayPrecedence(): void
    {
        // A larger year wins even though its month and day are smaller - the field precedence, which a
        // reordered comparison would break.
        self::assertTrue(CalendarDate::for(2027, 1, 1)->isAfter(CalendarDate::for(2026, 12, 31)));
        // A larger month wins even though the day is smaller.
        self::assertTrue(CalendarDate::for(2026, 8, 1)->isAfter(CalendarDate::for(2026, 7, 31)));
    }

    public function testComparisonBoundariesAreExact(): void
    {
        $day = CalendarDate::for(2026, 8, 1);
        $next = CalendarDate::for(2026, 8, 2);
        $prev = CalendarDate::for(2026, 7, 31);

        self::assertSame(0, $day->compareTo($day));
        self::assertLessThan(0, $day->compareTo($next));
        self::assertGreaterThan(0, $day->compareTo($prev));

        // isAfter is strict (equal -> false): recordPayment's `>` boundary now lives here.
        self::assertFalse($day->isAfter($day));
        self::assertTrue($day->isAfter($prev));
        // isOnOrBefore includes equality: remainingInPeriod's `<=` boundary lives here.
        self::assertTrue($day->isOnOrBefore($day));
        self::assertTrue($day->isOnOrBefore($next));
        self::assertFalse($day->isOnOrBefore($prev));

        self::assertFalse($day->isBefore($day));
        self::assertTrue($day->isBefore($next));
        self::assertTrue($day->isOnOrAfter($day));
    }

    public function testSpaceshipOnTwoDatesAgreesWithCompareTo(): void
    {
        // Pins the property-declaration order (year, month, day) so a future reorder fails loudly rather
        // than silently mis-sorting anything that uses the default `<=>`.
        $pairs = [
            [CalendarDate::for(2026, 8, 1), CalendarDate::for(2026, 8, 1)],
            [CalendarDate::for(2026, 7, 31), CalendarDate::for(2026, 8, 1)],
            [CalendarDate::for(2026, 12, 31), CalendarDate::for(2027, 1, 1)],
            [CalendarDate::for(2027, 1, 1), CalendarDate::for(2026, 12, 31)],
        ];

        foreach ($pairs as [$a, $b]) {
            self::assertSame($a->compareTo($b) <=> 0, $a <=> $b);
        }
    }

    public function testSortsAscendingViaCompareTo(): void
    {
        $dates = [
            CalendarDate::for(2027, 1, 1),
            CalendarDate::for(2026, 7, 31),
            CalendarDate::for(2026, 8, 1),
        ];

        usort($dates, static fn (CalendarDate $a, CalendarDate $b): int => $a->compareTo($b));

        self::assertSame(['2026-07-31', '2026-08-01', '2027-01-01'], array_map('strval', $dates));
    }

    public function testAddsDaysWithoutMutatingTheReceiver(): void
    {
        $date = CalendarDate::for(2026, 8, 1);

        self::assertTrue($date->plusDays(1)->equals(CalendarDate::for(2026, 8, 2)));
        self::assertTrue($date->plusDays(-1)->equals(CalendarDate::for(2026, 7, 31)));
        self::assertTrue($date->equals(CalendarDate::for(2026, 8, 1)), 'the receiver is unchanged');
    }

    public function testAddingZeroDaysReturnsAnEqualValue(): void
    {
        self::assertTrue(CalendarDate::for(2026, 8, 1)->plusDays(0)->equals(CalendarDate::for(2026, 8, 1)));
    }

    public function testAddingDaysCrossesMonthYearAndLeapBoundaries(): void
    {
        self::assertTrue(CalendarDate::for(2026, 8, 1)->plusDays(31)->equals(CalendarDate::for(2026, 9, 1)));
        self::assertTrue(CalendarDate::for(2026, 12, 31)->plusDays(1)->equals(CalendarDate::for(2027, 1, 1)));
        self::assertTrue(CalendarDate::for(2024, 2, 28)->plusDays(1)->equals(CalendarDate::for(2024, 2, 29)));
        self::assertTrue(CalendarDate::for(2025, 2, 28)->plusDays(1)->equals(CalendarDate::for(2025, 3, 1)));
        self::assertTrue(CalendarDate::for(2024, 1, 1)->plusDays(365)->equals(CalendarDate::for(2024, 12, 31)));
    }

    public function testAddsWeeks(): void
    {
        self::assertTrue(CalendarDate::for(2026, 8, 1)->plusWeeks(1)->equals(CalendarDate::for(2026, 8, 8)));
        self::assertTrue(CalendarDate::for(2026, 8, 1)->plusWeeks(2)->equals(CalendarDate::for(2026, 8, 15)));
    }

    #[DataProvider('provideAddingADayIsExactlyOneCalendarDayRegardlessOfAmbientZoneCases')]
    public function testAddingADayIsExactlyOneCalendarDayRegardlessOfAmbientZone(string $zone, string $date): void
    {
        date_default_timezone_set($zone);
        $start = CalendarDate::fromString($date);

        self::assertSame(1, $start->daysUntil($start->plusDays(1)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAddingADayIsExactlyOneCalendarDayRegardlessOfAmbientZoneCases(): iterable
    {
        // A DST spring-forward day is only 23 hours long; a fall-back day 25. Day arithmetic must count
        // calendar days, not hours, so it must not depend on the ambient zone.
        yield 'US spring-forward' => ['America/New_York', '2026-03-08'];
        yield 'US fall-back' => ['America/New_York', '2026-11-01'];
        yield 'Samoa skipped a whole day in 2011' => ['Pacific/Apia', '2011-12-29'];
    }

    public function testDaysUntilIsSigned(): void
    {
        $a = CalendarDate::for(2026, 8, 1);

        self::assertSame(0, $a->daysUntil($a));
        self::assertSame(1, $a->daysUntil(CalendarDate::for(2026, 8, 2)));
        self::assertSame(-1, $a->daysUntil(CalendarDate::for(2026, 7, 31)));
    }

    public function testDaysUntilCountsLeapAndNonLeapFebruaryCorrectly(): void
    {
        self::assertSame(2, CalendarDate::for(2024, 2, 28)->daysUntil(CalendarDate::for(2024, 3, 1)));
        self::assertSame(1, CalendarDate::for(2025, 2, 28)->daysUntil(CalendarDate::for(2025, 3, 1)));
        self::assertSame(366, CalendarDate::for(2024, 1, 1)->daysUntil(CalendarDate::for(2025, 1, 1)));
        self::assertSame(365, CalendarDate::for(2025, 1, 1)->daysUntil(CalendarDate::for(2026, 1, 1)));
    }

    public function testDaysInMonthKnowsMonthLengthsAndLeapRules(): void
    {
        self::assertSame(28, CalendarDate::for(2026, 2, 1)->daysInMonth());
        self::assertSame(29, CalendarDate::for(2024, 2, 1)->daysInMonth());
        self::assertSame(28, CalendarDate::for(1900, 2, 1)->daysInMonth());
        self::assertSame(29, CalendarDate::for(2000, 2, 1)->daysInMonth());
        self::assertSame(30, CalendarDate::for(2026, 4, 1)->daysInMonth());
        self::assertSame(31, CalendarDate::for(2026, 1, 1)->daysInMonth());
    }

    public function testLastDayOfMonth(): void
    {
        self::assertTrue(CalendarDate::for(2026, 2, 15)->lastDayOfMonth()->equals(CalendarDate::for(2026, 2, 28)));
        self::assertTrue(CalendarDate::for(2024, 2, 15)->lastDayOfMonth()->equals(CalendarDate::for(2024, 2, 29)));
        self::assertTrue(CalendarDate::for(2026, 4, 1)->lastDayOfMonth()->equals(CalendarDate::for(2026, 4, 30)));
    }

    public function testToStringIsZeroPaddedIso(): void
    {
        self::assertSame('2026-08-01', (string) CalendarDate::for(2026, 8, 1));
        self::assertSame('2026-01-05', (string) CalendarDate::for(2026, 1, 5));
    }

    #[DataProvider('provideRoundTripsThroughItsStringCases')]
    public function testRoundTripsThroughItsString(string $iso): void
    {
        self::assertTrue(CalendarDate::fromString((string) CalendarDate::fromString($iso))->equals(CalendarDate::fromString($iso)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRoundTripsThroughItsStringCases(): iterable
    {
        yield 'a leap day' => ['2024-02-29'];
        yield 'January the first' => ['2026-01-01'];
        yield 'December the 31st' => ['2026-12-31'];
        yield 'a 30-day month end' => ['2026-04-30'];
    }

    public function testToDateTimeImmutableIsMidnightInTheGivenZoneAndTheZoneMatters(): void
    {
        $date = CalendarDate::for(2026, 8, 1);

        $utc = $date->toDateTimeImmutable(new \DateTimeZone('UTC'));
        $ny = $date->toDateTimeImmutable(new \DateTimeZone('America/New_York'));

        self::assertSame('2026-08-01T00:00:00+00:00', $utc->format('c'));
        self::assertSame('2026-08-01T00:00:00-04:00', $ny->format('c'));
        // Same wall-clock date, different instants - which is why the zone is a required argument.
        self::assertNotSame($utc->getTimestamp(), $ny->getTimestamp());
    }

    public function testToDateTimeImmutableIsUnaffectedByTheAmbientZone(): void
    {
        date_default_timezone_set('Pacific/Auckland');

        $instant = CalendarDate::for(2026, 8, 1)->toDateTimeImmutable(new \DateTimeZone('UTC'));

        self::assertSame('2026-08-01T00:00:00+00:00', $instant->format('c'));
    }

    public function testToDateTimeImmutableWhereLocalMidnightDoesNotExistRollsForwardWithoutThrowing(): void
    {
        // Chile springs forward at 00:00 -> 01:00 on 2026-09-06 (verified via getTransitions below), so
        // local midnight does not exist that day. PHP resolves it to 01:00 - the first instant of the day -
        // rather than throwing or landing on the previous day.
        $santiago = new \DateTimeZone('America/Santiago');
        $instant = CalendarDate::for(2026, 9, 6)->toDateTimeImmutable($santiago);

        self::assertSame('2026-09-06', $instant->format('Y-m-d'));
        self::assertSame('01:00:00', $instant->format('H:i:s'));
    }

    public function testForDatetimeInTimezoneReducesTheLiveBugInstantToTheOwnersDate(): void
    {
        $ny = new \DateTimeZone('America/New_York');

        // 2026-08-01 03:59:59 UTC is still 2026-07-31 in New York - the exact instant behind the
        // remainingInPeriod bug.
        self::assertTrue(
            CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('2026-08-01 03:59:59', new \DateTimeZone('UTC')), $ny)
                ->equals(CalendarDate::for(2026, 7, 31)),
        );
        // One second later it has ticked over to the 1st in New York.
        self::assertTrue(
            CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('2026-08-01 04:00:00', new \DateTimeZone('UTC')), $ny)
                ->equals(CalendarDate::for(2026, 8, 1)),
        );
    }

    public function testForDatetimeInTimezoneGivesDifferentDatesForDifferentZonesAtOneInstant(): void
    {
        $instant = new \DateTimeImmutable('2026-06-15 06:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(
            CalendarDate::forDatetimeInTimezone($instant, new \DateTimeZone('Pacific/Honolulu'))
                ->equals(CalendarDate::for(2026, 6, 14)),
        );
        $tokyoInstant = new \DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC'));
        self::assertTrue(
            CalendarDate::forDatetimeInTimezone($tokyoInstant, new \DateTimeZone('Asia/Tokyo'))
                ->equals(CalendarDate::for(2026, 6, 16)),
        );
    }

    public function testForDatetimeInTimezoneReducesAnInstantNotAWallClock(): void
    {
        $ny = new \DateTimeZone('America/New_York');
        // The same instant, expressed two ways; the carried offset must not change the result.
        $withOffset = new \DateTimeImmutable('2026-08-01T00:00:00-04:00');
        $inUtc = new \DateTimeImmutable('2026-08-01T04:00:00+00:00');

        self::assertTrue(
            CalendarDate::forDatetimeInTimezone($withOffset, $ny)
                ->equals(CalendarDate::forDatetimeInTimezone($inUtc, $ny)),
        );
    }

    public function testMonthOrdinalCountsMonthsSinceYearZero(): void
    {
        self::assertSame(1, CalendarDate::for(2026, 2, 1)->monthOrdinal() - CalendarDate::for(2026, 1, 1)->monthOrdinal());
        self::assertSame(1, CalendarDate::for(2027, 1, 1)->monthOrdinal() - CalendarDate::for(2026, 12, 1)->monthOrdinal());
    }

    #[DataProvider('provideDayOfWeekIsZeroForSundayCases')]
    public function testDayOfWeekIsZeroForSunday(string $iso, int $expected): void
    {
        self::assertSame($expected, CalendarDate::fromString($iso)->dayOfWeek());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideDayOfWeekIsZeroForSundayCases(): iterable
    {
        yield 'Sunday is 0' => ['2026-08-02', 0];
        yield 'Monday is 1' => ['2026-08-03', 1];
        yield 'Saturday is 6' => ['2026-08-01', 6];
    }
}
