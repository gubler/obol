<?php

// ABOUTME: Invokable controller for displaying an individual payment source with its subscriptions.
// ABOUTME: Uses the CQRS query pattern to fetch the source via FindPaymentSourceQuery with 404 handling.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Entity\PaymentSource;
use App\Message\Query\PaymentSource\FindAllPaymentSourcesQuery;
use App\Message\Query\PaymentSource\FindPaymentSourceQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class ShowPaymentSourceController extends AbstractBaseController
{
    #[Route(path: '/payment-sources/{id}', name: 'payment_source_show', methods: ['GET'])]
    public function __invoke(Ulid $id): Response
    {
        $paymentSource = $this->queryBus->query(query: new FindPaymentSourceQuery(paymentSourceId: $id));

        if (null === $paymentSource) {
            throw new NotFoundHttpException(\sprintf('Payment source with ID "%s" not found.', $id));
        }

        \assert($paymentSource instanceof PaymentSource);

        // Other sources are the candidate targets for the "move all subscriptions" action.
        /** @var array<PaymentSource> $allSources */
        $allSources = $this->queryBus->query(query: new FindAllPaymentSourcesQuery());
        $otherSources = array_filter(
            $allSources,
            static fn (PaymentSource $other): bool => !$other->id->equals($paymentSource->id),
        );

        return $this->render(view: 'payment_source/show.html.twig', parameters: [
            'payment_source' => $paymentSource,
            'other_payment_sources' => $otherSources,
        ]);
    }
}
