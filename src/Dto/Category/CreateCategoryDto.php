<?php

// ABOUTME: Data Transfer Object for category creation containing form input data.
// ABOUTME: Used to transfer data from form submission to command handler via CreateCategoryCommand.

declare(strict_types=1);

namespace App\Dto\Category;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CreateCategoryDto
{
    #[NotBlank]
    #[Length(max: 255)]
    public string $name = '';

    public TileColor $color;

    public CategoryIcon $icon = CategoryIcon::Tag;

    public function __construct()
    {
        // Pre-select a random swatch so a new category always starts with a color.
        $this->color = TileColor::random();
    }
}
