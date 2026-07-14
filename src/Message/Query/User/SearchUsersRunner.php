<?php

// ABOUTME: Runner for SearchUsersQuery - returns one page of matching accounts plus the total, for the admin list.

declare(strict_types=1);

namespace App\Message\Query\User;

use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: SearchUsersQuery::class)]
final readonly class SearchUsersRunner
{
    public function __construct(
        private UserRepository $userRepository,
        #[Autowire(param: 'app.admin.users_per_page')]
        private int $perPage,
    ) {
    }

    public function __invoke(SearchUsersQuery $query): UsersPage
    {
        $page = max(1, $query->page);
        $total = $this->userRepository->countMatching($query->search);
        $users = $this->userRepository->findMatching($query->search, $this->perPage, ($page - 1) * $this->perPage);

        return new UsersPage(users: $users, total: $total, page: $page, perPage: $this->perPage);
    }
}
