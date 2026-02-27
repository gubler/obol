<?php

// ABOUTME: Runner for FindCategoryQuery that retrieves a single category by ID.
// ABOUTME: Returns Category entity or null if not found or ID is invalid.

declare(strict_types=1);

namespace App\Message\Query\Category;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'query.bus', handles: FindCategoryQuery::class)]
final readonly class FindCategoryRunner
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    public function __invoke(FindCategoryQuery $query): ?Category
    {
        if (!Ulid::isValid($query->categoryId)) {
            return null;
        }

        return $this->categoryRepository->find(Ulid::fromString($query->categoryId));
    }
}
