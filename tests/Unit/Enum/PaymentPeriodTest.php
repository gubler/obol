<?php

// ABOUTME: Unit tests for the PaymentPeriod enum's normalization factor.
// ABOUTME: monthsPerPeriod is the shared basis for monthlyCost and the report period scalings.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\PaymentPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentPeriodTest extends TestCase
{
    #[DataProvider('provideMonthsPerPeriodReflectsTheNormalizationCases')]
    public function testMonthsPerPeriodReflectsTheNormalization(PaymentPeriod $period, float $expected): void
    {
        self::assertSame($expected, $period->monthsPerPeriod());
    }

    public static function provideMonthsPerPeriodReflectsTheNormalizationCases(): iterable
    {
        return [
            'year' => [PaymentPeriod::Year, 12.0],
            'month' => [PaymentPeriod::Month, 1.0],
            'week' => [PaymentPeriod::Week, 12.0 / 52.0],
        ];
    }
}
