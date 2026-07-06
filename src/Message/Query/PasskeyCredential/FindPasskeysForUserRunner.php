<?php

// ABOUTME: Runner for FindPasskeysForUserQuery - returns the user's passkeys, newest first.
// ABOUTME: Owner-scoped read backing the /account/passkeys management page.

declare(strict_types=1);

namespace App\Message\Query\PasskeyCredential;

use App\Entity\PasskeyCredential;
use App\Repository\PasskeyCredentialRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindPasskeysForUserQuery::class)]
final readonly class FindPasskeysForUserRunner
{
    public function __construct(
        private PasskeyCredentialRepository $passkeyCredentials,
    ) {
    }

    /**
     * @return list<PasskeyCredential>
     */
    public function __invoke(FindPasskeysForUserQuery $query): array
    {
        return $this->passkeyCredentials->findForOwnerId($query->ownerUserId);
    }
}
