<?php

// ABOUTME: Query message for a single user-email row by id, without owner scoping.
// ABOUTME: Backs the public verification controller, where the clicker may not be logged in.

declare(strict_types=1);

namespace App\Message\Query\UserEmail;

use Symfony\Component\Uid\Ulid;

final readonly class FindUserEmailByIdQuery
{
    public function __construct(
        public Ulid $userEmailId,
    ) {
    }
}
