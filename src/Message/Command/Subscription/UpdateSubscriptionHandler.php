<?php

// ABOUTME: Handler for UpdateSubscriptionCommand that updates existing subscription entities.
// ABOUTME: Finds subscription by ID and updates all fields, flushing changes via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Enum\Currency;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
        $subscription = $this->subscriptionRepository->find($command->subscriptionId);

        if (null === $subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        $category = $this->categoryRepository->find($command->categoryId);

        if (null === $category) {
            throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
        }

        $subscription->update(
            category: $category,
            name: $command->name,
            nextRenewal: $command->nextRenewal,
            description: $command->description,
            link: $command->link,
            logo: $command->logo,
            paymentPeriod: $command->paymentPeriod,
            paymentPeriodCount: $command->paymentPeriodCount,
            // The edit form has no currency picker yet (A3, #129); default to USD for now.
            cost: new Money($command->cost, Currency::USD),
            color: $command->color,
        );

        // update() has already set the anchor; resuming re-anchors it and flips back to automated.
        if ($command->restartPaymentGeneration) {
            $subscription->automatePayments($command->nextRenewal);
        }

        $this->entityManager->flush();
    }
}
