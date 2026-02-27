<?php

// ABOUTME: Invokable controller for deleting categories with subscription validation.
// ABOUTME: Uses CQRS command pattern with error handling for categories that have subscriptions.

declare(strict_types=1);

namespace App\Controller\Category;

use App\Controller\AbstractBaseController;
use App\Message\Command\Category\DeleteCategoryCommand;
use App\Message\Query\Category\FindCategoryQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteCategoryController extends AbstractBaseController
{
    #[Route(path: '/categories/{id}/delete', name: 'category_delete', methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        $category = $this->queryBus->query(query: new FindCategoryQuery(categoryId: $id));

        if (null === $category) {
            throw new NotFoundHttpException(\sprintf('Category with ID "%s" not found.', $id));
        }

        try {
            $this->commandBus->dispatch(command: new DeleteCategoryCommand(categoryId: $id));

            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Category deleted successfully');
        } catch (\Exception $e) {
            $this->addFlash(
                type: self::FLASH_ERROR,
                message: 'Cannot delete category with subscriptions. Please reassign or delete subscriptions first.'
            );

            return $this->redirectToRoute(route: 'category_show', parameters: ['id' => $id]);
        }

        return $this->redirectToRoute(route: 'category_index');
    }
}
