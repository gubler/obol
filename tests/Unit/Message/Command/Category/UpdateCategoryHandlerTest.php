<?php

// ABOUTME: Unit tests for UpdateCategoryHandler verifying category name updates via Doctrine.
// ABOUTME: Tests that handler finds category, sets name, and flushes; throws on not found.

declare(strict_types=1);

use App\Entity\Category;
use App\Message\Command\Category\UpdateCategoryCommand;
use App\Message\Command\Category\UpdateCategoryHandler;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler updates category name', function (): void {
    $ulid = new Ulid();

    $category = $this->createMock(Category::class);
    $category->expects($this->once())
        ->method('setName')
        ->with('Updated Name')
    ;

    $repository = $this->createMock(CategoryRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($category)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    $handler = new UpdateCategoryHandler($repository, $entityManager);
    $handler(new UpdateCategoryCommand(categoryId: $ulid->toRfc4122(), name: 'Updated Name'));
});

test('handler throws when category not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(CategoryRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new UpdateCategoryHandler($repository, $entityManager);

    $handler(new UpdateCategoryCommand(categoryId: $ulid->toRfc4122(), name: 'Updated Name'));
})->throws(InvalidArgumentException::class);
