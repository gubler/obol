<?php

// ABOUTME: GET /app/admin/users/{id} - the admin read-only detail for one account, behind ROLE_ADMIN.
// ABOUTME: Shows email, display name, roles, joined date, and onboarding status; no fields are editable.

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractBaseController;
use App\Entity\User;
use App\Message\Query\User\FindUserQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class ShowUserController extends AbstractBaseController
{
    #[IsGranted(attribute: 'ROLE_ADMIN')]
    #[Route(path: '/app/admin/users/{id}', name: 'admin_user_show', methods: ['GET'])]
    public function __invoke(Ulid $id): Response
    {
        $user = $this->queryBus->query(new FindUserQuery(userId: $id));

        if (!$user instanceof User) {
            throw new NotFoundHttpException(\sprintf('User with ID "%s" not found.', $id));
        }

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }
}
