<?php

// ABOUTME: POST /account/passkeys/{id}/name - rename a passkey.
// ABOUTME: Owner-scoped lookup (404 cross-owner); flashes success only on a real change (rename is idempotent).

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Dto\Account\RenamePasskeyDto;
use App\Entity\PasskeyCredential;
use App\Form\Account\RenamePasskeyFormType;
use App\Message\Command\PasskeyCredential\RenamePasskeyCommand;
use App\Message\Query\PasskeyCredential\FindPasskeyForOwnerQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class RenamePasskeyController extends AbstractBaseController
{
    #[Route(path: '/app/account/passkeys/{id}/name', name: 'account_passkey_rename', methods: ['POST'])]
    public function __invoke(Ulid $id, Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        $credential = $this->queryBus->query(new FindPasskeyForOwnerQuery(credentialId: $id, ownerUserId: $user->id));
        if (!$credential instanceof PasskeyCredential) {
            throw new NotFoundHttpException(\sprintf('Passkey with ID "%s" not found.', $id));
        }

        $dto = new RenamePasskeyDto();
        $form = $this->createForm(RenamePasskeyFormType::class, $dto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->logFormErrors($form);
            $this->addFlash(self::FLASH_ERROR, $this->translator->trans('account.passkey.rename.invalid'));

            return $this->redirectToRoute('account_access');
        }

        $renamed = $this->commandBus->dispatch(new RenamePasskeyCommand(
            credentialId: $id,
            ownerUserId: $user->id,
            name: $dto->name,
        ));

        if (true === $renamed) {
            $this->addFlash(self::FLASH_SUCCESS, $this->translator->trans('account.passkey.rename.success'));
        }

        return $this->redirectToRoute('account_access');
    }
}
