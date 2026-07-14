<?php

// ABOUTME: GET /app/admin - the admin area's landing (Overview), behind the ROLE_ADMIN gate.
// ABOUTME: The hub shell; System Toggles and User management sections hang off it in later slices.

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractBaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IndexController extends AbstractBaseController
{
    // Belt-and-suspenders with the `^/app/admin` access_control rule: the firewall rule guards the
    // whole surface, this attribute guards the action even if a future route escapes that prefix.
    #[IsGranted(attribute: 'ROLE_ADMIN')]
    #[Route(path: '/app/admin', name: 'admin_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/index.html.twig');
    }
}
