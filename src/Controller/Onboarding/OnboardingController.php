<?php

// ABOUTME: Invokable controller for the first-run onboarding screen (confirm name/currency/timezone).
// ABOUTME: Pre-fills a browser-guessed currency, persists via CompleteOnboardingCommand, then offers the tour.

declare(strict_types=1);

namespace App\Controller\Onboarding;

use App\Controller\AbstractBaseController;
use App\Dto\Onboarding\CompleteOnboardingDto;
use App\Form\Onboarding\CompleteOnboardingFormType;
use App\Message\Command\Onboarding\CompleteOnboardingCommand;
use App\Service\CurrencyGuesser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OnboardingController extends AbstractBaseController
{
    public function __construct(private readonly CurrencyGuesser $currencyGuesser)
    {
    }

    #[Route(path: '/app/onboarding', name: 'onboarding', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        // Onboarding runs once. An already-onboarded user reaching here (they are not gated) is sent
        // straight to the app - it saves rebuilding the form and avoids a re-submit tripping
        // completeOnboarding()'s idempotency guard.
        if ($this->currentUser()->hasCompletedOnboarding()) {
            return $this->redirectToRoute(route: 'subscription_index');
        }

        $dto = new CompleteOnboardingDto();
        // Best-effort defaults the user confirms or overrides: currency from the browser locale, and
        // the timezone already on the account (refined client-side by the timezone_detect controller).
        $dto->displayCurrency = $this->currencyGuesser->guessFrom($request);
        $dto->timezone = $this->currentUser()->timezone;

        $form = $this->createForm(type: CompleteOnboardingFormType::class, data: $dto);
        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CompleteOnboardingDto $data */
            $data = $form->getData();

            $this->commandBus->dispatch(command: new CompleteOnboardingCommand(
                ownerUserId: $this->currentUser()->id,
                displayName: $data->displayName,
                displayCurrency: $data->displayCurrency,
                timezone: $data->timezone,
            ));

            // Land on the dashboard with the one-time tour offer flagged.
            return $this->redirectToRoute(route: 'subscription_index', parameters: ['welcome' => 1]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'onboarding/index.html.twig', parameters: [
            'form' => $form,
        ]);
    }
}
