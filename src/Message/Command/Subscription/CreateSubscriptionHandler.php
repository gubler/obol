<?php

// ABOUTME: Handler for CreateSubscriptionCommand that creates new subscription entities.
// ABOUTME: Validates data via entity constructor and persists to database via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Entity\Subscription;
use App\Repository\CategoryRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreateSubscriptionCommand::class)]
final readonly class CreateSubscriptionHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private SubscriptionChangeNotifierInterface $subscriptionChangeNotifier,
    ) {
    }

    public function __invoke(CreateSubscriptionCommand $command): void
    {
        // A subscription may be uncategorized; only a given-but-missing category is an error.
        $category = null;
        if (null !== $command->categoryId) {
            $category = $this->categoryRepository->find($command->categoryId);

            if (null === $category) {
                throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
            }
        }

        $subscription = new Subscription(
            category: $category,
            name: $command->name,
            nextRenewal: $command->nextRenewal,
            paymentPeriod: $command->paymentPeriod,
            paymentPeriodCount: $command->paymentPeriodCount,
            cost: new Money($command->cost, $command->currency),
            description: $command->description,
            link: $command->link,
            logo: $command->logo,
            color: $command->color,
        );

        $this->entityManager->persist($subscription);

        $this->subscriptionChangeNotifier->notifyChanged();
    }
}
