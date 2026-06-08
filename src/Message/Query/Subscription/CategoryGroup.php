<?php

// ABOUTME: Read model pairing a category with its subscriptions for the homepage listing.
// ABOUTME: Exposes the category's combined monthly cost and combined savings target as of a fixed date.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;

final readonly class CategoryGroup
{
    /**
     * @param list<Subscription> $subscriptions
     */
    public function __construct(
        public Category $category,
        public array $subscriptions,
        private \DateTimeImmutable $asOf,
    ) {
    }

    /**
     * Combined monthly-equivalent cost of the group, in the currency's minor units.
     */
    public function monthlyTotal(): int
    {
        return array_sum(
            array_map(static fn (Subscription $subscription): int => $subscription->monthlyCost(), $this->subscriptions),
        );
    }

    /**
     * Combined savings target of the group as of `$asOf`, in the currency's minor units.
     */
    public function savingsTotal(): int
    {
        return array_sum(
            array_map(fn (Subscription $subscription): int => $subscription->savingsTarget($this->asOf), $this->subscriptions),
        );
    }
}
