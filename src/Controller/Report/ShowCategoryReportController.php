<?php

// ABOUTME: Invokable controller for the /reports category drill-down: one category's subscriptions as a pie.
// ABOUTME: Dispatches FindCategoryBreakdownQuery, 404s on an unknown category, and builds the pie.

declare(strict_types=1);

namespace App\Controller\Report;

use App\Controller\AbstractBaseController;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindCategoryBreakdownQuery;
use App\Service\CompositionChartFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class ShowCategoryReportController extends AbstractBaseController
{
    public function __construct(private readonly CompositionChartFactory $chartFactory)
    {
    }

    #[Route(path: '/app/reports/categories/{id}', name: 'reports_category', methods: ['GET'])]
    public function __invoke(Ulid $id): Response
    {
        $composition = $this->queryBus->query(query: new FindCategoryBreakdownQuery(ownerUserId: $this->currentUser()->id, categoryId: $id));
        \assert(null === $composition || $composition instanceof Composition);

        if (!$composition instanceof Composition) {
            throw new NotFoundHttpException(\sprintf('Category with ID "%s" not found.', $id));
        }

        return $this->render(view: 'reports/category.html.twig', parameters: [
            'composition' => $composition,
            'chart' => $this->chartFactory->pie($composition),
        ]);
    }
}
