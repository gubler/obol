<?php

// ABOUTME: Public landing at `/` (outside /app, ADR-0018) and the updates-signup POST target.
// ABOUTME: Renders the product intro + login CTA + "sign up for updates" form; POST captures the email.

declare(strict_types=1);

namespace App\Controller\Landing;

use App\Controller\AbstractBaseController;
use App\Dto\Updates\UpdatesSignupDto;
use App\Form\Updates\UpdatesSignupFormType;
use App\Message\Command\Updates\SubscribeToUpdatesCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandingController extends AbstractBaseController
{
    /**
     * Serves both the landing render (`GET /`, the public front door for anonymous and signed-in
     * visitors alike) and the updates-signup submission (`POST /updates`). handleRequest only binds
     * the POST, so the GET falls through to rendering an empty form.
     */
    #[Route(path: '/', name: 'landing', methods: ['GET'])]
    #[Route(path: '/updates', name: 'updates_subscribe', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(type: UpdatesSignupFormType::class, data: new UpdatesSignupDto());
        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UpdatesSignupDto $data */
            $data = $form->getData();

            $this->commandBus->dispatch(command: new SubscribeToUpdatesCommand(email: $data->email));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('landing.updates.thanks'));

            // Back to the landing (not the dashboard): the submitter is typically anonymous.
            return $this->redirectToRoute(route: 'landing');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'landing/index.html.twig', parameters: [
            'form' => $form,
        ]);
    }
}
