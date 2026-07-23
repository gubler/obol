<?php

// ABOUTME: Integration test that Money round-trips through the database as a Doctrine embeddable.
// ABOUTME: Persists a non-USD cost and a payment amount, reloads, and asserts both come back as Money.

declare(strict_types=1);

namespace App\Tests\Integration\ValueObject;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MoneyPersistenceTest extends WebTestCase
{
    public function testASubscriptionCostAndItsPaymentAmountsRoundTripAsMoney(): void
    {
        self::createClient();

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);

        $owner = new User(email: 'owner@example.com');
        $category = new Category(owner: $owner, name: 'Entertainment');
        $subscription = new Subscription(
            owner: $owner,
            category: $category,
            name: 'Manga Box',
            nextRenewal: CalendarDate::fromString('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(2000, Currency::JPY),
            now: new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')),
        );
        // A recorded payment inherits the subscription's currency.
        $subscription->recordPayment(paidDate: CalendarDate::fromString('2024-01-01'), paymentType: PaymentType::Verified);

        $entityManager->persist($owner);
        $entityManager->persist($category);
        $entityManager->persist($subscription);
        $entityManager->flush();

        $subscriptionId = $subscription->id;
        $entityManager->clear();

        $reloaded = $entityManager->getRepository(Subscription::class)->find($subscriptionId);
        self::assertNotNull($reloaded);
        self::assertInstanceOf(Money::class, $reloaded->cost);
        self::assertTrue($reloaded->cost->equals(new Money(2000, Currency::JPY)));

        /** @var Payment $payment */
        $payment = $reloaded->payments->first();
        self::assertInstanceOf(Money::class, $payment->amount);
        self::assertTrue($payment->amount->equals(new Money(2000, Currency::JPY)));
    }
}
