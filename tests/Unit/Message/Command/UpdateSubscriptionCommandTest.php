<?php

// ABOUTME: Unit tests for UpdateSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with all required update fields including subscriptionId.

declare(strict_types=1);

use App\Enum\PaymentPeriod;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;

test('creates command with all fields', function (): void {
    $lastPaidDate = new DateTimeImmutable('2026-01-15');

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

    expect($command->subscriptionId)->toBe('01JKSUB1234567890ABCDEFGH')
        ->and($command->categoryId)->toBe('01JKCAT1234567890ABCDEFGH')
        ->and($command->name)->toBe('Netflix Premium')
        ->and($command->lastPaidDate)->toBe($lastPaidDate)
        ->and($command->description)->toBe('Updated streaming service')
        ->and($command->link)->toBe('https://netflix.com/premium')
        ->and($command->logo)->toBe('netflix-premium.png')
        ->and($command->paymentPeriod)->toBe(PaymentPeriod::Year)
        ->and($command->paymentPeriodCount)->toBe(1)
        ->and($command->cost)->toBe(15999)
    ;
});

test('is readonly', function (): void {
    $command = new UpdateSubscriptionCommand(
        subscriptionId: '01JKSUB1234567890ABCDEFGH',
        categoryId: '01JKCAT1234567890ABCDEFGH',
        name: 'Test',
        lastPaidDate: new DateTimeImmutable(),
        description: 'Test description',
        link: 'https://test.com',
        logo: 'test.png',
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 100,
    );

    $reflection = new ReflectionClass($command);
    expect($reflection->isReadOnly())->toBeTrue();
});
