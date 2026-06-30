<?php

// ABOUTME: Invokable controller for the /reports unassigned drill-down: subscriptions with no source as a pie.
// ABOUTME: Dispatches FindPaymentSourceBreakdownQuery with a null source id and builds the pie.

declare(strict_types=1);

namespace App\Controller\Report;

use App\Controller\AbstractBaseController;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindPaymentSourceBreakdownQuery;
use App\Service\CompositionChartFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowUnassignedReportController extends AbstractBaseController
{
    public function __construct(private readonly CompositionChartFactory $chartFactory)
    {
    }

    // Higher priority than the `{id}` drill-down so the literal "unassigned" segment is never
    // resolved as a payment-source Ulid.
    #[Route(path: '/reports/payment-sources/unassigned', name: 'reports_unassigned', methods: ['GET'], priority: 10)]
    public function __invoke(): Response
    {
        $composition = $this->queryBus->query(query: new FindPaymentSourceBreakdownQuery(paymentSourceId: null));
        \assert($composition instanceof Composition);

        return $this->render(view: 'reports/payment_source.html.twig', parameters: [
            'composition' => $composition,
            'chart' => $this->chartFactory->pie($composition),
        ]);
    }
}
