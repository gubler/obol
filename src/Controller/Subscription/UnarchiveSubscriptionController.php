<?php

// ABOUTME: Invokable controller for unarchiving a subscription via POST request.
// ABOUTME: Uses CQRS command pattern to unarchive subscription and redirect back to show page.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Message\Command\Subscription\UnarchiveSubscriptionCommand;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class UnarchiveSubscriptionController extends AbstractBaseController
{
    #[Route(path: '/subscriptions/{id}/unarchive', name: 'subscription_unarchive', methods: ['POST'])]
    public function __invoke(Ulid $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(ownerUserId: $this->currentUser()->id, subscriptionId: $id));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $id));
        }

        $this->commandBus->dispatch(command: new UnarchiveSubscriptionCommand(ownerUserId: $this->currentUser()->id, subscriptionId: $id));

        $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('subscription.flash.unarchived'));

        return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $id]);
    }
}
