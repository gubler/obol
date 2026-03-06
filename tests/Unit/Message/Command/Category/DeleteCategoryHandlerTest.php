<?php

// ABOUTME: Unit tests for DeleteCategoryHandler verifying category removal via Doctrine.
// ABOUTME: Tests happy path, not-found, and has-subscriptions guard.

declare(strict_types=1);

use App\Entity\Category;
use App\Exception\CategoryHasSubscriptionsException;
use App\Message\Command\Category\DeleteCategoryCommand;
use App\Message\Command\Category\DeleteCategoryHandler;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('handler removes category with no subscriptions', function (): void {
    $ulid = new Ulid();

    $category = new Category(name: 'Test');

    $repository = $this->createMock(CategoryRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($category)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())
        ->method('remove')
        ->with($category)
    ;
    $entityManager->expects($this->once())->method('flush');

    $handler = new DeleteCategoryHandler($repository, $entityManager);
    $handler(new DeleteCategoryCommand(categoryId: $ulid->toRfc4122()));
});

test('handler throws when category not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(CategoryRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new DeleteCategoryHandler($repository, $entityManager);

    $handler(new DeleteCategoryCommand(categoryId: $ulid->toRfc4122()));
})->throws(InvalidArgumentException::class);

test('handler throws when category has subscriptions', function (): void {
    $ulid = new Ulid();

    $category = new Category(name: 'Test');

    // Use reflection to add items to the private(set) subscriptions collection
    $reflection = new ReflectionProperty(Category::class, 'subscriptions');
    $reflection->setValue($category, new ArrayCollection(['placeholder']));

    $repository = $this->createMock(CategoryRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($category)
    ;

    $entityManager = $this->createMock(EntityManagerInterface::class);

    $handler = new DeleteCategoryHandler($repository, $entityManager);

    $handler(new DeleteCategoryCommand(categoryId: $ulid->toRfc4122()));
})->throws(CategoryHasSubscriptionsException::class);
