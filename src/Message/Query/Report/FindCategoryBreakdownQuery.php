<?php

// ABOUTME: Query for one category's drill-down pie: its subscriptions' shares of the category obligation.
// ABOUTME: Carries the category Ulid; dispatched via query.bus and handled by FindCategoryBreakdownRunner.

declare(strict_types=1);

namespace App\Message\Query\Report;

use Symfony\Component\Uid\Ulid;

final readonly class FindCategoryBreakdownQuery
{
    public function __construct(
        public Ulid $categoryId,
    ) {
    }
}
