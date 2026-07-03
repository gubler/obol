<?php

// ABOUTME: Non-prod login bypass for Panther browser tests - authenticates a session by email, no magic link.
// ABOUTME: 404s outside dev/test; resolves the user through MultiEmailUserProvider (no direct data access).

declare(strict_types=1);

namespace App\Controller\Test;

use App\Security\MultiEmailUserProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class TestLoginAsController
{
    #[Route(path: '/_test/login-as/{email}', name: 'test_login_as', methods: ['GET'])]
    public function __invoke(
        string $email,
        #[Autowire(value: '%kernel.environment%')]
        string $kernelEnvironment,
        Security $security,
        MultiEmailUserProvider $userProvider,
    ): RedirectResponse {
        if (!\in_array($kernelEnvironment, ['dev', 'test'], true)) {
            throw new NotFoundHttpException();
        }

        try {
            $user = $userProvider->loadUserByIdentifier($email);
        } catch (UserNotFoundException) {
            throw new NotFoundHttpException(\sprintf('Test user "%s" not found.', $email));
        }

        $security->login($user, 'security.authenticator.login_link.main', 'main');

        return new RedirectResponse('/');
    }
}
