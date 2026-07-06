<?php

// ABOUTME: POST /account/emails/{id}/resend - re-send the verification link for a pending address.
// ABOUTME: Owner-scoped lookup (404 cross-owner); the flash is the same generic notice as the add flow.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Entity\UserEmail;
use App\Message\Command\UserEmail\ResendEmailVerificationCommand;
use App\Message\Query\UserEmail\FindEmailForOwnerQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

final class ResendEmailVerificationController extends AbstractBaseController
{
    #[Route(
        path: '/account/emails/{id}/resend',
        name: 'account_email_resend',
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

        $this->commandBus->dispatch(new ResendEmailVerificationCommand(ownerUserId: $user->id, userEmailId: $id));
        $this->addFlash(self::FLASH_NOTICE, $this->translator->trans('account.email.flash.resent'));

        return $this->redirectToRoute('account_email_index');
    }
}
