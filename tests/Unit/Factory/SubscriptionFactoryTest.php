<?php

// ABOUTME: Unit tests for SubscriptionFactory ensuring proper factory defaults and state methods.
// ABOUTME: Tests verify subscription creation, custom overrides, and factory state methods.

declare(strict_types=1);

use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

uses(KernelTestCase::class);

test('creates subscription with required fields', function (): void {
    $subscription = SubscriptionFactory::createOne();

    expect($subscription->name)->not->toBeEmpty()
        ->and($subscription->cost)->toBeGreaterThan(0)
        ->and($subscription->paymentPeriodCount)->toBeGreaterThan(0)
        ->and($subscription->archived)->toBeFalse()
        ->and($subscription->payments)->toHaveCount(0)
        ->and($subscription->subscriptionEvents)->toHaveCount(0)
    ;
});

test('allows custom name', function (): void {
    $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

    expect($subscription->name)->toBe('Netflix');
});

test('archived creates archived subscription', function (): void {
    $subscription = SubscriptionFactory::new()->archived()->create();

    expect($subscription->archived)->toBeTrue();
});

test('with recent payment creates subscription with recent payment', function (): void {
    $subscription = SubscriptionFactory::new()->withRecentPayment()->create();

    expect($subscription->payments)->toHaveCount(1);
});

test('expensive subscription creates subscription with high cost', function (): void {
    $subscription = SubscriptionFactory::new()->expensiveSubscription()->create();

    expect($subscription->cost)->toBeGreaterThanOrEqual(5000);
});
