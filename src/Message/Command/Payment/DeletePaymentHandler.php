<?php

// ABOUTME: Handler for DeletePaymentCommand that deletes a subscription's latest payment.
// ABOUTME: Removing the latest payment switches the subscription to manual generation (ADR-0008).

declare(strict_types=1);

namespace App\Message\Command\Payment;

use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: DeletePaymentCommand::class)]
final readonly class DeletePaymentHandler
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeletePaymentCommand $command): void
    {
        $payment = $this->paymentRepository->find($command->paymentId);

        if (null === $payment) {
            throw new \InvalidArgumentException(\sprintf('Payment with ID "%s" not found.', $command->paymentId));
        }

        $payment->subscription->removeLatestPayment($payment);
        $this->entityManager->flush();
    }
}
