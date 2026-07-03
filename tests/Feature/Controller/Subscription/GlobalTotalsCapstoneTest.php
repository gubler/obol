<?php

// ABOUTME: Feature tests for the homepage Global Totals capstone (total obligation, week/month/year).
// ABOUTME: Covers single-currency exact figures and multi-currency conversion with a native disclosure.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

final class GlobalTotalsCapstoneTest extends AuthenticatedTestCase
{
    public function testShowsTheGlobalTotalObligationAcrossWeekMonthAndYear(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(1500, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(1000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.global-total-monthly', text: '$25.00');
        self::assertSelectorTextContains(selector: '.global-total-weekly', text: '$5.77');   // round(2500 * 12/52)
        self::assertSelectorTextContains(selector: '.global-total-yearly', text: '$300.00');
    }

    public function testConvertsAMultiCurrencyTotalToTheDisplayCurrencyWithANativeBreakdown(): void
    {
        $client = $this->authenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // 1 EUR = 1.08 USD.
        $entityManager->persist(new ExchangeRate(Currency::EUR, 1.0, new \DateTimeImmutable()));
        $entityManager->persist(new ExchangeRate(Currency::USD, 1.08, new \DateTimeImmutable()));
        $entityManager->flush();

        // Separate categories so each category total stays single-currency; the capstone sums across both.
        $usd = CategoryFactory::createOne(['name' => 'Streaming']);
        $eur = CategoryFactory::createOne(['name' => 'Cloud']);
        SubscriptionFactory::createOne(['category' => $usd, 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::createOne(['category' => $eur, 'cost' => new Money(3000, Currency::EUR), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        // 4000 USD + (3000 EUR -> 3240 USD) = 7240 USD, flagged approximate.
        self::assertSelectorTextContains(selector: '.global-total-monthly', text: '~');
        self::assertSelectorTextContains(selector: '.global-total-monthly', text: '$72.40');
        // The disclosure carries the native per-currency split.
        self::assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '€30.00');
        self::assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '$40.00');
    }
}
