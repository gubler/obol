<?php

// ABOUTME: Runner for FindEmailsForUserQuery - returns the user's addresses, primary then verified then pending.
// ABOUTME: Owner-scoped read backing the /account/emails management page.

declare(strict_types=1);

namespace App\Message\Query\UserEmail;

use App\Entity\UserEmail;
use App\Repository\UserEmailRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindEmailsForUserQuery::class)]
final readonly class FindEmailsForUserRunner
{
    public function __construct(
        private UserEmailRepository $userEmails,
    ) {
    }

    /**
     * @return list<UserEmail>
     */
    public function __invoke(FindEmailsForUserQuery $query): array
    {
        return $this->userEmails->findForOwnerId($query->ownerUserId);
    }
}
