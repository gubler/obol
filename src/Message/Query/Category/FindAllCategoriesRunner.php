<?php

// ABOUTME: Runner for FindAllCategoriesQuery that retrieves all categories.
// ABOUTME: Returns array of Category entities ordered by name.

declare(strict_types=1);

namespace App\Message\Query\Category;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindAllCategoriesQuery::class)]
final readonly class FindAllCategoriesRunner
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @return array<Category>
     */
    public function __invoke(FindAllCategoriesQuery $query): array
    {
        return $this->categoryRepository->findBy([], ['name' => 'ASC']);
    }
}
