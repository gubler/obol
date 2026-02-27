<?php

// ABOUTME: Invokable controller for displaying list of all categories with subscription counts.
// ABOUTME: Uses CQRS query pattern to fetch categories via FindAllCategoriesQuery.

declare(strict_types=1);

namespace App\Controller\Category;

use App\Controller\AbstractBaseController;
use App\Message\Query\Category\FindAllCategoriesQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListCategoriesController extends AbstractBaseController
{
    #[Route(path: '/categories', name: 'category_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $categories = $this->queryBus->query(query: new FindAllCategoriesQuery());

        return $this->render(view: 'category/index.html.twig', parameters: [
            'categories' => $categories,
        ]);
    }
}
