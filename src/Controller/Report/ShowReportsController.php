<?php

// ABOUTME: Invokable controller for the /reports overview: the category-composition pie and the obligation trend.
// ABOUTME: Dispatches the composition and over-time queries and builds the charts via their factories.

declare(strict_types=1);

namespace App\Controller\Report;

use App\Controller\AbstractBaseController;
use App\Enum\ObligationTrendPeriod;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryCompositionQuery;
use App\Message\Query\Report\FindObligationOverTimeQuery;
use App\Message\Query\Report\FindPaymentSourceCompositionQuery;
use App\Message\Query\Report\ObligationSeries;
use App\Service\CompositionChartFactory;
use App\Service\ObligationTrendChartFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowReportsController extends AbstractBaseController
{
    public function __construct(private readonly CompositionChartFactory $chartFactory, private readonly ObligationTrendChartFactory $trendFactory)
    {
    }

    #[Route(path: '/reports', name: 'reports_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $ownerUserId = $this->currentUser()->id;

        $composition = $this->queryBus->query(query: new FindCategoryCompositionQuery(ownerUserId: $ownerUserId));
        \assert($composition instanceof Composition);

        $paymentSourceComposition = $this->queryBus->query(query: new FindPaymentSourceCompositionQuery(ownerUserId: $ownerUserId));
        \assert($paymentSourceComposition instanceof Composition);

        $trendPeriod = ObligationTrendPeriod::fromQuery($request->query->getString('trend'));
        $series = $this->queryBus->query(query: new FindObligationOverTimeQuery(period: $trendPeriod, ownerUserId: $ownerUserId));
        \assert($series instanceof ObligationSeries);

        return $this->render(view: 'reports/index.html.twig', parameters: [
            'composition' => $composition,
            'chart' => $this->chartFactory->pie($composition),
            'paymentSourceComposition' => $paymentSourceComposition,
            'paymentSourceChart' => $this->chartFactory->pie($paymentSourceComposition),
            'series' => $series,
            'trendChart' => $this->trendFactory->line($series),
            'trendPeriod' => $trendPeriod,
            'trendOptions' => ObligationTrendPeriod::cases(),
        ]);
    }
}
