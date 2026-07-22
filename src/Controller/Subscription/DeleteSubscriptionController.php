<?php

// ABOUTME: Invokable controller for deleting subscription with validation.
// ABOUTME: Uses CQRS command pattern.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Message\Command\Subscription\DeleteSubscriptionCommand;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Uid\Ulid;

final class DeleteSubscriptionController extends AbstractBaseController
{
    #[IsCsrfTokenValid(id: 'submit')]
    #[Route(path: '/app/subscriptions/{id}/delete', name: 'subscription_delete', methods: ['POST'])]
    public function __invoke(Ulid $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(ownerUserId: $this->currentUser()->id, subscriptionId: $id));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $id));
        }

        try {
            $this->commandBus->dispatch(command: new DeleteSubscriptionCommand(ownerUserId: $this->currentUser()->id, subscriptionId: $id));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('subscription.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash(
                type: self::FLASH_ERROR,
                message: $this->translator->trans('subscription.flash.delete_failed')
            );

            return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $id]);
        }

        return $this->redirectToRoute(route: 'subscription_index');
    }
}
