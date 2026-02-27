<?php

// ABOUTME: Handler for DeletePaymentCommand that removes a payment entity.
// ABOUTME: Finds payment by ID, removes it via entity manager, and flushes.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

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
        $payment = $this->paymentRepository->find(Ulid::fromString($command->paymentId));

        if (null === $payment) {
            throw new \InvalidArgumentException(\sprintf('Payment with ID "%s" not found.', $command->paymentId));
        }

        $this->entityManager->remove($payment);
        $this->entityManager->flush();
    }
}
