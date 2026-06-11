<?php

// ABOUTME: Unit tests for Subscription entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify creation, update logic, payment recording, archival, and business invariants.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionEvent;
use App\Enum\PaymentGeneration;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
use App\Enum\TileColor;

beforeEach(function (): void {
    $this->category = new Category(name: 'Entertainment');
});

describe('creation', function (): void {
    test('creates subscription with valid data', function (): void {
        $nextRenewal = new DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->category)->toBe($this->category)
            ->and($subscription->name)->toBe('Netflix')
            ->and($subscription->nextRenewal)->toBe($nextRenewal)
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
            nextRenewal: new DateTimeImmutable(),
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
            nextRenewal: new DateTimeImmutable(),
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
            nextRenewal: new DateTimeImmutable(),
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
            nextRenewal: new DateTimeImmutable(),
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
            nextRenewal: new DateTimeImmutable(),
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
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $newCategory = new Category(name: 'Streaming');
        $subscription->update(
            category: $newCategory,
            name: 'Netflix Premium',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            description: 'Premium plan',
            link: 'https://netflix.com',
            logo: 'netflix.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: $subscription->color,
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
        $nextRenewal = new DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15000,
            color: $subscription->color,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(1);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        expect($event->type)->toBe(SubscriptionEventType::CostChange)
            ->and($event->context)->toHaveKey('paymentPeriod')
            ->and($event->context)->toHaveKey('cost')
        ;
    });

    test('records a period count change under the paymentPeriodCount key', function (): void {
        $nextRenewal = new DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 3,
            cost: 1500,
            color: $subscription->color,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(1);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        expect($event->type)->toBe(SubscriptionEventType::CostChange)
            ->and($event->context)->toHaveKey('paymentPeriodCount')
            ->and($event->context)->not->toHaveKey('paymentPeriodCost')
            ->and($event->context['paymentPeriodCount'])->toBe(['old' => 1, 'new' => 3])
        ;
    });

    test('creates both events when both types of fields change', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix Premium',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15000,
            color: $subscription->color,
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
        $nextRenewal = new DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: $subscription->color,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(0);
    });
});

describe('record payment', function (): void {
    test('advances next renewal by one interval from the anchor', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        // Paying late (on the 6th) must not move the anchor off the fixed cadence.
        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-02-06'),
            paymentType: PaymentType::Verified,
        );

        expect($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2024-03-01'));
    });

    test('rolling back a removed payment pulls the renewal anchor back', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
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
        $subscription->removePayment($payment);

        expect($subscription->payments)->toHaveCount(0)
            ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2024-02-01'))
        ;
    });

    test('adds payment to collection', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
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
            nextRenewal: new DateTimeImmutable('2024-01-01'),
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
            nextRenewal: new DateTimeImmutable('2024-01-01'),
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

describe('payment generation', function (): void {
    test('defaults to automated', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->paymentGeneration)->toBe(PaymentGeneration::Automated)
            ->and($subscription->generatesPaymentsAutomatically())->toBeTrue()
        ;
    });

    test('switching to manual sets payment generation to manual', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->switchToManualPayments();

        expect($subscription->paymentGeneration)->toBe(PaymentGeneration::Manual)
            ->and($subscription->generatesPaymentsAutomatically())->toBeFalse()
        ;
    });

    test('recording a payment under manual generation leaves the renewal anchor untouched', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
        $subscription->switchToManualPayments();

        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        expect($subscription->payments)->toHaveCount(1)
            ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2024-02-01'))
        ;
    });

    test('removing a payment under manual generation leaves the renewal anchor untouched', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
        // Record while automated so the anchor advances to 2024-03-01, then switch to manual.
        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );
        $subscription->switchToManualPayments();
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        $subscription->removePayment($payment);

        expect($subscription->payments)->toHaveCount(0)
            ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2024-03-01'))
        ;
    });

    test('removing the latest payment switches generation to manual and rolls back the anchor', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
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

        $subscription->removeLatestPayment($payment);

        expect($subscription->payments)->toHaveCount(0)
            ->and($subscription->paymentGeneration)->toBe(PaymentGeneration::Manual)
            ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2024-02-01'))
        ;
    });

    test('removing a payment that is not the latest is rejected', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-01-01'),
            paymentType: PaymentType::Verified,
        );
        $subscription->recordPayment(
            paidDate: new DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $older */
        $older = $subscription->payments->first();

        $subscription->removeLatestPayment($older);
    })->throws(Assert\InvalidArgumentException::class);

    test('automating sets generation to automated and anchors the future renewal', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
        $subscription->switchToManualPayments();

        $future = new DateTimeImmutable('tomorrow');
        $subscription->automatePayments($future);

        expect($subscription->paymentGeneration)->toBe(PaymentGeneration::Automated)
            ->and($subscription->generatesPaymentsAutomatically())->toBeTrue()
            ->and($subscription->nextRenewal)->toEqual($future)
        ;
    });

    test('automating with a non-future renewal is rejected', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
        $subscription->switchToManualPayments();

        $subscription->automatePayments(new DateTimeImmutable('2020-01-01'));
    })->throws(Assert\InvalidArgumentException::class);

    test('suggested resume renewal steps the cadence to the first date after today', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2020-01-15'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $suggested = $subscription->suggestedResumeRenewal();

        // Lands strictly after today and stays on the original day-of-cadence (the 15th).
        expect($suggested > new DateTimeImmutable('today'))->toBeTrue()
            ->and($suggested->format('d'))->toBe('15')
        ;
    });

    test('suggested resume renewal keeps a renewal that is already in the future', function (): void {
        $future = new DateTimeImmutable('+40 days');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $future,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->suggestedResumeRenewal())->toEqual($future);
    });
});

describe('archive', function (): void {
    test('sets archived to true', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
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
            nextRenewal: new DateTimeImmutable('2024-01-01'),
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
            nextRenewal: new DateTimeImmutable('2024-01-01'),
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
            nextRenewal: new DateTimeImmutable('2024-01-01'),
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
            nextRenewal: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects whitespace name', function (): void {
        new Subscription(
            category: $this->category,
            name: '   ',
            nextRenewal: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects zero cost', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 0,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects negative cost', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: -100,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects zero period count', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 0,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('rejects negative period count', function (): void {
        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: -1,
            cost: 1500,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('update rejects empty name', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: '',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: $subscription->color,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('update rejects whitespace name', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: '   ',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: $subscription->color,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('update rejects zero cost', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 0,
            color: $subscription->color,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('update rejects negative cost', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: -100,
            color: $subscription->color,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('update rejects zero period count', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 0,
            cost: 1500,
            color: $subscription->color,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('update rejects negative period count', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: -1,
            cost: 1500,
            color: $subscription->color,
        );
    })->throws(Assert\InvalidArgumentException::class);

    test('trims name on creation', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: '  Netflix  ',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->name)->toBe('Netflix');
    });

    test('update trims name', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: '  Netflix Premium  ',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: $subscription->color,
        );

        expect($subscription->name)->toBe('Netflix Premium');
    });
});

describe('color', function (): void {
    test('assigns a random palette color when none is given', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        expect($subscription->color)->toBeInstanceOf(TileColor::class);
    });

    test('accepts an explicit color', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: TileColor::Blue,
        );

        expect($subscription->color)->toBe(TileColor::Blue);
    });

    test('records a color change as an update event', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: TileColor::Blue,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: TileColor::Red,
        );

        expect($subscription->color)->toBe(TileColor::Red)
            ->and($subscription->subscriptionEvents)->toHaveCount(1)
        ;
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        expect($event->type)->toBe(SubscriptionEventType::Update)
            ->and($event->context)->toHaveKey('color')
            ->and($event->context['color'])->toBe(['old' => 'blue', 'new' => 'red'])
        ;
    });

    test('records no event when the color is unchanged', function (): void {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: TileColor::Blue,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            color: TileColor::Blue,
        );

        expect($subscription->subscriptionEvents)->toHaveCount(0);
    });
});

describe('monthlyCost', function (): void {
    $makeSubscription = function (PaymentPeriod $period, int $count, int $cost): Subscription {
        return new Subscription(
            category: new Category(name: 'Entertainment'),
            name: 'Example',
            nextRenewal: new DateTimeImmutable('2024-01-01'),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: $cost,
        );
    };

    test('returns the cost itself for a monthly subscription', function () use ($makeSubscription): void {
        expect($makeSubscription(PaymentPeriod::Month, 1, 1500)->monthlyCost())
            ->toBeInt()
            ->toBe(1500)
        ;
    });

    test('divides by the period count for a multi-month subscription', function () use ($makeSubscription): void {
        // 3000 every 3 months is 1000 per month.
        expect($makeSubscription(PaymentPeriod::Month, 3, 3000)->monthlyCost())->toBe(1000);
    });

    test('divides a yearly cost across twelve months', function () use ($makeSubscription): void {
        // 12000 per year is 1000 per month.
        expect($makeSubscription(PaymentPeriod::Year, 1, 12000)->monthlyCost())->toBe(1000);
    });

    test('normalizes a multi-year subscription', function () use ($makeSubscription): void {
        // 4800 every 2 years is 200 per month.
        expect($makeSubscription(PaymentPeriod::Year, 2, 4800)->monthlyCost())->toBe(200);
    });

    test('normalizes a weekly subscription using 52 weeks per year', function () use ($makeSubscription): void {
        // 1000 per week is 1000 * 52 / 12 = 4333.33 -> 4333 cents per month.
        expect($makeSubscription(PaymentPeriod::Week, 1, 1000)->monthlyCost())->toBe(4333);
    });

    test('rounds to the nearest whole cent', function () use ($makeSubscription): void {
        // 1000 per year is 83.33 -> 83 cents per month.
        expect($makeSubscription(PaymentPeriod::Year, 1, 1000)->monthlyCost())->toBe(83);
    });
});

describe('savingsTarget', function (): void {
    $makeSubscription = function (
        PaymentPeriod $period,
        int $count,
        int $cost,
        DateTimeImmutable $nextRenewal,
    ): Subscription {
        return new Subscription(
            category: new Category(name: 'Entertainment'),
            name: 'Example',
            nextRenewal: $nextRenewal,
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: $cost,
        );
    };

    test('ramps by one monthly cost per calendar month toward the renewal', function () use ($makeSubscription): void {
        // 1200 every 6 months -> 200/mo, due 2024-04-28. Funded by the 1st of March (a month ahead);
        // by 2024-01-15 four monthly allocations (Oct..Jan) have been made -> 800.
        $subscription = $makeSubscription(PaymentPeriod::Month, 6, 1200, new DateTimeImmutable('2024-04-28'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2024-01-15')))
            ->toBeInt()
            ->toBe(800)
        ;
    });

    test('holds the funded cost and the next cycle together in the unpaid due month', function () use ($makeSubscription): void {
        // In April the 1200 for the 2024-04-28 bill is funded and held (not yet paid), while 200
        // toward the October renewal has already begun -> 1400.
        $subscription = $makeSubscription(PaymentPeriod::Month, 6, 1200, new DateTimeImmutable('2024-04-28'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2024-04-15')))->toBe(1400);
    });

    test('drops to the next cycle once the renewal is recorded paid', function () use ($makeSubscription): void {
        // Recording the April payment advances nextRenewal to October; the held 1200 is released,
        // leaving the first 200 of the October cycle.
        $subscription = $makeSubscription(PaymentPeriod::Month, 6, 1200, new DateTimeImmutable('2024-10-28'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2024-04-28')))->toBe(200);
    });

    test('stacks this month and next for a monthly bill in its unpaid due month', function () use ($makeSubscription): void {
        // 100 monthly due the 15th: on the 8th the bill due on the 15th is held (100) and next
        // month's allocation has begun (100) -> 200.
        $subscription = $makeSubscription(PaymentPeriod::Month, 1, 100, new DateTimeImmutable('2024-04-15'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2024-04-08')))->toBe(200);
    });

    test('is one payment for a monthly bill the month before it is due', function () use ($makeSubscription): void {
        // 1500 monthly due 2024-02-01: in January only the funded February bill is held; saving for
        // the March bill has not begun -> 1500.
        $subscription = $makeSubscription(PaymentPeriod::Month, 1, 1500, new DateTimeImmutable('2024-02-01'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2024-01-15')))->toBe(1500);
    });

    test('treats a weekly bill as one payment in hand', function () use ($makeSubscription): void {
        // By-month proration cannot split a weekly cadence; until by-week proration lands a weekly
        // bill is just one payment held.
        $subscription = $makeSubscription(PaymentPeriod::Week, 1, 1000, new DateTimeImmutable('2024-01-08'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2024-01-05')))->toBe(1000);
    });

    test('is zero before the first cycle has begun', function () use ($makeSubscription): void {
        // A future renewal whose funding window has not opened yet has nothing to set aside.
        $subscription = $makeSubscription(PaymentPeriod::Year, 1, 12000, new DateTimeImmutable('2025-01-01'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2023-12-01')))->toBe(0);
    });

    test('holds an overdue renewal in full on top of saving for the next', function () use ($makeSubscription): void {
        // 12000 yearly due 2024-01-01, still unpaid by March: the full 12000 is held while three
        // monthly allocations toward the 2025 renewal have been made -> 15000.
        $subscription = $makeSubscription(PaymentPeriod::Year, 1, 12000, new DateTimeImmutable('2024-01-01'));

        expect($subscription->savingsTarget(new DateTimeImmutable('2024-03-01')))->toBe(15000);
    });
});
