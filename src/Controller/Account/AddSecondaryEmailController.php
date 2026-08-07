<?php

// ABOUTME: POST /account/emails - adds a secondary address and mails it a verification link.
// ABOUTME: The flash is generic whether the address was free, already yours, or taken - it never confirms who holds it.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Dto\Account\AddSecondaryEmailDto;
use App\Form\Account\AddSecondaryEmailFormType;
use App\Message\Command\UserEmail\AddSecondaryEmailCommand;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AddSecondaryEmailController extends AbstractBaseController
{
    // Belt-and-suspenders with the `^/app/account/emails` access_control rule (ADR-0014): adding an
    // address is a credential change, so a remember-me-restored session must re-prove first.
    #[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
    #[Route(path: '/app/account/emails', name: 'account_email_add', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $dto = new AddSecondaryEmailDto();
        $form = $this->createForm(AddSecondaryEmailFormType::class, $dto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->logFormErrors($form);
            $this->addFlash(self::FLASH_ERROR, $this->translator->trans('account.email.add.invalid'));

            return $this->redirectToRoute('account_access');
        }

        $this->commandBus->dispatch(new AddSecondaryEmailCommand(
            ownerUserId: $this->currentUser()->id,
            email: $dto->email,
        ));

        // Generic notice, identical whether the address was free, already on this account, or verified
        // elsewhere - the response never reveals who holds an address (parallels the /login flow).
        $this->addFlash(self::FLASH_NOTICE, $this->translator->trans('account.email.flash.verify_sent'));

        return $this->redirectToRoute('account_access');
    }
}
