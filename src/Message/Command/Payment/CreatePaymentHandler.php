<?php

// ABOUTME: Handler for CreatePaymentCommand that records a payment on a subscription.
// ABOUTME: Finds subscription by ID, calls recordPayment with Verified type, and flushes.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use App\Enum\PaymentType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'command.bus', handles: CreatePaymentCommand::class)]
final readonly class CreatePaymentHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreatePaymentCommand $command): void
    {
        $subscription = $this->subscriptionRepository->find(Ulid::fromString($command->subscriptionId));

        if (null === $subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        $subscription->recordPayment(
            paidDate: $command->paidDate,
            paymentType: PaymentType::Verified,
            amount: $command->amount,
        );

        $this->entityManager->flush();
    }
}
