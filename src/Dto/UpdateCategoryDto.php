<?php

// ABOUTME: Data Transfer Object for updating an existing category.
// ABOUTME: Maps to UpdateCategoryType form and provides data for UpdateCategoryCommand.

declare(strict_types=1);

namespace App\Dto;

final readonly class UpdateCategoryDto
{
    public function __construct(
        public string $name = '',
    ) {
    }
}