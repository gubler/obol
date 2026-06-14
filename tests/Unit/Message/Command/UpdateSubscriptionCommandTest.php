<?php

// ABOUTME: Unit tests for UpdateSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with all required update fields including subscriptionId.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UpdateSubscriptionCommandTest extends TestCase
{
    public function testCreatesCommandWithAllFields(): void
    {
        $subscriptionId = new Ulid();
        $categoryId = new Ulid();
        $nextRenewal = new \DateTimeImmutable('2026-01-15');

        $command = new UpdateSubscriptionCommand(
            subscriptionId: $subscriptionId,
            categoryId: $categoryId,
            name: 'Netflix Premium',
            nextRenewal: $nextRenewal,
            description: 'Updated streaming service',
            link: 'https://netflix.com/premium',
            logo: 'netflix-premium.png',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15999,
            currency: Currency::EUR,
            color: TileColor::Blue,
        );

        self::assertSame($subscriptionId, $command->subscriptionId);
        self::assertSame($categoryId, $command->categoryId);
        self::assertSame('Netflix Premium', $command->name);
        self::assertSame($nextRenewal, $command->nextRenewal);
        self::assertSame('Updated streaming service', $command->description);
        self::assertSame('https://netflix.com/premium', $command->link);
        self::assertSame('netflix-premium.png', $command->logo);
        self::assertSame(PaymentPeriod::Year, $command->paymentPeriod);
        self::assertSame(1, $command->paymentPeriodCount);
        self::assertSame(15999, $command->cost);
        self::assertSame(Currency::EUR, $command->currency);
        self::assertSame(TileColor::Blue, $command->color);
    }

    public function testIsReadonly(): void
    {
        $command = new UpdateSubscriptionCommand(
            subscriptionId: new Ulid(),
            categoryId: new Ulid(),
            name: 'Test',
            nextRenewal: new \DateTimeImmutable(),
            description: 'Test description',
            link: 'https://test.com',
            logo: 'test.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 100,
            currency: Currency::USD,
            color: TileColor::Blue,
        );

        $reflection = new \ReflectionClass($command);
        self::assertTrue($reflection->isReadOnly());
    }
}
