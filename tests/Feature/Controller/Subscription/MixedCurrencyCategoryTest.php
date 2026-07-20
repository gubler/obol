<?php

// ABOUTME: Feature test for a category whose subscriptions span currencies (regression for #150).
// ABOUTME: The category total used to 500 on Money::add; it now renders a converted headline with a disclosure.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

final class MixedCurrencyCategoryTest extends AuthenticatedTestCase
{
    public function testRendersAMixedCurrencyCategoryTotalConvertedToTheDisplayCurrency(): void
    {
        $client = $this->authenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // 1 EUR = 1.08 USD.
        $entityManager->persist(new ExchangeRate(Currency::EUR, 1.0, CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable(), new \DateTimeZone('UTC'))));
        $entityManager->persist(new ExchangeRate(Currency::USD, 1.08, CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable(), new \DateTimeZone('UTC'))));
        $entityManager->flush();

        $category = CategoryFactory::createOne(['name' => 'Mixed']);
        SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(4000, Currency::USD), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);
        SubscriptionFactory::createOne(['category' => $category, 'cost' => new Money(3000, Currency::EUR), 'paymentPeriod' => PaymentPeriod::Month, 'paymentPeriodCount' => 1]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        // 4000 USD + (3000 EUR -> 3240 USD) = 7240 USD, flagged approximate, native split disclosed.
        self::assertSelectorTextContains(selector: '.category-monthly-total', text: '~');
        self::assertSelectorTextContains(selector: '.category-monthly-total', text: '$72.40');
        self::assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '€30.00');
        self::assertSelectorTextContains(selector: '.money-disclosure-breakdown', text: '$40.00');
    }
}
