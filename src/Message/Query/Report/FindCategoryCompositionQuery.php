<?php

// ABOUTME: Query for the category-composition pie: each category's share of the monthly obligation.
// ABOUTME: Dispatched via query.bus and handled by FindCategoryCompositionRunner.

declare(strict_types=1);

namespace App\Message\Query\Report;

use Symfony\Component\Uid\Ulid;

final readonly class FindCategoryCompositionQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
