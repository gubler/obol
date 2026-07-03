<?php

// ABOUTME: Magic-link check endpoint. A valid link is intercepted by the login_link authenticator;
// ABOUTME: reaching the controller body means the signature was missing or invalid, so bounce to login.

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class LoginCheckController extends AbstractController
{
    #[Route(path: '/login/check', name: 'app_login_check', methods: ['GET'])]
    public function __invoke(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->redirectToRoute(route: 'app_login');
    }
}
