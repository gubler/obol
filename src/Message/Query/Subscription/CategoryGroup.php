<?php

// ABOUTME: Read model pairing a category with its subscriptions for the homepage listing.
// ABOUTME: Carries the category's monthly and savings totals already converted to the display currency.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Message\Currency\ConvertedTotal;

final readonly class CategoryGroup
{
    /**
     * @param list<Subscription> $subscriptions
     */
    public function __construct(
        public Category $category,
        public array $subscriptions,
        public ConvertedTotal $monthlyTotal,
        public ConvertedTotal $savingsTotal,
        public \DateTimeImmutable $asOf,
    ) {
    }
}
