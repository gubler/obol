<?php

// ABOUTME: GET /account/emails - lists the account's addresses (primary + secondaries) and the add form.
// ABOUTME: The list is the management surface; per-row actions (promote / remove / resend) post to their own routes.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Dto\Account\AddSecondaryEmailDto;
use App\Form\Account\AddSecondaryEmailFormType;
use App\Message\Query\UserEmail\FindEmailsForUserQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListEmailsController extends AbstractBaseController
{
    #[Route(path: '/account/emails', name: 'account_email_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $form = $this->createForm(AddSecondaryEmailFormType::class, new AddSecondaryEmailDto());

        return $this->render('account/email/index.html.twig', [
            'emails' => $this->queryBus->query(new FindEmailsForUserQuery(ownerUserId: $this->currentUser()->id)),
            'form' => $form,
        ]);
    }
}
