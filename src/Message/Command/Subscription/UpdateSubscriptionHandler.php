<?php

// ABOUTME: Handler for UpdateSubscriptionCommand that updates existing subscription entities.
// ABOUTME: Finds subscription by ID and updates all fields; the command bus commits the change.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Repository\CategoryRepository;
use App\Repository\PaymentSourceRepository;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: UpdateSubscriptionCommand::class)]
final readonly class UpdateSubscriptionHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private CategoryRepository $categoryRepository,
        private PaymentSourceRepository $paymentSourceRepository,
        private SubscriptionChangeNotifierInterface $subscriptionChangeNotifier,
    ) {
    }

    public function __invoke(UpdateSubscriptionCommand $command): void
    {
        $subscription = $this->subscriptionRepository->findForOwner($command->subscriptionId, $command->ownerUserId);

        if (!$subscription instanceof \App\Entity\Subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
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

        $subscription->update(
            category: $category,
            name: $command->name,
            nextRenewal: $command->nextRenewal,
            description: $command->description,
            link: $command->link,
            logo: $command->logo,
            paymentPeriod: $command->paymentPeriod,
            paymentPeriodCount: $command->paymentPeriodCount,
            cost: new Money($command->cost, $command->currency),
            color: $command->color,
            paymentSource: $paymentSource,
        );

        // update() has already set the anchor; resuming re-anchors it and flips back to automated.
        if ($command->restartPaymentGeneration) {
            $subscription->automatePayments($command->nextRenewal);
        }

        $this->subscriptionChangeNotifier->notifyChanged($command->ownerUserId);
    }
}
