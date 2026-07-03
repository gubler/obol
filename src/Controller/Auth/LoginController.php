<?php

// ABOUTME: Login page - renders the email form and, on submit, requests a magic link off-request.
// ABOUTME: The response is identical for any address (enumeration-generic); the lookup runs on the worker.

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\AbstractBaseController;
use App\Dto\Auth\LoginRequestDto;
use App\Form\Auth\LoginRequestFormType;
use App\Message\Command\Auth\RequestLoginLinkCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractBaseController
{
    public function __construct(private readonly AuthenticationUtils $authenticationUtils)
    {
    }

    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($this->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            return $this->redirectToRoute(route: 'subscription_index');
        }

        $dto = new LoginRequestDto();
        $form = $this->createForm(type: LoginRequestFormType::class, data: $dto);
        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var LoginRequestDto $data */
            $data = $form->getData();

            $this->commandBus->dispatch(command: new RequestLoginLinkCommand(email: $data->email));

            // Deliberately generic: the same message whether or not the address has an account.
            $this->addFlash(type: self::FLASH_NOTICE, message: $this->translator->trans('auth.login.link_sent'));

            return $this->redirectToRoute(route: 'app_login');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'auth/login.html.twig', parameters: [
            'form' => $form,
            'lastAuthError' => $this->authenticationUtils->getLastAuthenticationError(),
        ]);
    }
}
