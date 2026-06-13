<?php

// ABOUTME: Invokable controller for the /reports overview: the category-composition pie over active subscriptions.
// ABOUTME: Dispatches FindCategoryCompositionQuery and builds the pie via CompositionChartFactory.

declare(strict_types=1);

namespace App\Controller\Report;

use App\Controller\AbstractBaseController;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryCompositionQuery;
use App\Service\CompositionChartFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowReportsController extends AbstractBaseController
{
    #[Route(path: '/reports', name: 'reports_index', methods: ['GET'])]
    public function __invoke(CompositionChartFactory $chartFactory): Response
    {
        $composition = $this->queryBus->query(query: new FindCategoryCompositionQuery());
        \assert($composition instanceof Composition);

        return $this->render(view: 'reports/index.html.twig', parameters: [
            'composition' => $composition,
            'chart' => $chartFactory->pie($composition),
        ]);
    }
}
