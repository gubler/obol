<?php

// ABOUTME: Feature test for a category whose subscriptions span currencies (regression for #150).
// ABOUTME: The category total used to 500 on Money::add; it now renders a converted headline with a disclosure.

declare(strict_types=1);

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

test('renders a mixed-currency category total converted to the display currency', function (): void {
    $client = $this->createClient();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
    // 1 EUR = 1.08 USD.
    $entityManager->persist(new ExchangeRate(Currency::EUR, 1.0, new DateTimeImmutable()));
    $entityManager->persist(new ExchangeRate(Currency::USD, 1.08, new DateTimeImmutable()));
    $entityManager->flush();

    $category = CategoryFactory::createOne(['name' => 'Mixed']);
    SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
    SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(3000, Currency::EUR), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

    $client->request(method: 'GET', uri: '/');

    $this->assertResponseIsSuccessful();
    // 4000 USD + (3000 EUR -> 3240 USD) = 7240 USD, flagged approximate, native split disclosed.
    $this->assertSelectorTextContains(selector: '.category-monthly-total', text: '~');
    $this->assertSelectorTextContains(selector: '.category-monthly-total', text: '$72.40');
    $this->assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '€30.00');
    $this->assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '$40.00');
});
