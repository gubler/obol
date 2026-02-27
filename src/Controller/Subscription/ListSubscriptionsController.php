<?php

// ABOUTME: Invokable controller for displaying list of all categories with subscription counts.
// ABOUTME: Uses CQRS query pattern to fetch categories via FindAllSubscriptionsQuery.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Message\Query\Subscription\FindAllSubscriptionsQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListSubscriptionsController extends AbstractBaseController
{
    #[Route(path: '/', name: 'subscription_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $subscriptions = $this->queryBus->query(query: new FindAllSubscriptionsQuery());

        return $this->render(view: 'subscription/index.html.twig', parameters: [
            'subscriptions' => $subscriptions,
        ]);
    }
}
