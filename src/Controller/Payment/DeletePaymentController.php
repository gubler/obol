<?php

// ABOUTME: Invokable controller for deleting a payment via POST request.
// ABOUTME: Uses CQRS command pattern to delete payment and redirect back to subscription show page.

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Controller\AbstractBaseController;
use App\Message\Command\Payment\DeletePaymentCommand;
use App\Message\Query\Payment\FindPaymentQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DeletePaymentController extends AbstractBaseController
{
    #[Route(path: '/payments/{id}/delete', name: 'payment_delete', methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        /** @var \App\Entity\Payment|null $payment */
        $payment = $this->queryBus->query(query: new FindPaymentQuery(paymentId: $id));

        if (null === $payment) {
            throw new NotFoundHttpException(\sprintf('Payment with ID "%s" not found.', $id));
        }

        $subscriptionId = (string) $payment->subscription->id;

        try {
            $this->commandBus->dispatch(command: new DeletePaymentCommand(paymentId: $id));

            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Payment deleted successfully');
        } catch (\Exception $e) {
            $this->addFlash(
                type: self::FLASH_ERROR,
                message: 'Failed to delete payment. Please try again.'
            );
        }

        return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $subscriptionId]);
    }
}
