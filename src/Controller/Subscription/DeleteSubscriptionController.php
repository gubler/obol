<?php

// ABOUTME: Invokable controller for deleting subscription with validation.
// ABOUTME: Uses CQRS command pattern.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Message\Command\Subscription\DeleteSubscriptionCommand;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteSubscriptionController extends AbstractBaseController
{
    #[Route(path: '/subscriptions/{id}/delete', name: 'subscription_delete', methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(subscriptionId: $id));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $id));
        }

        try {
            $this->commandBus->dispatch(command: new DeleteSubscriptionCommand(subscriptionId: $id));

            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Subscription deleted successfully');
        } catch (\Exception $e) {
            $this->addFlash(
                type: self::FLASH_ERROR,
                message: 'Failed to delete subscription. Please try again.'
            );

            return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $id]);
        }

        return $this->redirectToRoute(route: 'subscription_index');
    }
}
