<?php

// ABOUTME: Data Transfer Object for category updates containing form input data.
// ABOUTME: Used to transfer data from edit form submission to command handler via UpdateCategoryCommand.

declare(strict_types=1);

namespace App\Dto\Category;

use App\Entity\Category;
use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UpdateCategoryDto
{
    #[NotBlank]
    #[Length(max: 255)]
    public string $name;

    public TileColor $color;

    public CategoryIcon $icon;

    public function __construct(Category $category)
    {
        $this->name = $category->name;
        $this->color = $category->color;
        $this->icon = $category->icon;
    }
}
