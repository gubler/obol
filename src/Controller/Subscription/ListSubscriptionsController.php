<?php

// ABOUTME: Invokable controller for the homepage subscription listing.
// ABOUTME: Reads the view/group/archived toggles and renders grouped subscriptions via CQRS.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Enum\SubscriptionSort;
use App\Message\Query\Report\FindRemainingInPeriodQuery;
use App\Message\Query\Report\FindTotalObligationQuery;
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
        $sort = SubscriptionSort::fromQuery($request->query->getString('sort'));

        $ownerUserId = $this->currentUser()->id;

        $listing = $this->queryBus->query(
            query: new FindSubscriptionsForHomepageQuery(ownerUserId: $ownerUserId, includeArchived: $includeArchived, sort: $sort),
        );

        // The capstone always reflects active obligation, independent of the listing's archived toggle.
        $capstoneMode = 'remaining' === $request->query->get('capstone') ? 'remaining' : 'totals';
        $capstone = 'remaining' === $capstoneMode
            ? $this->queryBus->query(query: new FindRemainingInPeriodQuery(ownerUserId: $ownerUserId))
            : $this->queryBus->query(query: new FindTotalObligationQuery(ownerUserId: $ownerUserId));

        return $this->render(view: 'subscription/index.html.twig', parameters: [
            'listing' => $listing,
            'capstone' => $capstone,
            'capstoneMode' => $capstoneMode,
            'view' => $view,
            'grouped' => $grouped,
            'includeArchived' => $includeArchived,
            'sort' => $sort,
            'sortOptions' => SubscriptionSort::cases(),
        ]);
    }
}
