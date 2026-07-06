<?php

// ABOUTME: Handler that removes a secondary address, refusing to remove the primary.
// ABOUTME: The primary is always verified and unremovable, so a user always keeps at least one verified address.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use App\Entity\UserEmail;
use App\Exception\CannotRemovePrimaryEmailException;
use App\Repository\UserEmailRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: RemoveEmailCommand::class)]
final readonly class RemoveEmailHandler
{
    public function __construct(
        private UserEmailRepository $userEmails,
    ) {
    }

    public function __invoke(RemoveEmailCommand $command): void
    {
        // Owner-scoped: a wrong-owner or unknown id is a bug (the controller already 404'd on null).
        $userEmail = $this->userEmails->findForOwner($command->userEmailId, $command->ownerUserId);
        if (!$userEmail instanceof UserEmail) {
            throw new \InvalidArgumentException(\sprintf('UserEmail with ID "%s" not found.', $command->userEmailId));
        }

        if ($userEmail->isPrimary) {
            throw new CannotRemovePrimaryEmailException();
        }

        $this->userEmails->remove($userEmail);
    }
}
