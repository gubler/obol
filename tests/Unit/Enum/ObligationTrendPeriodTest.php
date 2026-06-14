<?php

// ABOUTME: Unit tests for ObligationTrendPeriod - the day/week/month granularity of the obligation trend.
// ABOUTME: Covers query resolution (with the Month default) and the per-granularity label.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ObligationTrendPeriod;
use PHPUnit\Framework\TestCase;

final class ObligationTrendPeriodTest extends TestCase
{
    public function testResolvesAQueryValueDefaultingToMonthForUnknownOrMissingInput(): void
    {
        self::assertSame(ObligationTrendPeriod::Day, ObligationTrendPeriod::fromQuery('day'));
        self::assertSame(ObligationTrendPeriod::Week, ObligationTrendPeriod::fromQuery('week'));
        self::assertSame(ObligationTrendPeriod::Month, ObligationTrendPeriod::fromQuery('month'));
        self::assertSame(ObligationTrendPeriod::Month, ObligationTrendPeriod::fromQuery('nonsense'));
        self::assertSame(ObligationTrendPeriod::Month, ObligationTrendPeriod::fromQuery(null));
    }

    public function testLabelsEachGranularityForTheToggle(): void
    {
        self::assertSame('Daily', ObligationTrendPeriod::Day->label());
        self::assertSame('Weekly', ObligationTrendPeriod::Week->label());
        self::assertSame('Monthly', ObligationTrendPeriod::Month->label());
    }
}
