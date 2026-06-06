<?php

// ABOUTME: Runner for FindCategoryQuery that retrieves a single category by ID.
// ABOUTME: Returns the Category entity, or null when not found.

declare(strict_types=1);

namespace App\Message\Query\Category;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindCategoryQuery::class)]
final readonly class FindCategoryRunner
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    public function __invoke(FindCategoryQuery $query): ?Category
    {
        return $this->categoryRepository->find($query->categoryId);
    }
}
