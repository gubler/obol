<?php

// ABOUTME: JSON success handler for the passkey assertion firewall authenticator.
// ABOUTME: Returns the post-login redirect target so the Stimulus controller can hand off to window.location.

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final readonly class PasskeyAuthSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    private const string FIREWALL_NAME = 'main';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        // WebAuthn is an AJAX flow: returning a 302 here causes the browser to silently follow the
        // redirect on the fetch() and the Stimulus controller never sees the target. We return JSON
        // instead and let the client do `window.location.href = response.redirect`.
        $target = null;
        if ($request->hasSession()) {
            $target = $this->getTargetPath($request->getSession(), self::FIREWALL_NAME);
            if (null !== $target) {
                $this->removeTargetPath($request->getSession(), self::FIREWALL_NAME);
            }
        }

        return new JsonResponse([
            'redirect' => $target ?? $this->urlGenerator->generate('subscription_index'),
        ]);
    }
}
