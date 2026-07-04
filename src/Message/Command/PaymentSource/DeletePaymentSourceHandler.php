<?php

// ABOUTME: Handler for DeletePaymentSourceCommand that removes payment source entities.
// ABOUTME: Guards against deleting a source that still has subscriptions assigned.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use App\Exception\PaymentSourceHasSubscriptionsException;
use App\Repository\PaymentSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: DeletePaymentSourceCommand::class)]
final readonly class DeletePaymentSourceHandler
{
    public function __construct(
        private PaymentSourceRepository $paymentSourceRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeletePaymentSourceCommand $command): void
    {
        $source = $this->paymentSourceRepository->findForOwner($command->paymentSourceId, $command->ownerUserId);

        if (!$source instanceof \App\Entity\PaymentSource) {
            throw new \InvalidArgumentException(\sprintf('Payment source with ID "%s" not found.', $command->paymentSourceId));
        }

        if ($source->subscriptions->count() > 0) {
            throw new PaymentSourceHasSubscriptionsException((string) $command->paymentSourceId);
        }

        $this->entityManager->remove($source);
    }
}
