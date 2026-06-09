<?php

// ABOUTME: Read model for the homepage listing: subscriptions grouped by category and as a flat list.
// ABOUTME: Both views share one sort order - groups keep it within each category, the flat list applies it globally.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Subscription;

final readonly class HomepageListing
{
    /**
     * @param list<CategoryGroup> $groups        category groups, ordered by category name
     * @param list<Subscription>  $subscriptions every subscription, in the chosen sort order
     */
    public function __construct(
        public array $groups,
        public array $subscriptions,
    ) {
    }
}
