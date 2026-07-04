<?php

// ABOUTME: Handler for DeleteCategoryCommand that removes category entities.
// ABOUTME: Validates category has no subscriptions before deletion, throws exception if it does.

declare(strict_types=1);

namespace App\Message\Command\Category;

use App\Exception\CategoryHasSubscriptionsException;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: DeleteCategoryCommand::class)]
final readonly class DeleteCategoryHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeleteCategoryCommand $command): void
    {
        $category = $this->categoryRepository->findForOwner($command->categoryId, $command->ownerUserId);

        if (!$category instanceof \App\Entity\Category) {
            throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
        }

        if ($category->subscriptions->count() > 0) {
            throw new CategoryHasSubscriptionsException((string) $command->categoryId);
        }

        $this->entityManager->remove($category);
    }
}
