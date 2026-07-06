<?php

// ABOUTME: Command message for revoking (deleting) one of a user's passkeys.
// ABOUTME: Dispatched via command.bus and handled by RevokePasskeyHandler.

declare(strict_types=1);

namespace App\Message\Command\PasskeyCredential;

use Symfony\Component\Uid\Ulid;

final readonly class RevokePasskeyCommand
{
    public function __construct(
        public Ulid $credentialId,
        public Ulid $ownerUserId,
    ) {
    }
}
