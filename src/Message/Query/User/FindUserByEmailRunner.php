<?php

// ABOUTME: Runner for FindUserByEmailQuery - resolves the owning account via any verified UserEmail, or null.

declare(strict_types=1);

namespace App\Message\Query\User;

use App\Entity\User;
use App\Repository\UserEmailRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindUserByEmailQuery::class)]
final readonly class FindUserByEmailRunner
{
    public function __construct(
        private UserEmailRepository $userEmailRepository,
    ) {
    }

    public function __invoke(FindUserByEmailQuery $query): ?User
    {
        return $this->userEmailRepository->findVerifiedByEmail($query->email)?->user;
    }
}
