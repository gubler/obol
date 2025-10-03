<?php

// ABOUTME: Handler for CreateCategoryCommand that creates new category entities.
// ABOUTME: Validates name via entity constructor and persists to database via Doctrine.

declare(strict_types=1);

namespace App\Message\Command;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreateCategoryCommand::class)]
final readonly class CreateCategoryHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateCategoryCommand $command): void
    {
        $category = new Category(name: $command->name);

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }
}