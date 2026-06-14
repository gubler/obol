<?php

// ABOUTME: Handler for CreatePaymentCommand that records a payment on a subscription.
// ABOUTME: Finds subscription by ID and records a Verified payment; the command bus commits the change.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use App\Enum\PaymentType;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreatePaymentCommand::class)]
final readonly class CreatePaymentHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function __invoke(CreatePaymentCommand $command): void
    {
        $subscription = $this->subscriptionRepository->find($command->subscriptionId);

        if (null === $subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        // Record first: while the subscription is manual this leaves the anchor untouched. Resuming
        // afterwards sets the future anchor and flips back to automated.
        $subscription->recordPayment(
            paidDate: $command->paidDate,
            paymentType: PaymentType::Verified,
            amount: $command->amount,
        );

        if ($command->restartPaymentGeneration) {
            \assert(null !== $command->nextRenewal);
            $subscription->automatePayments($command->nextRenewal);
        }
    }
}
