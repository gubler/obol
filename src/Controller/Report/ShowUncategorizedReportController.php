<?php

// ABOUTME: Invokable controller for the /reports uncategorized drill-down: subscriptions with no category as a pie.
// ABOUTME: Dispatches FindCategoryBreakdownQuery with a null category id and builds the pie.

declare(strict_types=1);

namespace App\Controller\Report;

use App\Controller\AbstractBaseController;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryBreakdownQuery;
use App\Service\CompositionChartFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowUncategorizedReportController extends AbstractBaseController
{
    public function __construct(private readonly CompositionChartFactory $chartFactory)
    {
    }

    // Higher priority than the `{id}` drill-down so the literal "uncategorized" segment is never
    // resolved as a category Ulid.
    #[Route(path: '/reports/categories/uncategorized', name: 'reports_uncategorized', methods: ['GET'], priority: 10)]
    public function __invoke(): Response
    {
        $composition = $this->queryBus->query(query: new FindCategoryBreakdownQuery(categoryId: null));
        \assert($composition instanceof Composition);

        return $this->render(view: 'reports/category.html.twig', parameters: [
            'composition' => $composition,
            'chart' => $this->chartFactory->pie($composition),
        ]);
    }
}
