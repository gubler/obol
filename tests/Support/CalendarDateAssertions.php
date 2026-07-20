<?php

// ABOUTME: Test trait asserting a CalendarDate equals an expected Y-m-d string, by value.
// ABOUTME: The calendar-date counterpart to InstantAssertions (which compares zoned instants).

declare(strict_types=1);

namespace App\Tests\Support;

use App\ValueObject\CalendarDate;

trait CalendarDateAssertions
{
    private static function assertSameDate(string $expectedYmd, CalendarDate $actual): void
    {
        self::assertSame($expectedYmd, (string) $actual);
    }
}
