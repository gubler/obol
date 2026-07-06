<?php

// ABOUTME: Query message for listing every address on a user's account (primary + secondaries).
// ABOUTME: Dispatched via query.bus and handled by FindEmailsForUserRunner; backs /account/emails.

declare(strict_types=1);

namespace App\Message\Query\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class FindEmailsForUserQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
