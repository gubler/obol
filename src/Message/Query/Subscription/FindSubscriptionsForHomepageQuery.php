<?php

// ABOUTME: Query for the homepage subscription listing, optionally including archived subscriptions.
// ABOUTME: Handled by FindSubscriptionsForHomepageRunner, which returns CategoryGroup read models.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Enum\SubscriptionSort;

final readonly class FindSubscriptionsForHomepageQuery
{
    public function __construct(
        public bool $includeArchived = false,
        public SubscriptionSort $sort = SubscriptionSort::Name,
    ) {
    }
}
