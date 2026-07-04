<?php

// ABOUTME: Unit tests for UpdateCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with category ID and name, maintaining readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Message\Command\Category\UpdateCategoryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UpdateCategoryCommandTest extends TestCase
{
    public function testCreatesCommandWithCategoryIdNameColorAndIcon(): void
    {
        $ownerUserId = new Ulid();
        $categoryId = new Ulid();
        $command = new UpdateCategoryCommand(
            ownerUserId: $ownerUserId,
            categoryId: $categoryId,
            name: 'Updated Name',
            color: TileColor::Teal,
            icon: CategoryIcon::Film,
        );

        self::assertSame($ownerUserId, $command->ownerUserId);
        self::assertSame($categoryId, $command->categoryId);
        self::assertSame('Updated Name', $command->name);
        self::assertSame(TileColor::Teal, $command->color);
        self::assertSame(CategoryIcon::Film, $command->icon);
    }

    public function testIsReadonly(): void
    {
        $command = new UpdateCategoryCommand(
            ownerUserId: new Ulid(),
            categoryId: new Ulid(),
            name: 'Software',
            color: TileColor::Blue,
            icon: CategoryIcon::Tag,
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
