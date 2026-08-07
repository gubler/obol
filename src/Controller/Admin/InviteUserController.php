<?php

// ABOUTME: GET/POST /app/admin/users/invite - invite a user by email, behind the ROLE_ADMIN gate.
// ABOUTME: A thin invite: create the account (verified primary email) and email it a login link. No Invite entity.

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractBaseController;
use App\Dto\Admin\InviteUserData;
use App\Form\Admin\InviteUserFormType;
use App\Message\Command\Auth\RequestLoginLinkCommand;
use App\Message\Command\User\CreateUserCommand;
use App\Message\Query\User\FindUserByEmailQuery;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InviteUserController extends AbstractBaseController
{
    #[IsGranted(attribute: 'ROLE_ADMIN')]
    #[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
    #[Route(path: '/app/admin/users/invite', name: 'admin_user_invite', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $data = new InviteUserData();
        $form = $this->createForm(InviteUserFormType::class, $data);
        $form->handleRequest($request);

        $rejectedAsDuplicate = false;

        if ($form->isSubmitted() && $form->isValid()) {
            // NotBlank + Email guarantee a non-empty address once the form is valid.
            \assert(null !== $data->email);
            $email = $data->email;

            if (null !== $this->queryBus->query(new FindUserByEmailQuery(email: $email))) {
                // The email already belongs to an account; reuse the same seam the console relies on and
                // create nothing. Surfaced as a field error rather than a 500 from the unique constraint.
                $form->get('email')->addError(new FormError($this->translator->trans('admin.users.invite.duplicate')));
                $rejectedAsDuplicate = true;
            } else {
                // Thin invite: create the account (its constructor makes a verified primary email, so it can
                // log in at once) and email a login link. The account is real immediately.
                $this->commandBus->dispatch(new CreateUserCommand(email: $email));
                $this->commandBus->dispatch(new RequestLoginLinkCommand(email: $email));

                $this->addFlash(self::FLASH_SUCCESS, $this->translator->trans('admin.users.invite.sent', ['%email%' => $email]));

                return $this->redirectToRoute('admin_users');
            }
        }

        $invalid = $rejectedAsDuplicate || ($form->isSubmitted() && !$form->isValid());
        if ($invalid) {
            $this->logFormErrors($form);
        }

        return $this->render(
            'admin/users/invite.html.twig',
            ['form' => $form],
            new Response(status: $invalid ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }
}
