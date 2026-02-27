<?php

// ABOUTME: Query message for finding a single category by ID.
// ABOUTME: Dispatched via query.bus and handled by FindCategoryRunner.

declare(strict_types=1);

namespace App\Message\Query\Category;

final readonly class FindCategoryQuery
{
    public function __construct(
        public string $categoryId,
    ) {
    }
}
