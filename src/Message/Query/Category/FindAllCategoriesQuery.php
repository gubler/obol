<?php

// ABOUTME: Query message for finding all categories.
// ABOUTME: Dispatched via query.bus and handled by FindAllCategoriesRunner.

declare(strict_types=1);

namespace App\Message\Query\Category;

use Symfony\Component\Uid\Ulid;

final readonly class FindAllCategoriesQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
