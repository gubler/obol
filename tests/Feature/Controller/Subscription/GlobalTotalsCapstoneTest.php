<?php

// ABOUTME: Feature tests for the homepage Global Totals capstone (total obligation, week/month/year).
// ABOUTME: Covers single-currency exact figures and multi-currency conversion with a native disclosure.

declare(strict_types=1);

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

test('shows the global total obligation across week, month, and year', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(1500, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
    SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(1000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: '.global-total-monthly', text: '$25.00');
    $this->assertSelectorTextContains(selector: '.global-total-weekly', text: '$5.77');   // round(2500 * 12/52)
    $this->assertSelectorTextContains(selector: '.global-total-yearly', text: '$300.00');
});

test('converts a multi-currency total to the display currency with a native breakdown', function (): void {
    $client = $this->createClient();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
    // 1 EUR = 1.08 USD.
    $entityManager->persist(new ExchangeRate(Currency::EUR, 1.0, new DateTimeImmutable()));
    $entityManager->persist(new ExchangeRate(Currency::USD, 1.08, new DateTimeImmutable()));
    $entityManager->flush();

    // Separate categories so each category total stays single-currency; the capstone sums across both.
    $usd = CategoryFactory::createOne(['name' => 'Streaming']);
    $eur = CategoryFactory::createOne(['name' => 'Cloud']);
    SubscriptionFactory::createOne(['category' => $usd, 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
    SubscriptionFactory::createOne(['category' => $eur, 'cost' => new Money(3000, Currency::EUR), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    // 4000 USD + (3000 EUR -> 3240 USD) = 7240 USD, flagged approximate.
    $this->assertSelectorTextContains(selector: '.global-total-monthly', text: '~');
    $this->assertSelectorTextContains(selector: '.global-total-monthly', text: '$72.40');
    // The disclosure carries the native per-currency split.
    $this->assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '€30.00');
    $this->assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '$40.00');
});
