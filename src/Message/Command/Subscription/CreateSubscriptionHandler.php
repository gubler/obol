<?php

// ABOUTME: Handler for CreateSubscriptionCommand that creates new subscription entities.
// ABOUTME: Validates data via entity constructor and persists to database via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Entity\Subscription;
use App\Repository\CategoryRepository;
use App\Repository\PaymentSourceRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreateSubscriptionCommand::class)]
final readonly class CreateSubscriptionHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private PaymentSourceRepository $paymentSourceRepository,
        private EntityManagerInterface $entityManager,
        private SubscriptionChangeNotifierInterface $subscriptionChangeNotifier,
    ) {
    }

    public function __invoke(CreateSubscriptionCommand $command): void
    {
        // A subscription may be uncategorized; only a given-but-missing category is an error.
        $category = null;
        if ($command->categoryId instanceof \Symfony\Component\Uid\Ulid) {
            $category = $this->categoryRepository->find($command->categoryId);

            if (null === $category) {
                throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
            }
        }

        // A subscription may be unassigned; only a given-but-missing payment source is an error.
        $paymentSource = null;
        if ($command->paymentSourceId instanceof \Symfony\Component\Uid\Ulid) {
            $paymentSource = $this->paymentSourceRepository->find($command->paymentSourceId);

            if (null === $paymentSource) {
                throw new \InvalidArgumentException(\sprintf('Payment source with ID "%s" not found.', $command->paymentSourceId));
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
            paymentSource: $paymentSource,
        );

        $this->entityManager->persist($subscription);

        $this->subscriptionChangeNotifier->notifyChanged();
    }
}
