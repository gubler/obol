<?php

// ABOUTME: Feature test proving reports present converted totals in the current user's own displayCurrency.
// ABOUTME: The same USD subscription reads as EUR for a EUR-display user and as USD for a USD-display user.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Report;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class PerUserDisplayCurrencyTest extends WebTestCase
{
    public function testEachUserSeesConvertedTotalsInTheirOwnDisplayCurrency(): void
    {
        // createClient() must be the first kernel boot; the factories reuse it afterwards.
        $client = self::createClient();

        // EUR-pivot rates: 1 EUR = 1 EUR, 1 EUR = 1.08 USD. A $108.00 sub converts to exactly EUR 100.00.
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new ExchangeRate(Currency::EUR, 1.0, CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('today'), new \DateTimeZone('UTC'))));
        $entityManager->persist(new ExchangeRate(Currency::USD, 1.08, CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('today'), new \DateTimeZone('UTC'))));
        $entityManager->flush();

        // A EUR-display user with a single USD-priced subscription: the report headline reads in EUR.
        $userEur = UserFactory::createOne(['displayCurrency' => Currency::EUR]);
        SubscriptionFactory::createOne([
            'owner' => $userEur,
            'cost' => new Money(10800, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);

        $client->loginUser($userEur);
        $client->request(method: Request::METHOD_GET, uri: '/app/reports');
        self::assertResponseIsSuccessful();
        // The USD subscription is presented converted into the user's EUR - the euro glyph proves it.
        self::assertStringContainsString('€', (string) $client->getResponse()->getContent());

        // A second user with an identical USD subscription but a USD display never sees EUR.
        $userUsd = UserFactory::createOne(['displayCurrency' => Currency::USD]);
        SubscriptionFactory::createOne([
            'owner' => $userUsd,
            'cost' => new Money(10800, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);

        $client->loginUser($userUsd);
        $client->request(method: Request::METHOD_GET, uri: '/app/reports');
        self::assertResponseIsSuccessful();
        $usdContent = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('$', $usdContent);
        self::assertStringNotContainsString('€', $usdContent);
    }
}
