<?php

// ABOUTME: Invokable controller for deleting a payment via POST request.
// ABOUTME: Uses CQRS command pattern to delete payment and redirect back to subscription show page.

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Controller\AbstractBaseController;
use App\Message\Command\Payment\DeletePaymentCommand;
use App\Message\Query\Payment\FindPaymentQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class DeletePaymentController extends AbstractBaseController
{
    #[Route(path: '/app/payments/{id}/delete', name: 'payment_delete', methods: ['POST'])]
    public function __invoke(Ulid $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        /** @var \App\Entity\Payment|null $payment */
        $payment = $this->queryBus->query(query: new FindPaymentQuery(ownerUserId: $this->currentUser()->id, paymentId: $id));

        if (null === $payment) {
            throw new NotFoundHttpException(\sprintf('Payment with ID "%s" not found.', $id));
        }

        $subscriptionId = (string) $payment->subscription->id;

        try {
            $this->commandBus->dispatch(command: new DeletePaymentCommand(ownerUserId: $this->currentUser()->id, paymentId: $id));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash(
                type: self::FLASH_ERROR,
                message: $this->translator->trans('payment.flash.delete_failed')
            );
        }

        return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $subscriptionId]);
    }
}
