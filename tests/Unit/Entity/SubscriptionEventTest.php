<?php

// ABOUTME: Unit tests for SubscriptionEvent entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify event creation for all types, context structure, and business invariants.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\SubscriptionEvent;
use App\Enum\PaymentPeriod;
use App\Enum\SubscriptionEventType;

beforeEach(function (): void {
    $category = new Category(name: 'Test Category');
    $this->subscription = new Subscription(
        category: $category,
        name: 'Test Subscription',
        lastPaidDate: new DateTimeImmutable(),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1000,
    );
});

describe('event creation', function (): void {
    test('creates update event', function (): void {
        $context = ['changes' => ['name' => 'New Name']];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Update,
            context: $context,
        );

        expect($event->subscription)->toBe($this->subscription)
            ->and($event->type)->toBe(SubscriptionEventType::Update)
            ->and($event->context)->toBe($context)
        ;
    });

    test('creates cost change event', function (): void {
        $context = ['old' => ['cost' => 1000], 'new' => ['cost' => 1500]];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::CostChange,
            context: $context,
        );

        expect($event->type)->toBe(SubscriptionEventType::CostChange)
            ->and($event->context)->toBe($context)
        ;
    });

    test('creates archive event', function (): void {
        $context = [];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Archive,
            context: $context,
        );

        expect($event->type)->toBe(SubscriptionEventType::Archive)
            ->and($event->context)->toBe($context)
        ;
    });

    test('creates unarchive event', function (): void {
        $context = [];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Unarchive,
            context: $context,
        );

        expect($event->type)->toBe(SubscriptionEventType::Unarchive)
            ->and($event->context)->toBe($context)
        ;
    });

    test('sets created at to current time', function (): void {
        $before = new DateTimeImmutable();
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Update,
            context: ['changes' => ['name' => 'Test']],
        );
        $after = new DateTimeImmutable();

        expect($event->createdAt)->toBeGreaterThanOrEqual($before)
            ->and($event->createdAt)->toBeLessThanOrEqual($after)
        ;
    });

    test('accepts complex context structure', function (): void {
        $context = [
            'old' => ['cost' => 1000, 'period' => 'month'],
            'new' => ['cost' => 1500, 'period' => 'year'],
        ];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::CostChange,
            context: $context,
        );

        expect($event->context)->toBe($context)
            ->and($event->context)->toHaveKey('old')
            ->and($event->context)->toHaveKey('new')
        ;
    });
});

describe('context validation', function (): void {
    test('rejects non-empty context for archive event', function (): void {
        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Archive,
            context: ['new' => ['some' => 'data']],
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects non-empty context for unarchive event', function (): void {
        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Unarchive,
            context: ['new' => ['some' => 'data']],
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects empty context for update event', function (): void {
        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Update,
            context: [],
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects empty context for cost change event', function (): void {
        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::CostChange,
            context: [],
        );
    })->throws(Assert\InvalidArgumentException::class);
});
