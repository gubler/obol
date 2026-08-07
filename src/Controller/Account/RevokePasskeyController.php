<?php

// ABOUTME: POST /account/passkeys/{id}/delete - revoke a passkey.
// ABOUTME: Owner-scoped lookup (404 cross-owner); warns when the last passkey is removed.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Entity\PasskeyCredential;
use App\Message\Command\PasskeyCredential\RevokePasskeyCommand;
use App\Message\Query\PasskeyCredential\FindPasskeyForOwnerQuery;
use App\Message\Query\PasskeyCredential\FindPasskeysForUserQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class RevokePasskeyController extends AbstractBaseController
{
    #[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
    #[IsCsrfTokenValid(id: 'submit')]
    #[Route(path: '/app/account/passkeys/{id}/delete', name: 'account_passkey_revoke', methods: ['POST'])]
    public function __invoke(Ulid $id): RedirectResponse
    {
        $user = $this->currentUser();

        $credential = $this->queryBus->query(new FindPasskeyForOwnerQuery(credentialId: $id, ownerUserId: $user->id));
        if (!$credential instanceof PasskeyCredential) {
            throw new NotFoundHttpException(\sprintf('Passkey with ID "%s" not found.', $id));
        }

        $this->commandBus->dispatch(new RevokePasskeyCommand(credentialId: $id, ownerUserId: $user->id));

        /** @var list<PasskeyCredential> $remaining */
        $remaining = $this->queryBus->query(new FindPasskeysForUserQuery(ownerUserId: $user->id));
        if (0 === \count($remaining)) {
            $this->addFlash(self::FLASH_WARNING, $this->translator->trans('account.passkey.revoke.last_removed'));
        } else {
            $this->addFlash(self::FLASH_SUCCESS, $this->translator->trans('account.passkey.revoke.success'));
        }

        return $this->redirectToRoute('account_access');
    }
}
