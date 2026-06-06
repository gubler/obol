<?php

// ABOUTME: Unit tests for FindSubscriptionQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with subscription ID and maintains readonly properties.

declare(strict_types=1);

use App\Message\Query\Subscription\FindSubscriptionQuery;
use Symfony\Component\Uid\Ulid;

test('creates query with subscription id', function (): void {
    $subscriptionId = new Ulid();
    $query = new FindSubscriptionQuery(subscriptionId: $subscriptionId);

    expect($query->subscriptionId)->toBe($subscriptionId);
});

test('is readonly', function (): void {
    $query = new FindSubscriptionQuery(
        subscriptionId: new Ulid()
    );

    $reflection = new ReflectionClass($query);
    expect($reflection->isReadOnly())->toBeTrue();
});
