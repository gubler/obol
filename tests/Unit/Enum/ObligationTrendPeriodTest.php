<?php

// ABOUTME: Unit tests for ObligationTrendPeriod - the week/month/year granularity of the obligation trend.
// ABOUTME: Covers query resolution (with the Month default), the per-granularity label, and the lookback length.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ObligationTrendPeriod;
use PHPUnit\Framework\TestCase;

final class ObligationTrendPeriodTest extends TestCase
{
    public function testResolvesAQueryValueDefaultingToMonthForUnknownOrMissingInput(): void
    {
        self::assertSame(ObligationTrendPeriod::Week, ObligationTrendPeriod::fromQuery('week'));
        self::assertSame(ObligationTrendPeriod::Month, ObligationTrendPeriod::fromQuery('month'));
        self::assertSame(ObligationTrendPeriod::Year, ObligationTrendPeriod::fromQuery('year'));
        self::assertSame(ObligationTrendPeriod::Month, ObligationTrendPeriod::fromQuery('day'));
        self::assertSame(ObligationTrendPeriod::Month, ObligationTrendPeriod::fromQuery('nonsense'));
        self::assertSame(ObligationTrendPeriod::Month, ObligationTrendPeriod::fromQuery(null));
    }

    public function testLabelsEachGranularityForTheToggle(): void
    {
        self::assertSame('Weekly', ObligationTrendPeriod::Week->label());
        self::assertSame('Monthly', ObligationTrendPeriod::Month->label());
        self::assertSame('Yearly', ObligationTrendPeriod::Year->label());
    }

    public function testLooksBackOver52Weeks24MonthsOr10Years(): void
    {
        self::assertSame(52, ObligationTrendPeriod::Week->lookback());
        self::assertSame(24, ObligationTrendPeriod::Month->lookback());
        self::assertSame(10, ObligationTrendPeriod::Year->lookback());
    }
}
