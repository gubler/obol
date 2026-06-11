<?php

// ABOUTME: Unit tests for CreateSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with all required subscription fields and maintains readonly properties.

declare(strict_types=1);

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use Symfony\Component\Uid\Ulid;

test('creates command with all fields', function (): void {
    $categoryId = new Ulid();
    $nextRenewal = new DateTimeImmutable('2026-01-01');

    $command = new CreateSubscriptionCommand(
        categoryId: $categoryId,
        name: 'Netflix',
        nextRenewal: $nextRenewal,
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1599,
        currency: Currency::EUR,
        description: 'Streaming service',
        link: 'https://netflix.com',
        logo: 'netflix.png',
        color: TileColor::Blue,
    );

    expect($command->categoryId)->toBe($categoryId)
        ->and($command->name)->toBe('Netflix')
        ->and($command->nextRenewal)->toBe($nextRenewal)
        ->and($command->paymentPeriod)->toBe(PaymentPeriod::Month)
        ->and($command->paymentPeriodCount)->toBe(1)
        ->and($command->cost)->toBe(1599)
        ->and($command->currency)->toBe(Currency::EUR)
        ->and($command->description)->toBe('Streaming service')
        ->and($command->link)->toBe('https://netflix.com')
        ->and($command->logo)->toBe('netflix.png')
        ->and($command->color)->toBe(TileColor::Blue)
    ;
});

test('creates command with optional field defaults', function (): void {
    $categoryId = new Ulid();
    $nextRenewal = new DateTimeImmutable('2026-01-01');

    $command = new CreateSubscriptionCommand(
        categoryId: $categoryId,
        name: 'Spotify',
        nextRenewal: $nextRenewal,
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 999,
        currency: Currency::USD,
        color: TileColor::Blue,
    );

    expect($command->categoryId)->toBe($categoryId)
        ->and($command->name)->toBe('Spotify')
        ->and($command->nextRenewal)->toBe($nextRenewal)
        ->and($command->paymentPeriod)->toBe(PaymentPeriod::Month)
        ->and($command->paymentPeriodCount)->toBe(1)
        ->and($command->cost)->toBe(999)
        ->and($command->description)->toBe('')
        ->and($command->link)->toBe('')
        ->and($command->logo)->toBe('')
    ;
});

test('is readonly', function (): void {
    $command = new CreateSubscriptionCommand(
        categoryId: new Ulid(),
        name: 'Test',
        nextRenewal: new DateTimeImmutable(),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 100,
        currency: Currency::USD,
        color: TileColor::Blue,
    );

    $reflection = new ReflectionClass($command);
    expect($reflection->isReadOnly())->toBeTrue();
});
