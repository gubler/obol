<?php

// ABOUTME: Query message for listing a user's registered passkeys.
// ABOUTME: Dispatched via query.bus and handled by FindPasskeysForUserRunner.

declare(strict_types=1);

namespace App\Message\Query\PasskeyCredential;

use Symfony\Component\Uid\Ulid;

final readonly class FindPasskeysForUserQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
