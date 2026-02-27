<?php

// ABOUTME: Invokable controller for recording a payment on a subscription with form handling.
// ABOUTME: Uses CQRS command pattern to create payment via CreatePaymentCommand with flash messages.

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Controller\AbstractBaseController;
use App\Dto\Payment\CreatePaymentDto;
use App\Form\Payment\CreatePaymentFormType;
use App\Message\Command\Payment\CreatePaymentCommand;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CreatePaymentController extends AbstractBaseController
{
    #[Route(path: '/subscriptions/{subscriptionId}/payments/new', name: 'payment_new', methods: ['GET', 'POST'])]
    public function __invoke(string $subscriptionId, Request $request): Response
    {
        /** @var \App\Entity\Subscription|null $subscription */
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(subscriptionId: $subscriptionId));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $subscriptionId));
        }

        $dto = new CreatePaymentDto();
        $form = $this->createForm(type: CreatePaymentFormType::class, data: $dto);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreatePaymentDto $data */
            $data = $form->getData();

            \assert(null !== $data->amount);
            \assert(null !== $data->paidDate);

            $this->commandBus->dispatch(command: new CreatePaymentCommand(
                subscriptionId: $subscription->id->toRfc4122(),
                amount: $data->amount,
                paidDate: $data->paidDate,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Payment recorded successfully');

            return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $subscriptionId]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'payment/new.html.twig', parameters: [
            'form' => $form,
            'subscription' => $subscription,
        ]);
    }
}
