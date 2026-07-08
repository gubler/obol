<?php

// ABOUTME: GET /account/access - the account hub's Access section: email addresses and passkeys together.
// ABOUTME: Per-row actions (add / promote / remove / resend / rename / revoke) post to their own routes.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Dto\Account\AddSecondaryEmailDto;
use App\Entity\PasskeyCredential;
use App\Form\Account\AddSecondaryEmailFormType;
use App\Message\Query\PasskeyCredential\FindPasskeysForUserQuery;
use App\Message\Query\UserEmail\FindEmailsForUserQuery;
use App\Security\PasskeyRegistrationSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccessController extends AbstractBaseController
{
    #[Route(path: '/app/account/access', name: 'account_access', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->currentUser();

        $form = $this->createForm(AddSecondaryEmailFormType::class, new AddSecondaryEmailDto());

        // Read + clear the one-shot session flag set by the repository on passkey registration.
        $session = $request->getSession();
        $stashed = $session->get(PasskeyRegistrationSession::JUST_REGISTERED_KEY);
        $justRegisteredId = \is_string($stashed) ? $stashed : '';
        if ('' !== $justRegisteredId) {
            $session->remove(PasskeyRegistrationSession::JUST_REGISTERED_KEY);
        }

        return $this->render('account/access/index.html.twig', [
            'emails' => $this->queryBus->query(new FindEmailsForUserQuery(ownerUserId: $user->id)),
            'form' => $form,
            'credentials' => $this->queryBus->query(new FindPasskeysForUserQuery(ownerUserId: $user->id)),
            'just_registered_id' => $justRegisteredId,
            'name_max_length' => PasskeyCredential::NAME_MAX_LENGTH,
        ]);
    }
}
