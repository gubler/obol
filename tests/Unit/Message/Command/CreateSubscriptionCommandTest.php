<?php

// ABOUTME: Unit tests for CreateSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with all required subscription fields and maintains readonly properties.

declare(strict_types=1);

use App\Enum\PaymentPeriod;
use App\Message\Command\Subscription\CreateSubscriptionCommand;

test('creates command with all fields', function (): void {
    $lastPaidDate = new DateTimeImmutable('2026-01-01');

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

    expect($command->categoryId)->toBe('01JKTEST1234567890ABCDEFGH')
        ->and($command->name)->toBe('Netflix')
        ->and($command->lastPaidDate)->toBe($lastPaidDate)
        ->and($command->paymentPeriod)->toBe(PaymentPeriod::Month)
        ->and($command->paymentPeriodCount)->toBe(1)
        ->and($command->cost)->toBe(1599)
        ->and($command->description)->toBe('Streaming service')
        ->and($command->link)->toBe('https://netflix.com')
        ->and($command->logo)->toBe('netflix.png')
    ;
});

test('creates command with optional field defaults', function (): void {
    $lastPaidDate = new DateTimeImmutable('2026-01-01');

    $command = new CreateSubscriptionCommand(
        categoryId: '01JKTEST1234567890ABCDEFGH',
        name: 'Spotify',
        lastPaidDate: $lastPaidDate,
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 999,
    );

    expect($command->categoryId)->toBe('01JKTEST1234567890ABCDEFGH')
        ->and($command->name)->toBe('Spotify')
        ->and($command->lastPaidDate)->toBe($lastPaidDate)
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
        categoryId: '01JKTEST1234567890ABCDEFGH',
        name: 'Test',
        lastPaidDate: new DateTimeImmutable(),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 100,
    );

    $reflection = new ReflectionClass($command);
    expect($reflection->isReadOnly())->toBeTrue();
});
