<?php

// ABOUTME: Invokable controller for the homepage subscription listing.
// ABOUTME: Reads the view/group/archived toggles and renders grouped subscriptions via CQRS.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Message\Query\Subscription\FindSubscriptionsForHomepageQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListSubscriptionsController extends AbstractBaseController
{
    #[Route(path: '/', name: 'subscription_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $view = 'list' === $request->query->get('view') ? 'list' : 'tiles';
        $grouped = '0' !== $request->query->get('group', '1');
        $includeArchived = '1' === $request->query->get('archived');

        $groups = $this->queryBus->query(
            query: new FindSubscriptionsForHomepageQuery(includeArchived: $includeArchived),
        );

        return $this->render(view: 'subscription/index.html.twig', parameters: [
            'groups' => $groups,
            'view' => $view,
            'grouped' => $grouped,
            'includeArchived' => $includeArchived,
        ]);
    }
}
