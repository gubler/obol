<?php

// ABOUTME: Handler for RenamePasskeyCommand - renames an owner-scoped passkey.
// ABOUTME: Returns true when the name actually changed (rename() is idempotent), so the controller flashes success only on a real change.

declare(strict_types=1);

namespace App\Message\Command\PasskeyCredential;

use App\Entity\PasskeyCredential;
use App\Repository\PasskeyCredentialRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: RenamePasskeyCommand::class)]
final readonly class RenamePasskeyHandler
{
    public function __construct(
        private PasskeyCredentialRepository $passkeyCredentials,
    ) {
    }

    public function __invoke(RenamePasskeyCommand $command): bool
    {
        $credential = $this->passkeyCredentials->findForOwner($command->credentialId, $command->ownerUserId);

        if (!$credential instanceof PasskeyCredential) {
            throw new \InvalidArgumentException(\sprintf('Passkey with ID "%s" not found.', $command->credentialId));
        }

        return $credential->rename($command->name);
    }
}
