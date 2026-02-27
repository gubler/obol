<?php

// ABOUTME: Invokable controller for unarchiving a subscription via POST request.
// ABOUTME: Uses CQRS command pattern to unarchive subscription and redirect back to show page.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Message\Command\Subscription\UnarchiveSubscriptionCommand;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class UnarchiveSubscriptionController extends AbstractBaseController
{
    #[Route(path: '/subscriptions/{id}/unarchive', name: 'subscription_unarchive', methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(subscriptionId: $id));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $id));
        }

        $this->commandBus->dispatch(command: new UnarchiveSubscriptionCommand(subscriptionId: $id));

        $this->addFlash(type: self::FLASH_SUCCESS, message: 'Subscription unarchived successfully');

        return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $id]);
    }
}
