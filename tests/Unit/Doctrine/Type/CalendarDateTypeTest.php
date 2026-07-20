<?php

// ABOUTME: Unit tests for the CalendarDateType DBAL type: CalendarDate <-> DATE column round-trip.
// ABOUTME: Covers null handling, strict Y-m-d output, passthrough, and truncation of a stray time part.

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Type;

use App\Doctrine\Type\CalendarDateType;
use App\ValueObject\CalendarDate;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CalendarDateTypeTest extends TestCase
{
    private CalendarDateType $type;

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new CalendarDateType();
        $this->platform = new PostgreSQLPlatform();
    }

    #[Test]
    public function itDeclaresADateColumn(): void
    {
        self::assertSame('DATE', $this->type->getSQLDeclaration([], $this->platform));
    }

    #[Test]
    public function itConvertsACalendarDateToAStrictYmdString(): void
    {
        self::assertSame(
            '2026-08-01',
            $this->type->convertToDatabaseValue(CalendarDate::for(2026, 8, 1), $this->platform),
        );
    }

    #[Test]
    public function itConvertsNullToNullBothWays(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    #[Test]
    public function itRejectsANonCalendarDateOnTheWayToTheDatabase(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(new \DateTimeImmutable('2026-08-01'), $this->platform);
    }

    #[Test]
    public function itHydratesAYmdStringIntoACalendarDate(): void
    {
        $date = $this->type->convertToPHPValue('2026-08-01', $this->platform);

        self::assertInstanceOf(CalendarDate::class, $date);
        self::assertTrue(CalendarDate::for(2026, 8, 1)->equals($date));
    }

    #[Test]
    public function itPassesAnAlreadyHydratedCalendarDateThrough(): void
    {
        $date = CalendarDate::for(2026, 8, 1);

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    #[Test]
    public function itTruncatesAStrayTimePartWhenHydrating(): void
    {
        // A DATE column never returns a time, but a raw value carrying one is read as its calendar day
        // rather than rejected - the time is meaningless to a calendar date.
        $date = $this->type->convertToPHPValue('2026-08-01 14:37:00', $this->platform);

        self::assertInstanceOf(CalendarDate::class, $date);
        self::assertTrue(CalendarDate::for(2026, 8, 1)->equals($date));
    }
}
