<?php

// ABOUTME: Unit tests for UnarchiveSubscriptionCommand ensuring proper instantiation and immutability.
// ABOUTME: Tests verify command creates with subscription ID and maintains readonly properties.

declare(strict_types=1);

use App\Message\Command\Subscription\UnarchiveSubscriptionCommand;

test('creates command with subscription id', function (): void {
    $subscriptionId = '01JKSUB1234567890ABCDEFGH';
    $command = new UnarchiveSubscriptionCommand(subscriptionId: $subscriptionId);

    expect($command->subscriptionId)->toBe($subscriptionId);
});

test('is readonly', function (): void {
    $command = new UnarchiveSubscriptionCommand(
        subscriptionId: '01JKSUB1234567890ABCDEFGH'
    );

    $reflection = new ReflectionClass($command);
    expect($reflection->isReadOnly())->toBeTrue();
});
