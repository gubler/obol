<?php

// ABOUTME: Invokable controller for editing payment sources with form handling and validation.
// ABOUTME: Uses CQRS queries and commands to fetch and update the source with flash messages.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Dto\PaymentSource\UpdatePaymentSourceDto;
use App\Entity\PaymentSource;
use App\Form\PaymentSource\PaymentSourceFormType;
use App\Message\Command\PaymentSource\UpdatePaymentSourceCommand;
use App\Message\Query\PaymentSource\FindPaymentSourceQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class EditPaymentSourceController extends AbstractBaseController
{
    #[Route(path: '/payment-sources/{id}/edit', name: 'payment_source_edit', methods: ['GET', 'POST'])]
    public function __invoke(Ulid $id, Request $request): Response
    {
        $paymentSource = $this->queryBus->query(query: new FindPaymentSourceQuery(ownerUserId: $this->currentUser()->id, paymentSourceId: $id));

        if (null === $paymentSource) {
            throw new NotFoundHttpException(\sprintf('Payment source with ID "%s" not found.', $id));
        }

        \assert($paymentSource instanceof PaymentSource);

        $dto = new UpdatePaymentSourceDto(paymentSource: $paymentSource);

        $form = $this->createForm(type: PaymentSourceFormType::class, data: $dto, options: [
            'data_class' => UpdatePaymentSourceDto::class,
        ]);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UpdatePaymentSourceDto $data */
            $data = $form->getData();

            $this->commandBus->dispatch(command: new UpdatePaymentSourceCommand(
                ownerUserId: $this->currentUser()->id,
                paymentSourceId: $id,
                name: $data->name,
                comment: $data->comment,
                color: $data->color,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment_source.flash.updated'));

            return $this->redirectToRoute(route: 'payment_source_show', parameters: ['id' => $id]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'payment_source/edit.html.twig', parameters: [
            'form' => $form,
            'payment_source' => $paymentSource,
        ]);
    }
}
