<?php

// ABOUTME: Query for a page of accounts matching a search term - the admin user list (deliberately cross-owner).
// ABOUTME: Search matches a display name or any of a user's email addresses; blank search returns everyone.

declare(strict_types=1);

namespace App\Message\Query\User;

final readonly class SearchUsersQuery
{
    public function __construct(
        public string $search = '',
        public int $page = 1,
    ) {
    }
}
