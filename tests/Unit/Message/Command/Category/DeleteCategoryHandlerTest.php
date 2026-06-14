<?php

// ABOUTME: Unit tests for DeleteCategoryHandler verifying category removal via Doctrine.
// ABOUTME: Tests happy path, not-found, and has-subscriptions guard.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Category;

use App\Entity\Category;
use App\Exception\CategoryHasSubscriptionsException;
use App\Message\Command\Category\DeleteCategoryCommand;
use App\Message\Command\Category\DeleteCategoryHandler;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class DeleteCategoryHandlerTest extends TestCase
{
    public function testHandlerRemovesCategoryWithNoSubscriptions(): void
    {
        $ulid = new Ulid();

        $category = new Category(name: 'Test');

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($category)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('remove')
            ->with($category)
        ;
        // The command bus owns the transaction (doctrine_transaction middleware); the handler never flushes.
        $entityManager->expects(self::never())->method('flush');

        $handler = new DeleteCategoryHandler($repository, $entityManager);
        $handler(new DeleteCategoryCommand(categoryId: $ulid));
    }

    public function testHandlerThrowsWhenCategoryNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $entityManager = self::createStub(EntityManagerInterface::class);

        $handler = new DeleteCategoryHandler($repository, $entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new DeleteCategoryCommand(categoryId: $ulid));
    }

    public function testHandlerThrowsWhenCategoryHasSubscriptions(): void
    {
        $ulid = new Ulid();

        $category = new Category(name: 'Test');

        // Use reflection to add items to the private(set) subscriptions collection
        $reflection = new \ReflectionProperty(Category::class, 'subscriptions');
        $reflection->setValue($category, new ArrayCollection(['placeholder']));

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($category)
        ;

        $entityManager = self::createStub(EntityManagerInterface::class);

        $handler = new DeleteCategoryHandler($repository, $entityManager);

        $this->expectException(CategoryHasSubscriptionsException::class);
        $handler(new DeleteCategoryCommand(categoryId: $ulid));
    }
}
