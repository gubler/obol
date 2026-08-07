<?php

// ABOUTME: Handler for RevokePasskeyCommand - removes an owner-scoped passkey.
// ABOUTME: Resolves the credential inside the owner's tenancy; a missing/cross-owner id is a bug (the controller already 404'd).

declare(strict_types=1);

namespace App\Message\Command\PasskeyCredential;

use App\Entity\PasskeyCredential;
use App\Repository\PasskeyCredentialRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: RevokePasskeyCommand::class)]
final readonly class RevokePasskeyHandler
{
    public function __construct(
        private PasskeyCredentialRepository $passkeyCredentials,
    ) {
    }

    public function __invoke(RevokePasskeyCommand $command): void
    {
        $credential = $this->passkeyCredentials->findForOwner($command->credentialId, $command->ownerUserId);

        if (!$credential instanceof PasskeyCredential) {
            throw new \InvalidArgumentException(\sprintf('Passkey with ID "%s" not found.', $command->credentialId));
        }

        // @igor-ignore - Delegates to the entity manager, which is reset per request via the kernel.reset-tagged registry.
        $this->passkeyCredentials->remove($credential);
    }
}
