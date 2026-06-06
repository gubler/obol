<?php

// ABOUTME: Handler for CreateSubscriptionCommand that creates new subscription entities.
// ABOUTME: Validates data via entity constructor and persists to database via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Entity\Subscription;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreateSubscriptionCommand::class)]
final readonly class CreateSubscriptionHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateSubscriptionCommand $command): void
    {
        $category = $this->categoryRepository->find($command->categoryId);

        if (null === $category) {
            throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
        }

        $subscription = new Subscription(
            category: $category,
            name: $command->name,
            nextRenewal: $command->nextRenewal,
            paymentPeriod: $command->paymentPeriod,
            paymentPeriodCount: $command->paymentPeriodCount,
            cost: $command->cost,
            description: $command->description,
            link: $command->link,
            logo: $command->logo,
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }
}
