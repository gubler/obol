<?php

// ABOUTME: Invokable controller for displaying individual subscription details with subscriptions.
// ABOUTME: Uses CQRS query pattern to fetch subscription via FindSubscriptionQuery with 404 handling.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Entity\Subscription;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class ShowSubscriptionController extends AbstractBaseController
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/app/subscriptions/{id}', name: 'subscription_show', methods: ['GET'])]
    public function __invoke(Ulid $id): Response
    {
        $user = $this->currentUser();
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(ownerUserId: $user->id, subscriptionId: $id));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $id));
        }

        \assert($subscription instanceof Subscription);

        // What should be set aside for this subscription right now, honoring the user's savings lead;
        // null when they hide savings, so the template renders nothing (see ADR-0009).
        $savingsDisplay = $user->savingsDisplay;
        $savingsTarget = $savingsDisplay->showsSavings()
            ? $subscription->savingsTarget($user->localDateFor($this->clock->now()), $savingsDisplay->leadMonths())
            : null;

        return $this->render(view: 'subscription/show.html.twig', parameters: [
            'subscription' => $subscription,
            'savingsTarget' => $savingsTarget,
        ]);
    }
}
