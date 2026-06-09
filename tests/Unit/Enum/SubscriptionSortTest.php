<?php

// ABOUTME: Unit tests for the SubscriptionSort enum covering request parsing and labels.
// ABOUTME: Asserts the default fallback and that every case carries a human-readable label.

declare(strict_types=1);

use App\Enum\SubscriptionSort;

test('parses a known sort value', function (): void {
    expect(SubscriptionSort::fromQuery('renewal'))->toBe(SubscriptionSort::Renewal)
        ->and(SubscriptionSort::fromQuery('monthly-cost'))->toBe(SubscriptionSort::MonthlyCost)
        ->and(SubscriptionSort::fromQuery('cost'))->toBe(SubscriptionSort::Cost)
        ->and(SubscriptionSort::fromQuery('name'))->toBe(SubscriptionSort::Name)
    ;
});

test('falls back to name for an unknown or missing value', function (): void {
    expect(SubscriptionSort::fromQuery(null))->toBe(SubscriptionSort::Name)
        ->and(SubscriptionSort::fromQuery(''))->toBe(SubscriptionSort::Name)
        ->and(SubscriptionSort::fromQuery('bogus'))->toBe(SubscriptionSort::Name)
    ;
});

test('every case has a label', function (): void {
    foreach (SubscriptionSort::cases() as $case) {
        expect($case->label())->toBeString()->not->toBe('');
    }
});
