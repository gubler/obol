<?php

// ABOUTME: Signs and validates the secondary-email verification link with Symfony's UriSigner (HMAC over the URL).
// ABOUTME: Stateless - no token table; the signature (seeded from APP_SECRET) plus an expiry is the whole credential.

declare(strict_types=1);

namespace App\Security;

use App\Entity\UserEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The mechanism mirrors the magic-link floor (ADR-0014): a signed URL rather than a bundle, keeping the
 * verification flow dependency-free. The signed link resolves the row by id; delivery to the address is
 * what proves the person controls that mailbox.
 */
final readonly class SecondaryEmailVerifyUriSigner
{
    public function __construct(
        private UriSigner $uriSigner,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sign(UserEmail $userEmail, \DateTimeImmutable $expiresAt): string
    {
        $absoluteUrl = $this->urlGenerator->generate(
            'account_email_verify',
            ['id' => (string) $userEmail->id],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->uriSigner->sign($absoluteUrl, $expiresAt);
    }

    public function verifyRequest(Request $request): bool
    {
        return $this->uriSigner->checkRequest($request);
    }
}
