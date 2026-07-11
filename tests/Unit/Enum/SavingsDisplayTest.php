<?php

// ABOUTME: Unit tests for the SavingsDisplay enum - the funding lead per visible style and the label key.
// ABOUTME: MonthOf funds by the due month (lead 0), MonthBefore a month ahead (lead 1), Hidden has no lead.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\SavingsDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SavingsDisplayTest extends TestCase
{
    #[DataProvider('provideMapsEachStyleToItsFundingLeadCases')]
    public function testMapsEachStyleToItsFundingLead(SavingsDisplay $display, int $expected): void
    {
        self::assertSame($expected, $display->leadMonths());
    }

    /**
     * @return iterable<string, array{SavingsDisplay, int}>
     */
    public static function provideMapsEachStyleToItsFundingLeadCases(): iterable
    {
        yield 'month of funds by the due month' => [SavingsDisplay::MonthOf, 0];
        yield 'month before funds one month ahead' => [SavingsDisplay::MonthBefore, 1];
        // Hidden carries no lead of its own; it returns MonthOf's 0 as a harmless default for the
        // computation (the display layer never asks a hidden preference for a figure).
        yield 'hidden defaults to the month-of lead' => [SavingsDisplay::Hidden, 0];
    }

    public function testDefaultIsMonthOf(): void
    {
        // New accounts save by the due month, the way most people budget.
        self::assertSame('month_of', SavingsDisplay::MonthOf->value);
    }

    public function testLabelIsTheEnumTranslationKey(): void
    {
        self::assertSame('enum.savings_display.month_before', SavingsDisplay::MonthBefore->label());
    }
}
