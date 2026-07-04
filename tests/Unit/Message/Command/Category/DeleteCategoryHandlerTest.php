<?php

// ABOUTME: Unit tests for DeleteCategoryHandler verifying category removal via Doctrine.
// ABOUTME: Tests happy path, not-found, and has-subscriptions guard.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Category;

use App\Entity\Category;
use App\Entity\User;
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
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Test');

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
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
        $handler(new DeleteCategoryCommand(ownerUserId: new Ulid(), categoryId: new Ulid()));
    }

    public function testHandlerThrowsWhenCategoryNotFoundForOwner(): void
    {
        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn(null)
        ;

        $entityManager = self::createStub(EntityManagerInterface::class);

        $handler = new DeleteCategoryHandler($repository, $entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new DeleteCategoryCommand(ownerUserId: new Ulid(), categoryId: new Ulid()));
    }

    public function testHandlerThrowsWhenCategoryHasSubscriptions(): void
    {
        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Test');

        // Use reflection to add items to the private(set) subscriptions collection
        $reflection = new \ReflectionProperty(Category::class, 'subscriptions');
        $reflection->setValue($category, new ArrayCollection(['placeholder']));

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn($category)
        ;

        $entityManager = self::createStub(EntityManagerInterface::class);

        $handler = new DeleteCategoryHandler($repository, $entityManager);

        $this->expectException(CategoryHasSubscriptionsException::class);
        $handler(new DeleteCategoryCommand(ownerUserId: new Ulid(), categoryId: new Ulid()));
    }
}
