<?php

// ABOUTME: A page of the admin user list - the matching accounts plus the paging metadata to render controls.
// ABOUTME: Returned by SearchUsersRunner; a read model, not an entity.

declare(strict_types=1);

namespace App\Message\Query\User;

use App\Entity\User;

final readonly class UsersPage
{
    /**
     * @param list<User> $users the accounts on this page
     */
    public function __construct(
        public array $users,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pageCount();
    }
}
