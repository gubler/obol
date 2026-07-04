<?php

// ABOUTME: Handler for CreateCategoryCommand that creates new category entities.
// ABOUTME: Validates name via entity constructor and persists to database via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Category;

use App\Entity\Category;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreateCategoryCommand::class)]
final readonly class CreateCategoryHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateCategoryCommand $command): void
    {
        $owner = $this->userRepository->getForId($command->ownerUserId);

        $category = new Category(owner: $owner, name: $command->name, color: $command->color, icon: $command->icon);

        $this->entityManager->persist($category);
    }
}
