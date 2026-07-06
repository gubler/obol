<?php

// ABOUTME: Query message for a single passkey, scoped to its owner.
// ABOUTME: Dispatched via query.bus and handled by FindPasskeyForOwnerRunner; returns null cross-owner.

declare(strict_types=1);

namespace App\Message\Query\PasskeyCredential;

use Symfony\Component\Uid\Ulid;

final readonly class FindPasskeyForOwnerQuery
{
    public function __construct(
        public Ulid $credentialId,
        public Ulid $ownerUserId,
    ) {
    }
}
