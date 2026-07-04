<?php

// ABOUTME: Unit tests for CreateCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with valid data and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Message\Command\Category\CreateCategoryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreateCategoryCommandTest extends TestCase
{
    public function testCreatesCommandWithOwnerNameColorAndIcon(): void
    {
        $ownerUserId = new Ulid();
        $command = new CreateCategoryCommand(ownerUserId: $ownerUserId, name: 'Entertainment', color: TileColor::Violet, icon: CategoryIcon::Tv);

        self::assertSame($ownerUserId, $command->ownerUserId);
        self::assertSame('Entertainment', $command->name);
        self::assertSame(TileColor::Violet, $command->color);
        self::assertSame(CategoryIcon::Tv, $command->icon);
    }

    public function testIsReadonly(): void
    {
        $command = new CreateCategoryCommand(ownerUserId: new Ulid(), name: 'Utilities', color: TileColor::Blue, icon: CategoryIcon::Tag);

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
