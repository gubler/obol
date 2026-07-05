<?php

// ABOUTME: Handler for GenerateDuePaymentsCommand: records a Generated payment for every due subscription.
// ABOUTME: Due-ness (non-archived, automatic, renewal reached in the owner's zone) is decided by the finder.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use App\Enum\PaymentType;
use App\Repository\SubscriptionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: GenerateDuePaymentsCommand::class)]
final readonly class GenerateDuePaymentsHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GenerateDuePaymentsCommand $command): void
    {
        // The finder resolves due-ness in each owner's local timezone (ADR-0016). Recording a payment
        // advances nextRenewal, so a re-run picks up nothing already generated. This is a single
        // synchronous sweep on the one sequential worker; a future concurrent worker would need a claim
        // or row lock to stay idempotent, but the sequential drain makes that unnecessary today.
        foreach ($this->subscriptionRepository->findAllPendingPaymentGeneration($this->clock->now()) as $subscription) {
            $subscription->recordPayment(
                paidDate: $subscription->nextRenewal,
                paymentType: PaymentType::Generated,
            );
        }
    }
}
