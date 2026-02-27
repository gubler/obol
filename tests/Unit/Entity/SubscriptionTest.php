<?php

// ABOUTME: Unit tests for Subscription entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify creation, update logic, payment recording, archival, and business invariants.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionEvent;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;

beforeEach(function (): void {
    $this->category = new Category(name: 'Entertainment');
});

describe('creation', function (): void {
    test('creates subscription with valid data', function (): void {
        $lastPaidDate = new DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->category)->toBe($this->category)
            ->and($subscription->name)->toBe('Netflix')
            ->and($subscription->lastPaidDate)->toBe($lastPaidDate)
            ->and($subscription->paymentPeriod)->toBe(PaymentPeriod::Month)
            ->and($subscription->paymentPeriodCount)->toBe(1)
            ->and($subscription->cost)->toBe(1500)
        ;
    });

    test('sets created at to current time', function (): void {
        $before = new DateTimeImmutable();
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );
        $after = new DateTimeImmutable();

        expect($subscription->createdAt)->toBeGreaterThanOrEqual($before)
            ->and($subscription->createdAt)->toBeLessThanOrEqual($after)
        ;
    });

    test('initializes as not archived', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );

        expect($subscription->archived)->toBeFalse();
    });

    test('initializes empty collections', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );

        expect($subscription->payments)->toHaveCount(0)
            ->and($subscription->subscriptionEvents)->toHaveCount(0)
        ;
    });

    test('accepts optional fields', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            description: 'Streaming service',
            link: 'https://netflix.com',
            logo: 'netflix.png',
        );

        expect($subscription->description)->toBe('Streaming service')
            ->and($subscription->link)->toBe('https://netflix.com')
            ->and($subscription->logo)->toBe('netflix.png')
        ;
    });

    test('defaults optional fields to empty', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );

        expect($subscription->description)->toBe('')
            ->and($subscription->link)->toBe('')
            ->and($subscription->logo)->toBe('')
        ;
    });
});

describe('update', function (): void {
    test('creates only update event when only general fields change', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $newCategory = new Category(name: 'Streaming');
        $subscription->update(
            category: $newCategory,
            name: 'Netflix Premium',
            lastPaidDate: new DateTimeImmutable('2024-02-01'),
            description: 'Premium plan',
            link: 'https://netflix.com',
            logo: 'netflix.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(1);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        expect($event->type)->toBe(SubscriptionEventType::Update)
            ->and($event->context)->toHaveKey('category')
            ->and($event->context)->toHaveKey('name')
            ->and($event->context)->not->toHaveKey('cost')
        ;
    });

    test('creates only cost change event when only cost fields change', function (): void {
        $lastPaidDate = new DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15000,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(1);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        expect($event->type)->toBe(SubscriptionEventType::CostChange)
            ->and($event->context)->toHaveKey('paymentPeriod')
            ->and($event->context)->toHaveKey('cost')
        ;
    });

    test('creates both events when both types of fields change', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix Premium',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15000,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(2);

        /** @var SubscriptionEvent $updateEvent */
        $updateEvent = $subscription->subscriptionEvents[0];
        /** @var SubscriptionEvent $costChangeEvent */
        $costChangeEvent = $subscription->subscriptionEvents[1];

        expect($updateEvent->type)->toBe(SubscriptionEventType::Update)
            ->and($costChangeEvent->type)->toBe(SubscriptionEventType::CostChange)
        ;
    });

    test('creates no events when no fields change', function (): void {
        $lastPaidDate = new DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(0);
    });
});

describe('record payment', function (): void {
    test('updates last paid date', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $newPaidDate = new DateTimeImmutable('2024-02-01');
        $subscription->recordPayment(
            paidDate: $newPaidDate,
            paymentType: PaymentType::Verified,
        );

        expect($subscription->lastPaidDate)->toBe($newPaidDate);
    });

    test('adds payment to collection', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        expect($subscription->payments)->toHaveCount(1);
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        expect($payment->type)->toBe(PaymentType::Verified)
            ->and($payment->amount)->toBe(1500)
        ;
    });

    test('uses subscription cost by default', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        expect($payment->amount)->toBe(1500);
    });

    test('accepts custom amount', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
            amount: 2000,
        );

        expect($subscription->payments)->toHaveCount(1);
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        expect($payment->amount)->toBe(2000);
    });
});

describe('archive', function (): void {
    test('sets archived to true', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();

        expect($subscription->archived)->toBeTrue();
    });

    test('creates archive event', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();

        expect($subscription->subscriptionEvents)->toHaveCount(1);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        expect($event->type)->toBe(SubscriptionEventType::Archive)
            ->and($event->context)->toBe([])
        ;
    });

    test('unarchive sets archived to false', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();
        $subscription->unarchive();

        expect($subscription->archived)->toBeFalse();
    });

    test('unarchive creates unarchive event', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();
        $subscription->unarchive();

        expect($subscription->subscriptionEvents)->toHaveCount(2);
        /** @var SubscriptionEvent $archiveEvent */
        $archiveEvent = $subscription->subscriptionEvents[0];
        /** @var SubscriptionEvent $unarchiveEvent */
        $unarchiveEvent = $subscription->subscriptionEvents[1];

        expect($archiveEvent->type)->toBe(SubscriptionEventType::Archive)
            ->and($unarchiveEvent->type)->toBe(SubscriptionEventType::Unarchive)
            ->and($unarchiveEvent->context)->toBe([])
        ;
    });
});

describe('validation', function (): void {
    test('rejects empty name', function (): void {
        new Subscription(
            category: $this->category,
            name: '',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects whitespace name', function (): void {
        new Subscription(
            category: $this->category,
            name: '   ',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects zero cost', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 0,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects negative cost', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: -100,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects zero period count', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 0,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects negative period count', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: -1,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);
});
