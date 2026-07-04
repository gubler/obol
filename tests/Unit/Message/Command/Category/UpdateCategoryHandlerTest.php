<?php

// ABOUTME: Unit tests for UpdateCategoryHandler verifying category name updates.
// ABOUTME: Tests that handler finds category and sets name; throws on not found.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Category;

use App\Entity\Category;
use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Message\Command\Category\UpdateCategoryCommand;
use App\Message\Command\Category\UpdateCategoryHandler;
use App\Repository\CategoryRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UpdateCategoryHandlerTest extends TestCase
{
    public function testHandlerUpdatesCategoryNameColorAndIcon(): void
    {
        $category = $this->createMock(Category::class);
        $category->expects(self::once())
            ->method('update')
            ->with('Updated Name', TileColor::Teal, CategoryIcon::Film)
        ;

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn($category)
        ;

        $handler = new UpdateCategoryHandler($repository);
        $handler(new UpdateCategoryCommand(ownerUserId: new Ulid(), categoryId: new Ulid(), name: 'Updated Name', color: TileColor::Teal, icon: CategoryIcon::Film));
    }

    public function testHandlerThrowsWhenCategoryNotFoundForOwner(): void
    {
        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn(null)
        ;

        $handler = new UpdateCategoryHandler($repository);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdateCategoryCommand(ownerUserId: new Ulid(), categoryId: new Ulid(), name: 'Updated Name', color: TileColor::Blue, icon: CategoryIcon::Tag));
    }
}
