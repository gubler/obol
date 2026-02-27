<?php

// ABOUTME: Handler that generates payments for subscriptions that are past due.
// ABOUTME: Computes next due date from lastPaidDate + (periodCount * period), creates Generated payments.

declare(strict_types=1);

namespace App\Message\Scheduler;

use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
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
            $nextDueDate = $this->calculateNextDueDate($subscription);

            if ($nextDueDate <= $today) {
                $subscription->recordPayment(
                    paidDate: $today,
                    paymentType: PaymentType::Generated,
                );
            }
        }

        $this->entityManager->flush();
    }

    private function calculateNextDueDate(Subscription $subscription): \DateTimeImmutable
    {
        $interval = match ($subscription->paymentPeriod) {
            PaymentPeriod::Week => \sprintf('P%dW', $subscription->paymentPeriodCount),
            PaymentPeriod::Month => \sprintf('P%dM', $subscription->paymentPeriodCount),
            PaymentPeriod::Year => \sprintf('P%dY', $subscription->paymentPeriodCount),
        };

        return $subscription->lastPaidDate->add(new \DateInterval($interval));
    }
}
