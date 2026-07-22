<?php

// ABOUTME: Invokable controller for displaying an individual payment source with its subscriptions.
// ABOUTME: Uses the CQRS query pattern to fetch the source via FindPaymentSourceQuery with 404 handling.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Dto\PaymentSource\ReassignSubscriptionsDto;
use App\Entity\PaymentSource;
use App\Form\PaymentSource\ReassignSubscriptionsFormType;
use App\Message\Query\PaymentSource\FindAllPaymentSourcesQuery;
use App\Message\Query\PaymentSource\FindPaymentSourceQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class ShowPaymentSourceController extends AbstractBaseController
{
    #[Route(path: '/app/payment-sources/{id}', name: 'payment_source_show', methods: ['GET'])]
    public function __invoke(Ulid $id): Response
    {
        $ownerUserId = $this->currentUser()->id;
        $paymentSource = $this->queryBus->query(query: new FindPaymentSourceQuery(ownerUserId: $ownerUserId, paymentSourceId: $id));

        if (null === $paymentSource) {
            throw new NotFoundHttpException(\sprintf('Payment source with ID "%s" not found.', $id));
        }

        \assert($paymentSource instanceof PaymentSource);

        // The "move all subscriptions" action is offered only when there is somewhere to move them - this
        // source has subscriptions and at least one other source exists to receive them. The form (posted
        // to the reassign action) is owner-scoped and excludes this source; see ReassignSubscriptionsFormType.
        /** @var array<PaymentSource> $allSources */
        $allSources = $this->queryBus->query(query: new FindAllPaymentSourcesQuery(ownerUserId: $ownerUserId));
        $hasOtherSources = array_any($allSources, static fn (PaymentSource $other): bool => !$other->id->equals($paymentSource->id));

        $reassignForm = null;
        if (\count($paymentSource->subscriptions) > 0 && $hasOtherSources) {
            $reassignForm = $this->createForm(
                type: ReassignSubscriptionsFormType::class,
                data: new ReassignSubscriptionsDto(),
                options: [
                    'owner_id' => $ownerUserId,
                    'current_id' => $paymentSource->id,
                    'action' => $this->generateUrl(route: 'payment_source_reassign', parameters: ['id' => $paymentSource->id]),
                ],
            );
        }

        return $this->render(view: 'payment_source/show.html.twig', parameters: [
            'payment_source' => $paymentSource,
            'reassign_form' => $reassignForm,
        ]);
    }
}
