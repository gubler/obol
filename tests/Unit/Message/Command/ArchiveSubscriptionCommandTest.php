<?php

// ABOUTME: Unit tests for ArchiveSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with subscription ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Message\Command\Subscription\ArchiveSubscriptionCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ArchiveSubscriptionCommandTest extends TestCase
{
    public function testCreatesCommandWithSubscriptionId(): void
    {
        $subscriptionId = new Ulid();
        $command = new ArchiveSubscriptionCommand(subscriptionId: $subscriptionId);

        self::assertSame($subscriptionId, $command->subscriptionId);
    }

    public function testIsReadonly(): void
    {
        $command = new ArchiveSubscriptionCommand(
            subscriptionId: new Ulid()
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
