<?php

// ABOUTME: Handler for AmendPaymentCommand that validates or adjusts a payment.
// ABOUTME: Finds the payment, amends its amount and paid date (flipping it to Verified), and flushes.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: AmendPaymentCommand::class)]
final readonly class AmendPaymentHandler
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(AmendPaymentCommand $command): void
    {
        $payment = $this->paymentRepository->find($command->paymentId);

        if (null === $payment) {
            throw new \InvalidArgumentException(\sprintf('Payment with ID "%s" not found.', $command->paymentId));
        }

        $payment->amend(amount: $command->amount, paidDate: $command->paidDate);

        $this->entityManager->flush();
    }
}
