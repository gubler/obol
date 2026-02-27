<?php

// ABOUTME: Invokable controller for displaying individual subscription details with subscriptions.
// ABOUTME: Uses CQRS query pattern to fetch subscription via FindSubscriptionQuery with 404 handling.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ShowSubscriptionController extends AbstractBaseController
{
    #[Route(path: '/subscriptions/{id}', name: 'subscription_show', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(subscriptionId: $id));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $id));
        }

        return $this->render(view: 'subscription/show.html.twig', parameters: [
            'subscription' => $subscription,
        ]);
    }
}
