<?php

// ABOUTME: Unit tests for the PaymentPeriod enum's normalization factor.
// ABOUTME: monthsPerPeriod is the shared basis for monthlyCost and the report period scalings.

declare(strict_types=1);

use App\Enum\PaymentPeriod;

test('months per period reflects the normalization (year 12, month 1, week 12/52)', function (PaymentPeriod $period, float $expected): void {
    expect($period->monthsPerPeriod())->toBe($expected);
})->with([
    'year' => [PaymentPeriod::Year, 12.0],
    'month' => [PaymentPeriod::Month, 1.0],
    'week' => [PaymentPeriod::Week, 12.0 / 52.0],
]);
