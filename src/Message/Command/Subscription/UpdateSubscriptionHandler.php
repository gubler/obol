<?php

// ABOUTME: Handler for UpdateSubscriptionCommand that updates existing subscription entities.
// ABOUTME: Finds subscription by ID and updates all fields, flushing changes via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'command.bus', handles: UpdateSubscriptionCommand::class)]
final readonly class UpdateSubscriptionHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(UpdateSubscriptionCommand $command): void
    {
        $subscription = $this->subscriptionRepository->find(Ulid::fromString($command->subscriptionId));

        if (null === $subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        $category = $this->categoryRepository->find(Ulid::fromString($command->categoryId));

        if (null === $category) {
            throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
        }

        $subscription->update(
            category: $category,
            name: $command->name,
            lastPaidDate: $command->lastPaidDate,
            description: $command->description,
            link: $command->link,
            logo: $command->logo,
            paymentPeriod: $command->paymentPeriod,
            paymentPeriodCount: $command->paymentPeriodCount,
            cost: $command->cost,
        );

        $this->entityManager->flush();
    }
}
