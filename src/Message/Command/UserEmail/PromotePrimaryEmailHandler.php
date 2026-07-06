<?php

// ABOUTME: Handler that swaps the account's primary address to a verified secondary (the two-flush transaction).
// ABOUTME: Demote-flush lands before promote-flush so the partial unique index never sees two primaries at once.

declare(strict_types=1);

namespace App\Message\Command\UserEmail;

use App\Entity\UserEmail;
use App\Exception\CannotPromoteUnverifiedEmailException;
use App\Exception\EmailAlreadyPrimaryException;
use App\Repository\UserEmailRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: PromotePrimaryEmailCommand::class)]
final readonly class PromotePrimaryEmailHandler
{
    public function __construct(
        private UserEmailRepository $userEmails,
    ) {
    }

    public function __invoke(PromotePrimaryEmailCommand $command): void
    {
        // Owner-scoped: a wrong-owner or unknown id is a bug (the controller already 404'd on null).
        $newPrimary = $this->userEmails->findForOwner($command->userEmailId, $command->ownerUserId);
        if (!$newPrimary instanceof UserEmail) {
            throw new \InvalidArgumentException(\sprintf('UserEmail with ID "%s" not found.', $command->userEmailId));
        }

        if (!$newPrimary->isVerified()) {
            throw new CannotPromoteUnverifiedEmailException();
        }

        if ($newPrimary->isPrimary) {
            throw new EmailAlreadyPrimaryException();
        }

        $priorPrimary = $this->userEmails->findPrimaryForUser($newPrimary->user);

        // The partial unique index (user_id) WHERE is_primary is checked per-statement (Postgres cannot
        // make a partial unique index deferrable), so a single flush that both demotes and promotes risks
        // a transient two-primaries violation. Flush the demotion first, then the promotion; both ride the
        // command bus's doctrine_transaction, so the pair is atomic to any concurrent reader.
        $priorPrimary->unmarkPrimary();

        $this->userEmails->flush();

        $newPrimary->markPrimary();
        // Refresh the denormalized session-identity cache. Because this mutates the same managed User the
        // security token holds, the acting session keeps working; only cookies signed on the old primary
        // (other devices) fall away.
        $newPrimary->user->syncPrimaryEmailCache();

        $this->userEmails->flush();
    }
}
