<?php

// ABOUTME: Unit tests for ObligationTrendPeriod - the day/week/month granularity of the obligation trend.
// ABOUTME: Covers query resolution (with the Month default) and the per-granularity label.

declare(strict_types=1);

use App\Enum\ObligationTrendPeriod;

test('resolves a query value, defaulting to Month for unknown or missing input', function (): void {
    expect(ObligationTrendPeriod::fromQuery('day'))->toBe(ObligationTrendPeriod::Day)
        ->and(ObligationTrendPeriod::fromQuery('week'))->toBe(ObligationTrendPeriod::Week)
        ->and(ObligationTrendPeriod::fromQuery('month'))->toBe(ObligationTrendPeriod::Month)
        ->and(ObligationTrendPeriod::fromQuery('nonsense'))->toBe(ObligationTrendPeriod::Month)
        ->and(ObligationTrendPeriod::fromQuery(null))->toBe(ObligationTrendPeriod::Month)
    ;
});

test('labels each granularity for the toggle', function (): void {
    expect(ObligationTrendPeriod::Day->label())->toBe('Daily')
        ->and(ObligationTrendPeriod::Week->label())->toBe('Weekly')
        ->and(ObligationTrendPeriod::Month->label())->toBe('Monthly')
    ;
});
