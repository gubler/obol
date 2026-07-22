<?php

// ABOUTME: POST /account/emails/{id}/remove - remove a secondary address from the account.
// ABOUTME: Owner-scoped lookup (404 cross-owner); removing the primary becomes a flash, not a crash.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Entity\UserEmail;
use App\Exception\AbstractSecondaryEmailException;
use App\Message\Command\UserEmail\RemoveEmailCommand;
use App\Message\Query\UserEmail\FindEmailForOwnerQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Uid\Ulid;

final class RemoveEmailController extends AbstractBaseController
{
    #[IsCsrfTokenValid(id: 'submit')]
    #[Route(
        path: '/app/account/emails/{id}/remove',
        name: 'account_email_remove',
        requirements: ['id' => Requirement::ULID],
        methods: ['POST'],
    )]
    public function __invoke(Ulid $id): RedirectResponse
    {
        $user = $this->currentUser();

        $userEmail = $this->queryBus->query(new FindEmailForOwnerQuery(ownerUserId: $user->id, userEmailId: $id));
        if (!$userEmail instanceof UserEmail) {
            throw new NotFoundHttpException(\sprintf('Email with ID "%s" not found.', $id));
        }

        try {
            $this->commandBus->dispatch(new RemoveEmailCommand(ownerUserId: $user->id, userEmailId: $id));
            $this->addFlash(self::FLASH_SUCCESS, $this->translator->trans('account.email.flash.removed'));
        } catch (AbstractSecondaryEmailException $secondaryEmailException) {
            $this->addFlash(self::FLASH_ERROR, $this->translator->trans($secondaryEmailException->translationKey()));
        }

        return $this->redirectToRoute('account_access');
    }
}
