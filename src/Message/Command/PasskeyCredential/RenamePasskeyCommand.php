<?php

// ABOUTME: Command message for renaming one of a user's passkeys.
// ABOUTME: Dispatched via command.bus and handled by RenamePasskeyHandler, which returns whether the name changed.

declare(strict_types=1);

namespace App\Message\Command\PasskeyCredential;

use Symfony\Component\Uid\Ulid;

final readonly class RenamePasskeyCommand
{
    public function __construct(
        public Ulid $credentialId,
        public Ulid $ownerUserId,
        public string $name,
    ) {
    }
}
