<?php

// ABOUTME: Integration test that Money round-trips through the database as a Doctrine embeddable.
// ABOUTME: Persists a non-USD cost and a payment amount, reloads, and asserts both come back as Money.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

test('a subscription cost and its payment amounts round-trip as Money', function (): void {
    $this->createClient();

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);

    $category = new Category(name: 'Entertainment');
    $subscription = new Subscription(
        category: $category,
        name: 'Manga Box',
        nextRenewal: new DateTimeImmutable('2024-01-01'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: new Money(2000, Currency::JPY),
    );
    // A recorded payment inherits the subscription's currency.
    $subscription->recordPayment(paidDate: new DateTimeImmutable('2024-01-01'), paymentType: PaymentType::Verified);

    $entityManager->persist($category);
    $entityManager->persist($subscription);
    $entityManager->flush();
    $subscriptionId = $subscription->id;
    $entityManager->clear();

    $reloaded = $entityManager->getRepository(Subscription::class)->find($subscriptionId);
    expect($reloaded)->not->toBeNull();
    expect($reloaded->cost)->toBeInstanceOf(Money::class)
        ->and($reloaded->cost->equals(new Money(2000, Currency::JPY)))->toBeTrue()
        ->and($reloaded->cost->format())->toBe('¥2,000')
    ;

    /** @var App\Entity\Payment $payment */
    $payment = $reloaded->payments->first();
    expect($payment->amount)->toBeInstanceOf(Money::class)
        ->and($payment->amount->equals(new Money(2000, Currency::JPY)))->toBeTrue()
    ;
});
