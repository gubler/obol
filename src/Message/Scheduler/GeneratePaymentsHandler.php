<?php

// ABOUTME: Handler that generates payments for subscriptions whose renewal date has passed.
// ABOUTME: Records a Generated payment dated to the renewal date; the entity advances nextRenewal.

declare(strict_types=1);

namespace App\Message\Scheduler;

use App\Enum\PaymentType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: GeneratePaymentsMessage::class)]
final readonly class GeneratePaymentsHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GeneratePaymentsMessage $message): void
    {
        $subscriptions = $this->subscriptionRepository->findBy(['archived' => false]);

        $today = new \DateTimeImmutable('today');

        foreach ($subscriptions as $subscription) {
            if ($subscription->generatesPaymentsAutomatically() && $subscription->nextRenewal <= $today) {
                $subscription->recordPayment(
                    paidDate: $subscription->nextRenewal,
                    paymentType: PaymentType::Generated,
                );
            }
        }

        $this->entityManager->flush();
    }
}
