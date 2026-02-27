<?php

// ABOUTME: Query message for finding a single subscription by ID.
// ABOUTME: Dispatched via query.bus and handled by FindSubscriptionRunner.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

final readonly class FindSubscriptionQuery
{
    public function __construct(
        public string $subscriptionId,
    ) {
    }
}
