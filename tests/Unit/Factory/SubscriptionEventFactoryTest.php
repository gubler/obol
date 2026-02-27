<?php

// ABOUTME: Unit tests for SubscriptionEventFactory ensuring proper factory defaults and state methods.
// ABOUTME: Tests verify event creation for all types and custom context overrides.

declare(strict_types=1);

use App\Enum\SubscriptionEventType;
use App\Factory\SubscriptionEventFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

uses(KernelTestCase::class);

test('allows custom context', function (): void {
    $context = ['field' => ['old' => 'value1', 'new' => 'value2']];
    $event = SubscriptionEventFactory::createOne(['type' => SubscriptionEventType::Update, 'context' => $context]);

    expect($event->context)->toBe($context);
});

test('update creates update event type', function (): void {
    $event = SubscriptionEventFactory::new()->update()->create();

    expect($event->type)->toBe(SubscriptionEventType::Update);
});

test('cost change creates cost change event type', function (): void {
    $event = SubscriptionEventFactory::new()->costChange()->create();

    expect($event->type)->toBe(SubscriptionEventType::CostChange);
});

test('archive creates archive event type', function (): void {
    $event = SubscriptionEventFactory::new()->archive()->create();

    expect($event->type)->toBe(SubscriptionEventType::Archive);
});

test('unarchive creates unarchive event type', function (): void {
    $event = SubscriptionEventFactory::new()->unarchive()->create();

    expect($event->type)->toBe(SubscriptionEventType::Unarchive);
});
