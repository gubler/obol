<?php

// ABOUTME: Read model pairing a category with its subscriptions for the homepage listing.
// ABOUTME: Carries the monthly total, and the savings total unless the owner hides savings (then null).

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Message\Currency\ConvertedTotal;
use App\ValueObject\CalendarDate;

final readonly class CategoryGroup
{
    /**
     * @param list<Subscription> $subscriptions
     */
    public function __construct(
        // Null is the uncategorized bucket: subscriptions with no category.
        public ?Category $category,
        public array $subscriptions,
        public ConvertedTotal $monthlyTotal,
        // Null when the owner's SavingsDisplay is Hidden: savings is suppressed and never computed.
        public ?ConvertedTotal $savingsTotal,
        public CalendarDate $asOf,
    ) {
    }
}
