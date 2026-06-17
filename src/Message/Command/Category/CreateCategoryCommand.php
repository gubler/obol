<?php

// ABOUTME: Command message for creating a new category.
// ABOUTME: Dispatched via command.bus and handled by CreateCategoryHandler.

declare(strict_types=1);

namespace App\Message\Command\Category;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;

final readonly class CreateCategoryCommand
{
    public function __construct(
        public string $name,
        public TileColor $color,
        public CategoryIcon $icon,
    ) {
    }
}
