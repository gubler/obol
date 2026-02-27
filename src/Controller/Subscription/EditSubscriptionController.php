<?php

// ABOUTME: Invokable controller for editing subscriptions with form handling and validation.
// ABOUTME: Uses CQRS pattern to fetch and update subscription via queries and commands with flash messages.

declare(strict_types=1);

namespace App\Controller\Subscription;

use App\Controller\AbstractBaseController;
use App\Dto\Subscription\UpdateSubscriptionDto;
use App\Entity\Subscription;
use App\Form\Subscription\EditSubscriptionFormType;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use App\Message\Query\Subscription\FindSubscriptionQuery;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class EditSubscriptionController extends AbstractBaseController
{
    #[Route(path: '/subscriptions/{id}/edit', name: 'subscription_edit', methods: ['GET', 'POST'])]
    public function __invoke(string $id, Request $request, FileUploader $fileUploader): Response
    {
        $subscription = $this->queryBus->query(query: new FindSubscriptionQuery(subscriptionId: $id));

        if (null === $subscription) {
            throw new NotFoundHttpException(\sprintf('Subscription with ID "%s" not found.', $id));
        }

        \assert($subscription instanceof Subscription);

        $dto = new UpdateSubscriptionDto(subscription: $subscription);

        $form = $this->createForm(type: EditSubscriptionFormType::class, data: $dto);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UpdateSubscriptionDto $data */
            $data = $form->getData();

            $logo = null !== $data->logo
                ? $fileUploader->upload(file: $data->logo)
                : $subscription->logo;

            $this->commandBus->dispatch(command: new UpdateSubscriptionCommand(
                subscriptionId: $id,
                categoryId: $data->category->id->toRfc4122(),
                name: $data->name,
                lastPaidDate: $data->lastPaidDate,
                description: $data->description,
                link: $data->link,
                logo: $logo,
                paymentPeriod: $data->paymentPeriod,
                paymentPeriodCount: $data->paymentPeriodCount,
                cost: $data->cost,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Subscription updated successfully');

            return $this->redirectToRoute(route: 'subscription_show', parameters: ['id' => $id]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'subscription/edit.html.twig', parameters: [
            'form' => $form,
            'subscription' => $subscription,
        ]);
    }
}
