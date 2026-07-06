<?php

// ABOUTME: Runner for FindUserEmailByIdQuery - a plain by-id lookup for the public verify link.
// ABOUTME: Not owner-scoped: the signed URL is the authority, and the clicker may be logged out.

declare(strict_types=1);

namespace App\Message\Query\UserEmail;

use App\Entity\UserEmail;
use App\Repository\UserEmailRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindUserEmailByIdQuery::class)]
final readonly class FindUserEmailByIdRunner
{
    public function __construct(
        private UserEmailRepository $userEmails,
    ) {
    }

    public function __invoke(FindUserEmailByIdQuery $query): ?UserEmail
    {
        return $this->userEmails->find($query->userEmailId);
    }
}
