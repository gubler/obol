<?php

// ABOUTME: POST /app/admin/users/{id}/resend-login-link - emails a fresh magic link to a chosen verified address.
// ABOUTME: The operator picks which of the user's verified emails to send to (a stuck primary is not a dead end).

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractBaseController;
use App\Entity\User;
use App\Message\Command\Auth\RequestLoginLinkCommand;
use App\Message\Query\User\FindUserQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class ResendLoginLinkController extends AbstractBaseController
{
    #[IsGranted(attribute: 'ROLE_ADMIN')]
    #[IsCsrfTokenValid(id: 'submit')]
    #[Route(path: '/app/admin/users/{id}/resend-login-link', name: 'admin_user_resend_login_link', methods: ['POST'])]
    public function __invoke(Ulid $id, Request $request): RedirectResponse
    {
        $user = $this->queryBus->query(new FindUserQuery(userId: $id));

        if (!$user instanceof User) {
            throw new NotFoundHttpException(\sprintf('User with ID "%s" not found.', $id));
        }

        // The link is delivered to the address the operator picks - so a user locked out of their primary
        // can be sent a link to a verified secondary they can still reach. Only the user's own verified
        // addresses are valid targets; a crafted request for anything else is a 404.
        $target = (string) $request->request->get('email', '');
        if (!$this->isVerifiedEmailOf($user, $target)) {
            throw new NotFoundHttpException(\sprintf('No verified email "%s" for user "%s".', $target, $id));
        }

        $this->commandBus->dispatch(new RequestLoginLinkCommand(email: $target));

        $this->addFlash(
            self::FLASH_SUCCESS,
            $this->translator->trans('admin.users.resend.sent', ['%email%' => $target]),
        );

        return $this->redirectToRoute('admin_user_show', ['id' => $id]);
    }

    private function isVerifiedEmailOf(User $user, string $email): bool
    {
        foreach ($user->emails as $userEmail) {
            if ($userEmail->email === $email && $userEmail->isVerified()) {
                return true;
            }
        }

        return false;
    }
}
