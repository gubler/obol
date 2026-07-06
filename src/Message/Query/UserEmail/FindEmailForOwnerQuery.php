<?php

// ABOUTME: Query message for a single user-email row, scoped to its owner.
// ABOUTME: Dispatched via query.bus and handled by FindEmailForOwnerRunner; returns null cross-owner.

declare(strict_types=1);

namespace App\Message\Query\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class FindEmailForOwnerQuery
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $userEmailId,
    ) {
    }
}
