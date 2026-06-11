<?php

// ABOUTME: Invokable controller for creating new subscriptions with form handling and validation.
// ABOUTME: Uses CQRS command pattern to create subscription via CreateSubscriptionCommand with flash messages.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Dto\Subscription\CreateSubscriptionDto;
use App\Form\Subscription\CreateSubscriptionFormType;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\Service\DisplayCurrencyProvider;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateSubscriptionController extends AbstractBaseController
{
    public function __construct(
        private readonly FileUploader $fileUploader,
        private readonly DisplayCurrencyProvider $displayCurrencyProvider,
    ) {
    }

    #[Route(path: '/subscriptions/new', name: 'subscription_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $dto = new CreateSubscriptionDto();
        // Pre-select the display currency; the user can still pick another before the first payment.
        $dto->currency = $this->displayCurrencyProvider->get();
        $form = $this->createForm(type: CreateSubscriptionFormType::class, data: $dto);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreateSubscriptionDto $data */
            $data = $form->getData();

            \assert(null !== $data->category);
            \assert(null !== $data->nextRenewal);

            $logo = null !== $data->logo
                ? $this->fileUploader->upload(file: $data->logo)
                : '';

            $this->commandBus->dispatch(command: new CreateSubscriptionCommand(
                categoryId: $data->category->id,
                name: $data->name,
                nextRenewal: $data->nextRenewal,
                paymentPeriod: $data->paymentPeriod,
                paymentPeriodCount: $data->paymentPeriodCount,
                cost: $data->cost,
                currency: $data->currency,
                description: $data->description,
                link: $data->link,
                logo: $logo,
                color: $data->color,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Subscription created successfully');

            return $this->redirectToRoute(route: 'subscription_index');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'subscription/new.html.twig', parameters: [
            'form' => $form,
        ]);
    }
}
