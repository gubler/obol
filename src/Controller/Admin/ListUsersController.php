<?php

// ABOUTME: GET /app/admin/users - the admin hub's searchable, paginated user list, behind the ROLE_ADMIN gate.
// ABOUTME: Reads all accounts through the query bus; deliberately not owner-scoped (see ADR-0015).

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractBaseController;
use App\Message\Query\User\SearchUsersQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListUsersController extends AbstractBaseController
{
    #[IsGranted(attribute: 'ROLE_ADMIN')]
    #[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
    #[Route(path: '/app/admin/users', name: 'admin_users', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));

        return $this->render('admin/users/index.html.twig', [
            'usersPage' => $this->queryBus->query(new SearchUsersQuery(search: $search, page: $page)),
            'search' => $search,
        ]);
    }
}
