<?php

// ABOUTME: Handler for ReassignPaymentSourceSubscriptionsCommand that moves every subscription to a new source.
// ABOUTME: Each moved subscription records its own Update audit event; the command bus commits the change.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use App\Repository\PaymentSourceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: ReassignPaymentSourceSubscriptionsCommand::class)]
final readonly class ReassignPaymentSourceSubscriptionsHandler
{
    public function __construct(
        private PaymentSourceRepository $paymentSourceRepository,
    ) {
    }

    public function __invoke(ReassignPaymentSourceSubscriptionsCommand $command): void
    {
        $from = $this->paymentSourceRepository->find($command->fromPaymentSourceId);

        if (null === $from) {
            throw new \InvalidArgumentException(\sprintf('Payment source with ID "%s" not found.', $command->fromPaymentSourceId));
        }

        $to = $this->paymentSourceRepository->find($command->toPaymentSourceId);

        if (null === $to) {
            throw new \InvalidArgumentException(\sprintf('Payment source with ID "%s" not found.', $command->toPaymentSourceId));
        }

        // Reassigning the source leaves every subscription's obligation untouched, so this does not
        // announce a SubscriptionsChanged event; each subscription records its own audit entry.
        foreach ($from->subscriptions as $subscription) {
            $subscription->reassignPaymentSource($to);
        }
    }
}
