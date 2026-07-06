<?php

// ABOUTME: JSON failure handler for the passkey assertion firewall authenticator.
// ABOUTME: Always returns 401 - never redirects, because browsers swallow 302s on the WebAuthn AJAX path.

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final readonly class PasskeyAuthFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse([
            'error' => 'authentication_failed',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
