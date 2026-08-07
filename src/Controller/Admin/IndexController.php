<?php

// ABOUTME: GET /app/admin - the admin area's landing (Overview), behind the ROLE_ADMIN gate.
// ABOUTME: The hub shell; System Toggles and User management sections hang off it in later slices.

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractBaseController;
use App\Message\Query\Admin\GetAdminOverviewQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IndexController extends AbstractBaseController
{
    // Belt-and-suspenders with the `^/app/admin` access_control rule: the firewall rule guards the
    // whole surface, these attributes guard the action even if a future route escapes that prefix.
    // Two attributes are an AND (IsGranted is repeatable and every one is checked), unlike a `roles:`
    // list in access_control, which is an OR - see the note there.
    #[IsGranted(attribute: 'ROLE_ADMIN')]
    #[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
    #[Route(path: '/app/admin', name: 'admin_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/index.html.twig', [
            'overview' => $this->queryBus->query(new GetAdminOverviewQuery()),
        ]);
    }
}
