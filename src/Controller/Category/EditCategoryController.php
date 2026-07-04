<?php

// ABOUTME: Invokable controller for editing categories with form handling and validation.
// ABOUTME: Uses CQRS pattern to fetch and update category via queries and commands with flash messages.

declare(strict_types=1);

namespace App\Controller\Category;

use App\Controller\AbstractBaseController;
use App\Dto\Category\UpdateCategoryDto;
use App\Entity\Category;
use App\Form\Category\EditCategoryFormType;
use App\Message\Command\Category\UpdateCategoryCommand;
use App\Message\Query\Category\FindCategoryQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class EditCategoryController extends AbstractBaseController
{
    #[Route(path: '/categories/{id}/edit', name: 'category_edit', methods: ['GET', 'POST'])]
    public function __invoke(Ulid $id, Request $request): Response
    {
        $category = $this->queryBus->query(query: new FindCategoryQuery(ownerUserId: $this->currentUser()->id, categoryId: $id));

        if (null === $category) {
            throw new NotFoundHttpException(\sprintf('Category with ID "%s" not found.', $id));
        }

        \assert($category instanceof Category);

        $dto = new UpdateCategoryDto(category: $category);

        $form = $this->createForm(type: EditCategoryFormType::class, data: $dto);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UpdateCategoryDto $data */
            $data = $form->getData();

            $this->commandBus->dispatch(command: new UpdateCategoryCommand(
                ownerUserId: $this->currentUser()->id,
                categoryId: $id,
                name: $data->name,
                color: $data->color,
                icon: $data->icon,
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: $this->translator->trans('category.flash.updated'));

            return $this->redirectToRoute(route: 'category_show', parameters: ['id' => $id]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'category/edit.html.twig', parameters: [
            'form' => $form,
            'category' => $category,
        ]);
    }
}
