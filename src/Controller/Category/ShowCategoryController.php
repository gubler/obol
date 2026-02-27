<?php

// ABOUTME: Invokable controller for displaying individual category details with subscriptions.
// ABOUTME: Uses CQRS query pattern to fetch category via FindCategoryQuery with 404 handling.

declare(strict_types=1);

namespace App\Controller\Category;

use App\Controller\AbstractBaseController;
use App\Message\Query\Category\FindCategoryQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ShowCategoryController extends AbstractBaseController
{
    #[Route(path: '/categories/{id}', name: 'category_show', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $category = $this->queryBus->query(query: new FindCategoryQuery(categoryId: $id));

        if (null === $category) {
            throw new NotFoundHttpException(\sprintf('Category with ID "%s" not found.', $id));
        }

        return $this->render(view: 'category/show.html.twig', parameters: [
            'category' => $category,
        ]);
    }
}
