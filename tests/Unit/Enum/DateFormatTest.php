<?php

// ABOUTME: Unit tests for the DateFormat enum - the ICU length per style and the one fixed ISO pattern.
// ABOUTME: Long/Medium/Short follow the locale via an IntlDateFormatter length; Iso pins yyyy-MM-dd.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\DateFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateFormatTest extends TestCase
{
    #[DataProvider('provideMapsEachStyleToItsIcuLengthCases')]
    public function testMapsEachStyleToItsIcuLength(DateFormat $format, int $expected): void
    {
        self::assertSame($expected, $format->length());
    }

    /**
     * @return iterable<string, array{DateFormat, int}>
     */
    public static function provideMapsEachStyleToItsIcuLengthCases(): iterable
    {
        yield 'long' => [DateFormat::Long, \IntlDateFormatter::LONG];
        yield 'medium' => [DateFormat::Medium, \IntlDateFormatter::MEDIUM];
        yield 'short' => [DateFormat::Short, \IntlDateFormatter::SHORT];
        // Iso ignores the length (it uses a fixed pattern) but still returns a valid one.
        yield 'iso' => [DateFormat::Iso, \IntlDateFormatter::MEDIUM];
    }

    public function testOnlyIsoCarriesAFixedPattern(): void
    {
        self::assertSame('yyyy-MM-dd', DateFormat::Iso->pattern());
        // The locale-aware styles defer to their length in the ambient locale, so carry no pattern.
        self::assertNull(DateFormat::Long->pattern());
        self::assertNull(DateFormat::Medium->pattern());
        self::assertNull(DateFormat::Short->pattern());
    }

    public function testOnlyIsoCarriesAFixedDateTimePattern(): void
    {
        // The audit log renders a datetime; ISO pins a 24h order, the locale-aware styles defer.
        self::assertSame('yyyy-MM-dd HH:mm', DateFormat::Iso->dateTimePattern());
        self::assertNull(DateFormat::Long->dateTimePattern());
        self::assertNull(DateFormat::Medium->dateTimePattern());
        self::assertNull(DateFormat::Short->dateTimePattern());
    }

    public function testReturnsATranslationKeyPerStyle(): void
    {
        self::assertSame('enum.date_format.long', DateFormat::Long->label());
        self::assertSame('enum.date_format.medium', DateFormat::Medium->label());
        self::assertSame('enum.date_format.short', DateFormat::Short->label());
        self::assertSame('enum.date_format.iso', DateFormat::Iso->label());
    }
}
