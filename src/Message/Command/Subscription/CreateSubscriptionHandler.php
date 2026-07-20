<?php

// ABOUTME: Handler for CreateSubscriptionCommand that creates new subscription entities.
// ABOUTME: Validates data via entity constructor and persists to database via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Entity\Subscription;
use App\Repository\CategoryRepository;
use App\Repository\PaymentSourceRepository;
use App\Repository\UserRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreateSubscriptionCommand::class)]
final readonly class CreateSubscriptionHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private PaymentSourceRepository $paymentSourceRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private SubscriptionChangeNotifierInterface $subscriptionChangeNotifier,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateSubscriptionCommand $command): void
    {
        $owner = $this->userRepository->find($command->ownerUserId);

        if (null === $owner) {
            throw new \InvalidArgumentException(\sprintf('User with ID "%s" not found.', $command->ownerUserId));
        }

        // A subscription may be uncategorized; only a given-but-missing category is an error. Scoped to
        // the owner so a user cannot attach another user's category (cross-owner id reads as missing).
        $category = null;
        if ($command->categoryId instanceof \Symfony\Component\Uid\Ulid) {
            $category = $this->categoryRepository->findForOwner($command->categoryId, $command->ownerUserId);

            if (!$category instanceof \App\Entity\Category) {
                throw new \InvalidArgumentException(\sprintf('Category with ID "%s" not found.', $command->categoryId));
            }
        }

        // A subscription may be unassigned; only a given-but-missing payment source is an error. Scoped to
        // the owner so a user cannot attach another user's payment source.
        $paymentSource = null;
        if ($command->paymentSourceId instanceof \Symfony\Component\Uid\Ulid) {
            $paymentSource = $this->paymentSourceRepository->findForOwner($command->paymentSourceId, $command->ownerUserId);

            if (!$paymentSource instanceof \App\Entity\PaymentSource) {
                throw new \InvalidArgumentException(\sprintf('Payment source with ID "%s" not found.', $command->paymentSourceId));
            }
        }

        $subscription = new Subscription(
            owner: $owner,
            category: $category,
            name: $command->name,
            nextRenewal: $command->nextRenewal,
            paymentPeriod: $command->paymentPeriod,
            paymentPeriodCount: $command->paymentPeriodCount,
            cost: new Money($command->cost, $command->currency),
            now: $this->clock->now(),
            description: $command->description,
            link: $command->link,
            logo: $command->logo,
            color: $command->color,
            paymentSource: $paymentSource,
        );

        $this->entityManager->persist($subscription);

        $this->subscriptionChangeNotifier->notifyChanged($command->ownerUserId);
    }
}
