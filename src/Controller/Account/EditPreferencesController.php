<?php

// ABOUTME: GET/POST /account/preferences/edit - the edit form behind the Preferences summary's Edit link.
// ABOUTME: Renders inside a Turbo Frame (swaps in with JS, full-navigates without); saves name + settings.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Dto\Account\ChangePreferencesDto;
use App\Enum\AppLocale;
use App\Form\Account\ChangePreferencesFormType;
use App\Message\Command\User\ChangePreferencesCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EditPreferencesController extends AbstractBaseController
{
    #[Route(path: '/account/preferences/edit', name: 'account_preferences_edit', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->currentUser();

        $dto = new ChangePreferencesDto();
        $dto->displayName = $user->displayName;
        $dto->displayCurrency = $user->displayCurrency;
        $dto->timezone = $user->timezone;
        // Null when the stored locale has no shipped catalog; the form then shows its placeholder.
        $dto->language = null === $user->locale ? null : AppLocale::tryFrom($user->locale);
        $dto->dateFormat = $user->dateFormat;

        $form = $this->createForm(ChangePreferencesFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // NotNull on the DTO guarantees a language was chosen once the form is valid.
            \assert($dto->language instanceof AppLocale);

            $this->commandBus->dispatch(new ChangePreferencesCommand(
                ownerUserId: $user->id,
                displayName: $dto->displayName,
                displayCurrency: $dto->displayCurrency,
                timezone: $dto->timezone,
                locale: $dto->language->value,
                dateFormat: $dto->dateFormat,
            ));

            $this->addFlash(self::FLASH_SUCCESS, $this->translator->trans('account.preferences.updated'));

            return $this->redirectToRoute('account_preferences');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors($form);
        }

        return $this->render('account/preferences/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
