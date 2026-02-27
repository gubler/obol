<?php

// ABOUTME: Unit tests for UpdateSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with all required update fields including subscriptionId.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Enum\PaymentPeriod;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use PHPUnit\Framework\TestCase;

class UpdateSubscriptionCommandTest extends TestCase
{
    public function testCreatesCommandWithAllFields(): void
    {
        $lastPaidDate = new \DateTimeImmutable('2026-01-15');

        $command = new UpdateSubscriptionCommand(
            subscriptionId: '01JKSUB1234567890ABCDEFGH',
            categoryId: '01JKCAT1234567890ABCDEFGH',
            name: 'Netflix Premium',
            lastPaidDate: $lastPaidDate,
            description: 'Updated streaming service',
            link: 'https://netflix.com/premium',
            logo: 'netflix-premium.png',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15999,
        );

        self::assertSame('01JKSUB1234567890ABCDEFGH', $command->subscriptionId);
        self::assertSame('01JKCAT1234567890ABCDEFGH', $command->categoryId);
        self::assertSame('Netflix Premium', $command->name);
        self::assertSame($lastPaidDate, $command->lastPaidDate);
        self::assertSame('Updated streaming service', $command->description);
        self::assertSame('https://netflix.com/premium', $command->link);
        self::assertSame('netflix-premium.png', $command->logo);
        self::assertSame(PaymentPeriod::Year, $command->paymentPeriod);
        self::assertSame(1, $command->paymentPeriodCount);
        self::assertSame(15999, $command->cost);
    }

    public function testIsReadonly(): void
    {
        $command = new UpdateSubscriptionCommand(
            subscriptionId: '01JKSUB1234567890ABCDEFGH',
            categoryId: '01JKCAT1234567890ABCDEFGH',
            name: 'Test',
            lastPaidDate: new \DateTimeImmutable(),
            description: 'Test description',
            link: 'https://test.com',
            logo: 'test.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 100,
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
