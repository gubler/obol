<?php

// ABOUTME: Unit tests for FindAllSubscriptionsQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates without parameters and maintains readonly properties.

declare(strict_types=1);

use App\Message\Query\Subscription\FindAllSubscriptionsQuery;

test('is readonly', function (): void {
    $query = new FindAllSubscriptionsQuery();

    $reflection = new ReflectionClass($query);
    expect($reflection->isReadOnly())->toBeTrue();
});
