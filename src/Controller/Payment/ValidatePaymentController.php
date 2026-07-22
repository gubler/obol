<?php

// ABOUTME: Invokable controller for one-click validation of a generated payment.
// ABOUTME: Amends the payment with its current values, flipping it Generated -> Verified.

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Controller\AbstractBaseController;
use App\Entity\Payment;
use App\Message\Command\Payment\AmendPaymentCommand;
use App\Message\Query\Payment\FindPaymentQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Uid\Ulid;

final class ValidatePaymentController extends AbstractBaseController
{
    #[IsCsrfTokenValid(id: 'submit')]
    #[Route(path: '/app/payments/{id}/validate', name: 'payment_validate', methods: ['POST'])]
    public function __invoke(Ulid $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $payment = $this->queryBus->query(query: new FindPaymentQuery(ownerUserId: $this->currentUser()->id, paymentId: $id));

        if (null === $payment) {
            throw new NotFoundHttpException(\sprintf('Payment with ID "%s" not found.', $id));
        }

        \assert($payment instanceof Payment);

        $this->commandBus->dispatch(command: new AmendPaymentCommand(
            ownerUserId: $this->currentUser()->id,
            paymentId: $id,
            amount: $payment->amount->minorAmount,
            paidDate: $payment->paidDate,
        ));

        $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment.flash.validated'));

        return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $payment->subscription->id]);
    }
}
