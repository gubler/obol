<?php

// ABOUTME: Command message for updating an existing category.
// ABOUTME: Dispatched via command.bus and handled by UpdateCategoryHandler.

declare(strict_types=1);

namespace App\Message\Command\Category;

use Symfony\Component\Uid\Ulid;

final readonly class UpdateCategoryCommand
{
    public function __construct(
        public Ulid $categoryId,
        public string $name,
    ) {
    }
}
