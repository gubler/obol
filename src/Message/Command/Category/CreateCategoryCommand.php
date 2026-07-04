<?php

// ABOUTME: Command message for creating a new category.
// ABOUTME: Dispatched via command.bus and handled by CreateCategoryHandler.

declare(strict_types=1);

namespace App\Message\Command\Category;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use Symfony\Component\Uid\Ulid;

final readonly class CreateCategoryCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public string $name,
        public TileColor $color,
        public CategoryIcon $icon,
    ) {
    }
}
