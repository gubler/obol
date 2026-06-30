<?php

// ABOUTME: Invokable controller for the /reports payment-source drill-down: one source's subscriptions as a pie.
// ABOUTME: Dispatches FindPaymentSourceBreakdownQuery, 404s on an unknown source, and builds the pie.

declare(strict_types=1);

namespace App\Controller\Report;

use App\Controller\AbstractBaseController;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindPaymentSourceBreakdownQuery;
use App\Service\CompositionChartFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class ShowPaymentSourceReportController extends AbstractBaseController
{
    public function __construct(private readonly CompositionChartFactory $chartFactory)
    {
    }

    #[Route(path: '/reports/payment-sources/{id}', name: 'reports_payment_source', methods: ['GET'])]
    public function __invoke(Ulid $id): Response
    {
        $composition = $this->queryBus->query(query: new FindPaymentSourceBreakdownQuery(paymentSourceId: $id));
        \assert(null === $composition || $composition instanceof Composition);

        if (!$composition instanceof Composition) {
            throw new NotFoundHttpException(\sprintf('Payment source with ID "%s" not found.', $id));
        }

        return $this->render(view: 'reports/payment_source.html.twig', parameters: [
            'composition' => $composition,
            'chart' => $this->chartFactory->pie($composition),
        ]);
    }
}
