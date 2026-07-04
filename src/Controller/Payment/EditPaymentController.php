<?php

// ABOUTME: Invokable controller for amending a payment (validate/adjust) via an edit form.
// ABOUTME: Dispatches AmendPaymentCommand, which flips the payment to Verified.

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Controller\AbstractBaseController;
use App\Dto\Payment\AmendPaymentDto;
use App\Entity\Payment;
use App\Form\Payment\AmendPaymentFormType;
use App\Message\Command\Payment\AmendPaymentCommand;
use App\Message\Query\Payment\FindPaymentQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class EditPaymentController extends AbstractBaseController
{
    #[Route(path: '/payments/{id}/edit', name: 'payment_edit', methods: ['GET', 'POST'])]
    public function __invoke(Ulid $id, Request $request): Response
    {
        $payment = $this->queryBus->query(query: new FindPaymentQuery(ownerUserId: $this->currentUser()->id, paymentId: $id));

        if (null === $payment) {
            throw new NotFoundHttpException(\sprintf('Payment with ID "%s" not found.', $id));
        }

        \assert($payment instanceof Payment);

        $dto = new AmendPaymentDto(payment: $payment);
        $form = $this->createForm(type: AmendPaymentFormType::class, data: $dto, options: [
            'fraction_digits' => $payment->amount->currency->fractionDigits(),
        ]);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var AmendPaymentDto $data */
            $data = $form->getData();

            \assert(null !== $data->amount);
            \assert(null !== $data->paidDate);

            $this->commandBus->dispatch(command: new AmendPaymentCommand(
                ownerUserId: $this->currentUser()->id,
                paymentId: $id,
                amount: $data->amount,
                paidDate: $data->paidDate,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment.flash.updated'));

            return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $payment->subscription->id]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'payment/edit.html.twig', parameters: [
            'form' => $form,
            'payment' => $payment,
        ]);
    }
}
