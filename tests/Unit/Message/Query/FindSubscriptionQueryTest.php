<?php

// ABOUTME: Unit tests for FindSubscriptionQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with subscription ID and maintains readonly properties.

declare(strict_types=1);

use App\Message\Query\Subscription\FindSubscriptionQuery;

test('creates query with subscription id', function (): void {
    $subscriptionId = '01JKSUB1234567890ABCDEFGH';
    $query = new FindSubscriptionQuery(subscriptionId: $subscriptionId);

    expect($query->subscriptionId)->toBe($subscriptionId);
});

test('is readonly', function (): void {
    $query = new FindSubscriptionQuery(
        subscriptionId: '01JKSUB1234567890ABCDEFGH'
    );

    $reflection = new ReflectionClass($query);
    expect($reflection->isReadOnly())->toBeTrue();
});
