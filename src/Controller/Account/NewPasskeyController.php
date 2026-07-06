<?php

// ABOUTME: GET /account/passkeys/new - renders the register-a-passkey page.
// ABOUTME: The ceremony itself runs against the WebAuthn bundle's controllers; this page hosts the Stimulus controller that drives them.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Entity\PasskeyCredential;
use App\Message\Query\PasskeyCredential\FindPasskeysForUserQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewPasskeyController extends AbstractBaseController
{
    #[Route(path: '/account/passkeys/new', name: 'account_passkey_new', methods: ['GET'])]
    public function __invoke(): Response
    {
        /** @var list<PasskeyCredential> $credentials */
        $credentials = $this->queryBus->query(new FindPasskeysForUserQuery(
            ownerUserId: $this->currentUser()->id,
        ));

        // The browser uses `excludeCredentials` to refuse duplicate registration from the same
        // authenticator. Pre-serialise the user's existing credential IDs (base64url) so the JS can
        // hand them straight to the WebAuthn API.
        $excludeIds = array_map(
            static fn (PasskeyCredential $credential): string => rtrim(strtr(base64_encode($credential->publicKeyCredentialId), '+/', '-_'), '='),
            $credentials,
        );

        return $this->render('account/passkey/new.html.twig', [
            'excludeCredentialsJson' => json_encode($excludeIds, \JSON_THROW_ON_ERROR),
        ]);
    }
}
