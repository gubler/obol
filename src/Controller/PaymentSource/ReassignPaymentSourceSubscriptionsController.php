<?php

// ABOUTME: Invokable controller for moving every subscription from one payment source to another.
// ABOUTME: Reads the destination from the form, dispatches the reassign command, and flashes the result.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Message\Command\PaymentSource\ReassignPaymentSourceSubscriptionsCommand;
use App\Message\Query\PaymentSource\FindPaymentSourceQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class ReassignPaymentSourceSubscriptionsController extends AbstractBaseController
{
    #[Route(path: '/payment-sources/{id}/reassign', name: 'payment_source_reassign', methods: ['POST'])]
    public function __invoke(Ulid $id, Request $request): RedirectResponse
    {
        $source = $this->queryBus->query(query: new FindPaymentSourceQuery(ownerUserId: $this->currentUser()->id, paymentSourceId: $id));

        if (null === $source) {
            throw new NotFoundHttpException(\sprintf('Payment source with ID "%s" not found.', $id));
        }

        $target = (string) $request->request->get('target', '');

        if (!Ulid::isValid($target)) {
            $this->addFlash(type: self::FLASH_ERROR, message: $this->translator->trans('payment_source.flash.reassign_failed'));

            return $this->redirectToRoute(route: 'payment_source_show', parameters: ['id' => $id]);
        }

        try {
            $this->commandBus->dispatch(command: new ReassignPaymentSourceSubscriptionsCommand(
                ownerUserId: $this->currentUser()->id,
                fromPaymentSourceId: $id,
                toPaymentSourceId: Ulid::fromString($target),
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment_source.flash.reassigned'));
        } catch (\Exception) {
            $this->addFlash(type: self::FLASH_ERROR, message: $this->translator->trans('payment_source.flash.reassign_failed'));
        }

        return $this->redirectToRoute(route: 'payment_source_show', parameters: ['id' => $id]);
    }
}
