<?php

// ABOUTME: Unit tests for CreateSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with all required subscription fields and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreateSubscriptionCommandTest extends TestCase
{
    public function testCreatesCommandWithAllFields(): void
    {
        $ownerUserId = new Ulid();
        $categoryId = new Ulid();
        $nextRenewal = CalendarDate::fromString('2026-01-01');

        $command = new CreateSubscriptionCommand(
            ownerUserId: $ownerUserId,
            categoryId: $categoryId,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1599,
            currency: Currency::EUR,
            color: TileColor::Blue,
            description: 'Streaming service',
            link: 'https://netflix.com',
            logo: 'netflix.png',
        );

        self::assertSame($ownerUserId, $command->ownerUserId);
        self::assertSame($categoryId, $command->categoryId);
        self::assertSame('Netflix', $command->name);
        self::assertSame($nextRenewal, $command->nextRenewal);
        self::assertSame(PaymentPeriod::Month, $command->paymentPeriod);
        self::assertSame(1, $command->paymentPeriodCount);
        self::assertSame(1599, $command->cost);
        self::assertSame(Currency::EUR, $command->currency);
        self::assertSame('Streaming service', $command->description);
        self::assertSame('https://netflix.com', $command->link);
        self::assertSame('netflix.png', $command->logo);
        self::assertSame(TileColor::Blue, $command->color);
    }

    public function testCreatesCommandWithOptionalFieldDefaults(): void
    {
        $categoryId = new Ulid();
        $nextRenewal = CalendarDate::fromString('2026-01-01');

        $command = new CreateSubscriptionCommand(
            ownerUserId: new Ulid(),
            categoryId: $categoryId,
            name: 'Spotify',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 999,
            currency: Currency::USD,
            color: TileColor::Blue,
        );

        self::assertSame($categoryId, $command->categoryId);
        self::assertSame('Spotify', $command->name);
        self::assertSame($nextRenewal, $command->nextRenewal);
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
            ownerUserId: new Ulid(),
            categoryId: new Ulid(),
            name: 'Test',
            nextRenewal: CalendarDate::fromString('2024-01-01'),
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
