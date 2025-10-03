<?php

// ABOUTME: Command message for deleting a category.
// ABOUTME: Dispatched via command.bus and handled by DeleteCategoryHandler.

declare(strict_types=1);

namespace App\Message\Command;

final readonly class DeleteCategoryCommand
{
    public function __construct(
        public string $categoryId,
    ) {
    }
}