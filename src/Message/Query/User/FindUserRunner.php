<?php

// ABOUTME: Runner for FindUserQuery - resolves an account by id, or null when absent (the controller 404s).

declare(strict_types=1);

namespace App\Message\Query\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindUserQuery::class)]
final readonly class FindUserRunner
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(FindUserQuery $query): ?User
    {
        return $this->userRepository->find($query->userId);
    }
}
