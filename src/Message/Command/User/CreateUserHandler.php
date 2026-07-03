<?php

// ABOUTME: Handler for CreateUserCommand - builds a User plus its primary verified UserEmail and persists them.
// ABOUTME: The primary email is verified on creation; a seeded/console-created account can log in immediately.

declare(strict_types=1);

namespace App\Message\Command\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: CreateUserCommand::class)]
final readonly class CreateUserHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateUserCommand $command): User
    {
        // User's constructor creates its own primary verified UserEmail, cascade-persisted with it, so
        // there is nothing to assemble here - just build a valid User and persist.
        $user = new User(email: $command->email);
        $this->entityManager->persist($user);

        return $user;
    }
}
