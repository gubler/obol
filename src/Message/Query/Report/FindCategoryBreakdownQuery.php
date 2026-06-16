<?php

// ABOUTME: Query for one category's drill-down pie: its subscriptions' shares of the category obligation.
// ABOUTME: Carries the category Ulid, or null for the uncategorized drill-down; handled by FindCategoryBreakdownRunner.

declare(strict_types=1);

namespace App\Message\Query\Report;

use Symfony\Component\Uid\Ulid;

final readonly class FindCategoryBreakdownQuery
{
    public function __construct(
        // Null selects the uncategorized drill-down (subscriptions with no category).
        public ?Ulid $categoryId,
    ) {
    }
}
