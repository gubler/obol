<?php

// ABOUTME: Unit tests for GeneratePaymentsHandler verifying renewal-anchored payment generation.
// ABOUTME: A subscription is due when nextRenewal <= today; generating advances the anchor by one interval.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Message\Scheduler\GeneratePaymentsHandler;
use App\Message\Scheduler\GeneratePaymentsMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

function makeSubscription(PaymentPeriod $period, int $count, DateTimeImmutable $nextRenewal): Subscription
{
    return new Subscription(
        category: new Category(name: 'Test Category'),
        name: 'Test',
        nextRenewal: $nextRenewal,
        paymentPeriod: $period,
        paymentPeriodCount: $count,
        cost: 1599,
    );
}

test('generates a payment dated to the renewal when it has passed', function (): void {
    $subscription = makeSubscription(PaymentPeriod::Month, 1, new DateTimeImmutable('2020-01-01'));

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')->with(['archived' => false])->willReturn([$subscription]);
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    (new GeneratePaymentsHandler($repository, $entityManager))(new GeneratePaymentsMessage());

    expect($subscription->payments)->toHaveCount(1);
    /** @var Payment $payment */
    $payment = $subscription->payments->first();
    expect($payment->type)->toBe(PaymentType::Generated)
        ->and($payment->amount)->toBe(1599)
        ->and($payment->paidDate)->toEqual(new DateTimeImmutable('2020-01-01'))
        ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2020-02-01'))
    ;
});

test('skips a subscription whose renewal is in the future', function (): void {
    $subscription = makeSubscription(PaymentPeriod::Month, 1, new DateTimeImmutable('+10 days'));

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')->with(['archived' => false])->willReturn([$subscription]);
    $entityManager = $this->createMock(EntityManagerInterface::class);

    (new GeneratePaymentsHandler($repository, $entityManager))(new GeneratePaymentsMessage());

    expect($subscription->payments)->toHaveCount(0);
});

test('advances the renewal anchor by the configured interval', function (PaymentPeriod $period, int $count, string $expected): void {
    $subscription = makeSubscription($period, $count, new DateTimeImmutable('2020-01-01'));

    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')->with(['archived' => false])->willReturn([$subscription]);
    $entityManager = $this->createMock(EntityManagerInterface::class);

    (new GeneratePaymentsHandler($repository, $entityManager))(new GeneratePaymentsMessage());

    expect($subscription->nextRenewal)->toEqual(new DateTimeImmutable($expected));
})->with([
    'weekly' => [PaymentPeriod::Week, 1, '2020-01-08'],
    'monthly' => [PaymentPeriod::Month, 1, '2020-02-01'],
    'yearly' => [PaymentPeriod::Year, 1, '2021-01-01'],
    'bi-weekly' => [PaymentPeriod::Week, 2, '2020-01-15'],
]);

test('flushes even when no subscriptions exist', function (): void {
    $repository = $this->createMock(SubscriptionRepository::class);
    $repository->method('findBy')->with(['archived' => false])->willReturn([]);
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects($this->once())->method('flush');

    (new GeneratePaymentsHandler($repository, $entityManager))(new GeneratePaymentsMessage());
});
