<?php

// ABOUTME: Invokable controller for deleting payment sources with subscription validation.
// ABOUTME: Uses the CQRS command pattern with error handling for sources that still have subscriptions.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Message\Command\PaymentSource\DeletePaymentSourceCommand;
use App\Message\Query\PaymentSource\FindPaymentSourceQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Uid\Ulid;

final class DeletePaymentSourceController extends AbstractBaseController
{
    #[IsCsrfTokenValid(id: 'submit')]
    #[Route(path: '/app/payment-sources/{id}/delete', name: 'payment_source_delete', methods: ['POST'])]
    public function __invoke(Ulid $id): RedirectResponse
    {
        $paymentSource = $this->queryBus->query(query: new FindPaymentSourceQuery(ownerUserId: $this->currentUser()->id, paymentSourceId: $id));

        if (null === $paymentSource) {
            throw new NotFoundHttpException(\sprintf('Payment source with ID "%s" not found.', $id));
        }

        try {
            $this->commandBus->dispatch(command: new DeletePaymentSourceCommand(ownerUserId: $this->currentUser()->id, paymentSourceId: $id));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('payment_source.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash(
                type: self::FLASH_ERROR,
                message: $this->translator->trans('payment_source.flash.delete_failed')
            );

            return $this->redirectToRoute(route: 'payment_source_show', parameters: ['id' => $id]);
        }

        return $this->redirectToRoute(route: 'payment_source_index');
    }
}
