<?php

// ABOUTME: Handler for GenerateDuePaymentsCommand: records a Generated payment for every due active subscription.
// ABOUTME: A subscription is due when nextRenewal <= today; recording advances the anchor. The command bus commits.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use App\Enum\PaymentType;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: GenerateDuePaymentsCommand::class)]
final readonly class GenerateDuePaymentsHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function __invoke(GenerateDuePaymentsCommand $command): void
    {
        $today = new \DateTimeImmutable('today');

        foreach ($this->subscriptionRepository->findBy(['archived' => false]) as $subscription) {
            if ($subscription->generatesPaymentsAutomatically() && $subscription->nextRenewal <= $today) {
                $subscription->recordPayment(
                    paidDate: $subscription->nextRenewal,
                    paymentType: PaymentType::Generated,
                );
            }
        }
    }
}
