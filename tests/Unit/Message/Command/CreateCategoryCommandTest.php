<?php

// ABOUTME: Unit tests for CreateCategoryCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with valid data and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Message\Command\Category\CreateCategoryCommand;
use PHPUnit\Framework\TestCase;

class CreateCategoryCommandTest extends TestCase
{
    public function testCreatesCommandWithName(): void
    {
        $command = new CreateCategoryCommand(name: 'Entertainment');

        self::assertSame('Entertainment', $command->name);
    }

    public function testIsReadonly(): void
    {
        $command = new CreateCategoryCommand(name: 'Utilities');

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
