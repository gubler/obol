<?php

// ABOUTME: Unit tests for CreateSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with all required subscription fields and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Enum\PaymentPeriod;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use PHPUnit\Framework\TestCase;

class CreateSubscriptionCommandTest extends TestCase
{
    public function testCreatesCommandWithAllFields(): void
    {
        $lastPaidDate = new \DateTimeImmutable('2026-01-01');

        $command = new CreateSubscriptionCommand(
            categoryId: '01JKTEST1234567890ABCDEFGH',
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1599,
            description: 'Streaming service',
            link: 'https://netflix.com',
            logo: 'netflix.png',
        );

        self::assertSame('01JKTEST1234567890ABCDEFGH', $command->categoryId);
        self::assertSame('Netflix', $command->name);
        self::assertSame($lastPaidDate, $command->lastPaidDate);
        self::assertSame(PaymentPeriod::Month, $command->paymentPeriod);
        self::assertSame(1, $command->paymentPeriodCount);
        self::assertSame(1599, $command->cost);
        self::assertSame('Streaming service', $command->description);
        self::assertSame('https://netflix.com', $command->link);
        self::assertSame('netflix.png', $command->logo);
    }

    public function testCreatesCommandWithOptionalFieldDefaults(): void
    {
        $lastPaidDate = new \DateTimeImmutable('2026-01-01');

        $command = new CreateSubscriptionCommand(
            categoryId: '01JKTEST1234567890ABCDEFGH',
            name: 'Spotify',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 999,
        );

        self::assertSame('01JKTEST1234567890ABCDEFGH', $command->categoryId);
        self::assertSame('Spotify', $command->name);
        self::assertSame($lastPaidDate, $command->lastPaidDate);
        self::assertSame(PaymentPeriod::Month, $command->paymentPeriod);
        self::assertSame(1, $command->paymentPeriodCount);
        self::assertSame(999, $command->cost);
        self::assertSame('', $command->description);
        self::assertSame('', $command->link);
        self::assertSame('', $command->logo);
    }

    public function testIsReadonly(): void
    {
        $command = new CreateSubscriptionCommand(
            categoryId: '01JKTEST1234567890ABCDEFGH',
            name: 'Test',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 100,
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
