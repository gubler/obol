<?php

// ABOUTME: GET /account/passkeys - list + rename + revoke surface.
// ABOUTME: Reads a one-shot session flag set on registration to highlight the just-registered row and focus its rename field.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Entity\PasskeyCredential;
use App\Message\Query\PasskeyCredential\FindPasskeysForUserQuery;
use App\Security\PasskeyRegistrationSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListPasskeysController extends AbstractBaseController
{
    #[Route(path: '/account/passkeys', name: 'account_passkey_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        // Read + clear the one-shot session flag set by the repository on registration.
        $session = $request->getSession();
        $stashed = $session->get(PasskeyRegistrationSession::JUST_REGISTERED_KEY);
        $justRegisteredId = \is_string($stashed) ? $stashed : '';
        if ('' !== $justRegisteredId) {
            $session->remove(PasskeyRegistrationSession::JUST_REGISTERED_KEY);
        }

        return $this->render('account/passkey/index.html.twig', [
            'credentials' => $this->queryBus->query(new FindPasskeysForUserQuery(
                ownerUserId: $this->currentUser()->id,
            )),
            'just_registered_id' => $justRegisteredId,
            'name_max_length' => PasskeyCredential::NAME_MAX_LENGTH,
        ]);
    }
}
