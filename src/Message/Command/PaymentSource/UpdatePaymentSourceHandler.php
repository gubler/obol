<?php

// ABOUTME: Handler for UpdatePaymentSourceCommand that updates existing payment source entities.
// ABOUTME: Finds the source by ID and applies the change; the command bus commits it.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use App\Repository\PaymentSourceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: UpdatePaymentSourceCommand::class)]
final readonly class UpdatePaymentSourceHandler
{
    public function __construct(
        private PaymentSourceRepository $paymentSourceRepository,
    ) {
    }

    public function __invoke(UpdatePaymentSourceCommand $command): void
    {
        $source = $this->paymentSourceRepository->find($command->paymentSourceId);

        if (null === $source) {
            throw new \InvalidArgumentException(\sprintf('Payment source with ID "%s" not found.', $command->paymentSourceId));
        }

        $source->update($command->name, $command->comment, $command->color);
    }
}
