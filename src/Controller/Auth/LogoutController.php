<?php

// ABOUTME: Logout route placeholder. The firewall's logout key intercepts this path before the controller runs.

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    #[Route(path: '/logout', name: 'app_logout', methods: ['GET'])]
    public function __invoke(): never
    {
        throw new \LogicException('This route is intercepted by the logout key on the firewall.');
    }
}
