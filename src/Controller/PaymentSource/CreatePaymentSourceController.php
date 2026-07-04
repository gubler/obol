<?php

// ABOUTME: Invokable controller for creating new payment sources with form handling and validation.
// ABOUTME: Uses the CQRS command pattern via CreatePaymentSourceCommand with flash messages.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Dto\PaymentSource\CreatePaymentSourceDto;
use App\Form\PaymentSource\PaymentSourceFormType;
use App\Message\Command\PaymentSource\CreatePaymentSourceCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreatePaymentSourceController extends AbstractBaseController
{
    #[Route(path: '/payment-sources/new', name: 'payment_source_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $dto = new CreatePaymentSourceDto();
        $form = $this->createForm(type: PaymentSourceFormType::class, data: $dto, options: [
            'data_class' => CreatePaymentSourceDto::class,
        ]);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreatePaymentSourceDto $data */
            $data = $form->getData();

            $this->commandBus->dispatch(command: new CreatePaymentSourceCommand(
                ownerUserId: $this->currentUser()->id,
                name: $data->name,
                comment: $data->comment,
                color: $data->color,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment_source.flash.created'));

            return $this->redirectToRoute(route: 'payment_source_index');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'payment_source/new.html.twig', parameters: [
            'form' => $form,
        ]);
    }
}
