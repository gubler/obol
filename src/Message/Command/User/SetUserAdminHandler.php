<?php

// ABOUTME: Handler for SetUserAdminCommand - resolves the account by email and grants/revokes ROLE_ADMIN.
// ABOUTME: Throws UserNotFoundException for an unknown email so the console reports it and exits non-zero.

declare(strict_types=1);

namespace App\Message\Command\User;

use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: SetUserAdminCommand::class)]
final readonly class SetUserAdminHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SetUserAdminCommand $command): void
    {
        $user = $this->userRepository->findForEmail($command->email);

        if (!$user instanceof User) {
            throw new UserNotFoundException($command->email);
        }

        if ($command->admin) {
            $user->grantAdmin();
        } else {
            // Never leave the system without an admin (ADR-0019); the repository refuses to de-admin the
            // last one, so this throws rather than locking everyone out of the operator surface.
            $this->userRepository->assertNotLastAdmin($user);
            $user->revokeAdmin();
        }

        $this->entityManager->flush();
    }
}
