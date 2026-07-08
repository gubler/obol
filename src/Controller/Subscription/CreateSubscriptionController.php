<?php

// ABOUTME: Invokable controller for creating new subscriptions with form handling and validation.
// ABOUTME: Uses CQRS command pattern to create subscription via CreateSubscriptionCommand with flash messages.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Dto\Subscription\CreateSubscriptionDto;
use App\Form\Subscription\CreateSubscriptionFormType;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\Message\Query\Category\FindAllCategoriesQuery;
use App\Message\Query\PaymentSource\FindAllPaymentSourcesQuery;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateSubscriptionController extends AbstractBaseController
{
    public function __construct(
        private readonly FileUploader $fileUploader,
    ) {
    }

    #[Route(path: '/app/subscriptions/new', name: 'subscription_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $dto = new CreateSubscriptionDto();
        // Pre-select the user's display currency; they can still pick another before the first payment.
        $dto->currency = $this->currentUser()->displayCurrency;

        $categories = $this->queryBus->query(query: new FindAllCategoriesQuery(ownerUserId: $this->currentUser()->id));
        \assert(\is_array($categories));

        $paymentSources = $this->queryBus->query(query: new FindAllPaymentSourcesQuery(ownerUserId: $this->currentUser()->id));
        \assert(\is_array($paymentSources));

        $form = $this->createForm(type: CreateSubscriptionFormType::class, data: $dto, options: [
            'has_categories' => [] !== $categories,
            'has_payment_sources' => [] !== $paymentSources,
        ]);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreateSubscriptionDto $data */
            $data = $form->getData();

            \assert(null !== $data->nextRenewal);

            $logo = null !== $data->logo
                ? $this->fileUploader->upload(file: $data->logo)
                : '';

            $this->commandBus->dispatch(command: new CreateSubscriptionCommand(
                ownerUserId: $this->currentUser()->id,
                categoryId: $data->category?->id,
                name: $data->name,
                nextRenewal: $data->nextRenewal,
                paymentPeriod: $data->paymentPeriod,
                paymentPeriodCount: $data->paymentPeriodCount,
                cost: $data->cost,
                currency: $data->currency,
                color: $data->color,
                description: $data->description,
                link: $data->link,
                logo: $logo,
                paymentSourceId: $data->paymentSource?->id,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('subscription.flash.created'));

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
