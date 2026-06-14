<?php

// ABOUTME: Unit tests for GenerateDuePaymentsHandler - records a Generated payment for each due subscription.
// ABOUTME: A subscription is due when nextRenewal <= today; generating advances the anchor by one interval.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Message\Command\Payment\GenerateDuePaymentsCommand;
use App\Message\Command\Payment\GenerateDuePaymentsHandler;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;

function makeDuePaymentSubscription(PaymentPeriod $period, int $count, DateTimeImmutable $nextRenewal): Subscription
{
    return new Subscription(
        category: new Category(name: 'Test Category'),
        name: 'Test',
        nextRenewal: $nextRenewal,
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: new Money(1599, Currency::USD),
    );
}

/**
 * @param list<Subscription> $subscriptions
 */
function runGenerateDuePayments(array $subscriptions): void
{
    $repository = test()->createMock(SubscriptionRepository::class);
    $repository->method('findBy')->with(['archived' => false])->willReturn($subscriptions);

    (new GenerateDuePaymentsHandler($repository))(new GenerateDuePaymentsCommand());
}

test('generates a payment dated to the renewal when it has passed', function (): void {
    $subscription = makeDuePaymentSubscription(PaymentPeriod::Month, 1, new DateTimeImmutable('2020-01-01'));

    runGenerateDuePayments([$subscription]);

    expect($subscription->payments)->toHaveCount(1);
    /** @var Payment $payment */
    $payment = $subscription->payments->first();
    expect($payment->type)->toBe(PaymentType::Generated)
        ->and($payment->amount->minorAmount)->toBe(1599)
        ->and($payment->paidDate)->toEqual(new DateTimeImmutable('2020-01-01'))
        ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2020-02-01'))
    ;
});

test('skips a subscription whose renewal is in the future', function (): void {
    $subscription = makeDuePaymentSubscription(PaymentPeriod::Month, 1, new DateTimeImmutable('+10 days'));

    runGenerateDuePayments([$subscription]);

    expect($subscription->payments)->toHaveCount(0);
});

test('skips a subscription set to manual payment generation even when its renewal has passed', function (): void {
    $subscription = makeDuePaymentSubscription(PaymentPeriod::Month, 1, new DateTimeImmutable('2020-01-01'));
    $subscription->switchToManualPayments();

    runGenerateDuePayments([$subscription]);

    expect($subscription->payments)->toHaveCount(0)
        ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2020-01-01'))
    ;
});

test('advances the renewal anchor by the configured interval', function (PaymentPeriod $period, int $count, string $expected): void {
    $subscription = makeDuePaymentSubscription($period, $count, new DateTimeImmutable('2020-01-01'));

    runGenerateDuePayments([$subscription]);

    expect($subscription->nextRenewal)->toEqual(new DateTimeImmutable($expected));
})->with([
    'weekly' => [PaymentPeriod::Week, 1, '2020-01-08'],
    'monthly' => [PaymentPeriod::Month, 1, '2020-02-01'],
    'yearly' => [PaymentPeriod::Year, 1, '2021-01-01'],
    'bi-weekly' => [PaymentPeriod::Week, 2, '2020-01-15'],
]);
