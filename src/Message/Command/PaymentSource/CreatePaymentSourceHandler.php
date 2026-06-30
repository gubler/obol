<?php

// ABOUTME: Handler for CreatePaymentSourceCommand that creates new payment source entities.
// ABOUTME: Validates the name via the entity constructor and persists via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use App\Entity\PaymentSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreatePaymentSourceCommand::class)]
final readonly class CreatePaymentSourceHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreatePaymentSourceCommand $command): void
    {
        $source = new PaymentSource(name: $command->name, comment: $command->comment, color: $command->color);

        $this->entityManager->persist($source);
    }
}
