<?php

// ABOUTME: Invokable controller for moving every subscription from one payment source to another.
// ABOUTME: The destination comes from an owner-scoped form; an invalid target re-flashes without moving.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Dto\PaymentSource\ReassignSubscriptionsDto;
use App\Entity\PaymentSource;
use App\Form\PaymentSource\ReassignSubscriptionsFormType;
use App\Message\Command\PaymentSource\ReassignPaymentSourceSubscriptionsCommand;
use App\Message\Query\PaymentSource\FindPaymentSourceQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class ReassignPaymentSourceSubscriptionsController extends AbstractBaseController
{
    #[Route(path: '/app/payment-sources/{id}/reassign', name: 'payment_source_reassign', methods: ['POST'])]
    public function __invoke(Ulid $id, Request $request): RedirectResponse
    {
        $ownerUserId = $this->currentUser()->id;
        $source = $this->queryBus->query(query: new FindPaymentSourceQuery(ownerUserId: $ownerUserId, paymentSourceId: $id));

        if (null === $source) {
            throw new NotFoundHttpException(\sprintf('Payment source with ID "%s" not found.', $id));
        }

        // The form is owner-scoped and excludes this source, so the target choice is validated (and CSRF
        // handled) here. A crafted or cross-owner target is not a valid choice and falls to the error path.
        $form = $this->createForm(
            type: ReassignSubscriptionsFormType::class,
            data: new ReassignSubscriptionsDto(),
            options: ['owner_id' => $ownerUserId, 'current_id' => $id],
        );
        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ReassignSubscriptionsDto $dto */
            $dto = $form->getData();
            \assert($dto->target instanceof PaymentSource);

            $this->commandBus->dispatch(command: new ReassignPaymentSourceSubscriptionsCommand(
                ownerUserId: $ownerUserId,
                fromPaymentSourceId: $id,
                toPaymentSourceId: $dto->target->id,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment_source.flash.reassigned'));

            return $this->redirectToRoute(route: 'payment_source_show', parameters: ['id' => $id]);
        }

        $this->logFormErrors(form: $form);
        $this->addFlash(type: self::FLASH_ERROR, message: $this->translator->trans('payment_source.flash.reassign_failed'));

        return $this->redirectToRoute(route: 'payment_source_show', parameters: ['id' => $id]);
    }
}
