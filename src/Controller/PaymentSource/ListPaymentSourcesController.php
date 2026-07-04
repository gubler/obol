<?php

// ABOUTME: Invokable controller for displaying the list of all payment sources with subscription counts.
// ABOUTME: Uses the CQRS query pattern to fetch sources via FindAllPaymentSourcesQuery.

declare(strict_types=1);

namespace App\Controller\PaymentSource;

use App\Controller\AbstractBaseController;
use App\Message\Query\PaymentSource\FindAllPaymentSourcesQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListPaymentSourcesController extends AbstractBaseController
{
    #[Route(path: '/payment-sources', name: 'payment_source_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $paymentSources = $this->queryBus->query(query: new FindAllPaymentSourcesQuery(ownerUserId: $this->currentUser()->id));

        return $this->render(view: 'payment_source/index.html.twig', parameters: [
            'payment_sources' => $paymentSources,
        ]);
    }
}
