<?php

// ABOUTME: GET /account/emails/{id}/verify - public endpoint that consumes the signed verification link.
// ABOUTME: The signature is the authority (the clicker may be logged out); a tampered link looks like a missing row.

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractBaseController;
use App\Entity\UserEmail;
use App\Exception\EmailVerifiedElsewhereException;
use App\Message\Command\UserEmail\VerifyEmailCommand;
use App\Message\Query\UserEmail\FindUserEmailByIdQuery;
use App\Security\SecondaryEmailVerifyUriSigner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

final class VerifyEmailController extends AbstractBaseController
{
    public function __construct(
        private readonly SecondaryEmailVerifyUriSigner $uriSigner,
    ) {
    }

    #[Route(
        path: '/account/emails/{id}/verify',
        name: 'account_email_verify',
        requirements: ['id' => Requirement::ULID],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, Ulid $id): Response
    {
        // Signature first: a tampered or expired link must be indistinguishable from a missing row, so it
        // never reveals whether the id existed.
        if (!$this->uriSigner->verifyRequest($request)) {
            return $this->render(
                'account/email/verify_error.html.twig',
                ['reason' => 'invalid'],
                new Response('', Response::HTTP_FORBIDDEN),
            );
        }

        $userEmail = $this->queryBus->query(new FindUserEmailByIdQuery(userEmailId: $id));
        if (!$userEmail instanceof UserEmail) {
            return $this->render(
                'account/email/verify_error.html.twig',
                ['reason' => 'not_found'],
                new Response('', Response::HTTP_NOT_FOUND),
            );
        }

        try {
            $this->commandBus->dispatch(new VerifyEmailCommand($userEmail->id));
        } catch (EmailVerifiedElsewhereException) {
            return $this->render(
                'account/email/verify_error.html.twig',
                ['reason' => 'verified_elsewhere'],
                new Response('', Response::HTTP_CONFLICT),
            );
        }

        return $this->render('account/email/verified.html.twig', [
            'address' => $userEmail->email,
        ]);
    }
}
