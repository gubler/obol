<?php

// ABOUTME: GET /account/preferences - the account hub's Preferences section, a read-only summary.
// ABOUTME: Values are shown as text with an Edit link (a Turbo Frame swaps the edit form in place).

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Enum\AppLocale;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PreferencesController extends AbstractBaseController
{
    #[Route(path: '/app/account/preferences', name: 'account_preferences', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->currentUser();

        return $this->render('account/preferences/show.html.twig', [
            'user' => $user,
            // The picker only knows the shipped languages; a browser-guessed region (no catalog) has none.
            'language' => null === $user->locale ? null : AppLocale::tryFrom($user->locale),
            // Rendered through the user_datetime filter in the template to preview the current style.
            'sampleDateTime' => new \DateTimeImmutable(),
        ]);
    }
}
