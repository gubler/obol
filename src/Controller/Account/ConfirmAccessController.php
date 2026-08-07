<?php

// ABOUTME: GET /account/access/confirm - the way back to full authentication from a remembered session.
// ABOUTME: Holds no logic: being refused here is the point, and reaching the body means the proof landed.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ConfirmAccessController extends AbstractBaseController
{
    // Here the attribute is the mechanism, not a restatement of a firewall rule (ADR-0014). A
    // remembered session is refused, and because this is a safe request Symfony records the path
    // before handing off to the login page - so re-proving comes back here, and this sends the user
    // on to the hub with the credential forms live. The mutations themselves are POSTs, whose paths
    // Symfony does not record, which is why the journey needs a GET to hang itself on.
    #[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
    #[Route(path: '/app/account/access/confirm', name: 'account_access_confirm', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return $this->redirectToRoute('account_access');
    }
}
