<?php

// ABOUTME: Data Transfer Object for creating a new category.
// ABOUTME: Maps to CreateCategoryType form and provides data for CreateCategoryCommand.

declare(strict_types=1);

namespace App\Dto;

final readonly class CreateCategoryDto
{
    public function __construct(
        public string $name = '',
    ) {
    }
}