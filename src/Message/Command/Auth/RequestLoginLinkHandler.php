<?php

// ABOUTME: Handler that mints a magic link for a verified address and emails it, or no-ops for unknown ones.
// ABOUTME: Runs on the async worker; an unknown/unverified address leaves no trace, so login cannot enumerate.

declare(strict_types=1);

namespace App\Message\Command\Auth;

use App\Repository\UserEmailRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RequestLoginLinkHandler
{
    public function __construct(
        private UserEmailRepository $userEmailRepository,
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private LoginLinkHandlerInterface $loginLinkHandler,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(RequestLoginLinkCommand $command): void
    {
        $userEmail = $this->userEmailRepository->findVerifiedByEmail($command->email);

        // Unknown or unverified address: do nothing. The generic "check your email" response was
        // already returned in-request, so silence here is what keeps login from enumerating accounts.
        if (!$userEmail instanceof \App\Entity\UserEmail) {
            return;
        }

        // The link signs against the primary email (session identity), but is delivered to the address
        // the person actually typed, so logging in from a verified secondary reaches the right inbox.
        $loginLink = $this->loginLinkHandler->createLoginLink($userEmail->user);

        $email = new TemplatedEmail()
            ->to(new Address($userEmail->email))
            ->subject($this->translator->trans('auth.email.login_link.subject'))
            ->htmlTemplate('email/login_link.html.twig')
            ->textTemplate('email/login_link.txt.twig')
            ->context([
                'loginLinkUrl' => $loginLink->getUrl(),
                'expiresAt' => $loginLink->getExpiresAt(),
            ])
        ;

        $this->mailer->send($email);
    }
}
