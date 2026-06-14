<?php

// ABOUTME: Unit tests for UpdateCategoryHandler verifying category name updates.
// ABOUTME: Tests that handler finds category and sets name; throws on not found.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Category;

use App\Entity\Category;
use App\Message\Command\Category\UpdateCategoryCommand;
use App\Message\Command\Category\UpdateCategoryHandler;
use App\Repository\CategoryRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UpdateCategoryHandlerTest extends TestCase
{
    public function testHandlerUpdatesCategoryName(): void
    {
        $ulid = new Ulid();

        $category = $this->createMock(Category::class);
        $category->expects(self::once())
            ->method('setName')
            ->with('Updated Name')
        ;

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($category)
        ;

        $handler = new UpdateCategoryHandler($repository);
        $handler(new UpdateCategoryCommand(categoryId: $ulid, name: 'Updated Name'));
    }

    public function testHandlerThrowsWhenCategoryNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $handler = new UpdateCategoryHandler($repository);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdateCategoryCommand(categoryId: $ulid, name: 'Updated Name'));
    }
}
