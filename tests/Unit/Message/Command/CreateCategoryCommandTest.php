<?php

// ABOUTME: Unit tests for CreateCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with valid data and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Message\Command\Category\CreateCategoryCommand;
use PHPUnit\Framework\TestCase;

final class CreateCategoryCommandTest extends TestCase
{
    public function testCreatesCommandWithNameColorAndIcon(): void
    {
        $command = new CreateCategoryCommand(name: 'Entertainment', color: TileColor::Violet, icon: CategoryIcon::Tv);

        self::assertSame('Entertainment', $command->name);
        self::assertSame(TileColor::Violet, $command->color);
        self::assertSame(CategoryIcon::Tv, $command->icon);
    }

    public function testIsReadonly(): void
    {
        $command = new CreateCategoryCommand(name: 'Utilities', color: TileColor::Blue, icon: CategoryIcon::Tag);

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
