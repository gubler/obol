<?php

// ABOUTME: Handler for UpdateCategoryCommand that updates existing category entities.
// ABOUTME: Finds category by ID and updates name, flushing changes via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Category;

use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'command.bus', handles: UpdateCategoryCommand::class)]
final readonly class UpdateCategoryHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(UpdateCategoryCommand $command): void
    {
        $category = $this->categoryRepository->find(Ulid::fromString($command->categoryId));

        if (null === $category) {
            throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
        }

        $category->setName($command->name);

        $this->entityManager->flush();
    }
}
