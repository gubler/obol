<?php

// ABOUTME: Builds and sends the secondary-email verification message (signed link) to a pending address.
// ABOUTME: Shared by the add-secondary and resend flows; delivery is async via the mail transport.

declare(strict_types=1);

namespace App\Mail;

use App\Entity\UserEmail;
use App\Security\SecondaryEmailVerifyUriSigner;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SecondaryEmailVerificationMailer
{
    public function __construct(
        private SecondaryEmailVerifyUriSigner $uriSigner,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        #[Autowire(param: 'app.secondary_email.verify_ttl_seconds')]
        private int $verifyTtlSeconds,
    ) {
    }

    public function send(UserEmail $userEmail): void
    {
        $expiresAt = $this->clock->now()->add(new \DateInterval('PT' . $this->verifyTtlSeconds . 'S'));
        $verifyUrl = $this->uriSigner->sign($userEmail, $expiresAt);

        // Delivered to the address being added, never the existing primary: the verify ceremony is what
        // proves control of the new mailbox, and mirroring to the primary would leak the attempt.
        $email = new TemplatedEmail()
            ->to(new Address($userEmail->email))
            ->subject($this->translator->trans('account.email.verify.subject'))
            ->htmlTemplate('email/secondary_email_verify.html.twig')
            ->textTemplate('email/secondary_email_verify.txt.twig')
            ->context([
                'verifyUrl' => $verifyUrl,
                'expiresAt' => $expiresAt,
            ])
        ;

        $this->mailer->send($email);
    }
}
