<?php

// ABOUTME: Runner for FindPasskeyForOwnerQuery - the owner-scoped single-passkey lookup.
// ABOUTME: Returns the PasskeyCredential, or null when it is absent or belongs to another user.

declare(strict_types=1);

namespace App\Message\Query\PasskeyCredential;

use App\Entity\PasskeyCredential;
use App\Repository\PasskeyCredentialRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindPasskeyForOwnerQuery::class)]
final readonly class FindPasskeyForOwnerRunner
{
    public function __construct(
        private PasskeyCredentialRepository $passkeyCredentials,
    ) {
    }

    public function __invoke(FindPasskeyForOwnerQuery $query): ?PasskeyCredential
    {
        return $this->passkeyCredentials->findForOwner($query->credentialId, $query->ownerUserId);
    }
}
