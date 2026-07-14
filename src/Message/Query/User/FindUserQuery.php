<?php

// ABOUTME: Query message for a single account by id - the admin user detail (any account, not owner-scoped).

declare(strict_types=1);

namespace App\Message\Query\User;

use Symfony\Component\Uid\Ulid;

final readonly class FindUserQuery
{
    public function __construct(
        public Ulid $userId,
    ) {
    }
}
