<?php

// ABOUTME: Runner for FindEmailForOwnerQuery - the owner-scoped single-address lookup.
// ABOUTME: Returns the UserEmail, or null when it is absent or belongs to another user.

declare(strict_types=1);

namespace App\Message\Query\UserEmail;

use App\Entity\UserEmail;
use App\Repository\UserEmailRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindEmailForOwnerQuery::class)]
final readonly class FindEmailForOwnerRunner
{
    public function __construct(
        private UserEmailRepository $userEmails,
    ) {
    }

    public function __invoke(FindEmailForOwnerQuery $query): ?UserEmail
    {
        return $this->userEmails->findForOwner($query->userEmailId, $query->ownerUserId);
    }
}
