<?php

// ABOUTME: Invokable controller for creating new categories with form handling and validation.
// ABOUTME: Uses CQRS command pattern to create category via CreateCategoryCommand with flash messages.

declare(strict_types=1);

namespace App\Controller\Category;

use App\Controller\AbstractBaseController;
use App\Dto\Category\CreateCategoryDto;
use App\Form\Category\CreateCategoryFormType;
use App\Message\Command\Category\CreateCategoryCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateCategoryController extends AbstractBaseController
{
    #[Route(path: '/categories/new', name: 'category_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $dto = new CreateCategoryDto();
        $form = $this->createForm(type: CreateCategoryFormType::class, data: $dto);

        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreateCategoryDto $data */
            $data = $form->getData();

            $this->commandBus->dispatch(command: new CreateCategoryCommand(
                name: $data->name
            ));

            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Category created successfully');

            return $this->redirectToRoute(route: 'category_index');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'category/new.html.twig', parameters: [
            'form' => $form,
        ]);
    }
}
