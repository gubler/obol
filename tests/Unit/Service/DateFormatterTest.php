<?php

// ABOUTME: Unit tests for DateFormatter - renders a date in a DateFormat style for the ambient locale.
// ABOUTME: Long/Medium/Short follow the locale (en-US vs en-GB differ); Iso is fixed regardless.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\DateFormat;
use App\Service\DateFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class DateFormatterTest extends TestCase
{
    public function testRendersEachStyleInTheAmericanLocale(): void
    {
        $formatter = new DateFormatter(new LocaleSwitcher('en-US', []));
        $date = new \DateTimeImmutable('2027-03-09');

        self::assertSame('March 9, 2027', $formatter->format($date, DateFormat::Long));
        self::assertSame('Mar 9, 2027', $formatter->format($date, DateFormat::Medium));
        self::assertSame('3/9/27', $formatter->format($date, DateFormat::Short));
        self::assertSame('2027-03-09', $formatter->format($date, DateFormat::Iso));
    }

    public function testTheLocaleAwareStylesFollowTheLocale(): void
    {
        // A British user reads the medium form day-first; the ISO style ignores the locale entirely.
        $formatter = new DateFormatter(new LocaleSwitcher('en-GB', []));
        $date = new \DateTimeImmutable('2027-03-09');

        self::assertSame('9 Mar 2027', $formatter->format($date, DateFormat::Medium));
        self::assertSame('2027-03-09', $formatter->format($date, DateFormat::Iso));
    }

    public function testDateTimeFollowsTheLocaleHourCycleForTheLocaleAwareStyles(): void
    {
        // The audit log renders a datetime: the American short style uses a 12-hour clock with AM/PM...
        $american = new DateFormatter(new LocaleSwitcher('en-US', []));
        $moment = new \DateTimeImmutable('2027-03-09 14:30');

        self::assertStringContainsString('2:30', $american->formatDateTime($moment, DateFormat::Short));
        self::assertStringContainsString('PM', $american->formatDateTime($moment, DateFormat::Short));

        // ...while the British short style uses a 24-hour clock, no meridiem.
        $british = new DateFormatter(new LocaleSwitcher('en-GB', []));
        self::assertStringContainsString('14:30', $british->formatDateTime($moment, DateFormat::Short));
        self::assertStringNotContainsString('PM', $british->formatDateTime($moment, DateFormat::Short));
    }

    public function testIsoDateTimeIsAFixed24HourFormatRegardlessOfLocale(): void
    {
        $american = new DateFormatter(new LocaleSwitcher('en-US', []));
        $moment = new \DateTimeImmutable('2027-03-09 14:30');

        self::assertSame('2027-03-09 14:30', $american->formatDateTime($moment, DateFormat::Iso));
    }
}
