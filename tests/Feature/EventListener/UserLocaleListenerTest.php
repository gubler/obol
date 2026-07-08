<?php

// ABOUTME: Feature test for UserLocaleListener - money/number formatting follows the current user's locale.
// ABOUTME: Also covers inferring + persisting the locale from Accept-Language when it is still unresolved.

declare(strict_types=1);

namespace App\Tests\Feature\EventListener;

use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class UserLocaleListenerTest extends WebTestCase
{
    public function testAppliesTheUsersRegionalCatalogToTranslatedText(): void
    {
        // End-to-end: a British user's locale drives the translator through the listener + LocaleSwitcher,
        // so the en-GB catalog's "Colour" resolves on a rendered page (the en base reads "Color").
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['locale' => 'en-GB']));

        $client->request(method: Request::METHOD_GET, uri: '/app/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Colour', (string) $client->getResponse()->getContent());
    }

    public function testAppliesEachUsersLocaleToMoneyFormattingWithoutBleedingAcrossRequests(): void
    {
        $client = self::createClient();

        // A German user with a $1,500/mo subscription: money reads in de-DE (dot thousands, comma decimal).
        $german = UserFactory::createOne(['locale' => 'de-DE', 'displayCurrency' => Currency::USD]);
        SubscriptionFactory::createOne([
            'owner' => $german,
            'cost' => new Money(150000, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);

        $client->loginUser($german);
        $client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1.500,00', (string) $client->getResponse()->getContent());

        // A second, US-English user with an identical subscription reads in en-US - the German locale
        // from the previous request must not bleed across in the worker.
        $american = UserFactory::createOne(['locale' => 'en-US', 'displayCurrency' => Currency::USD]);
        SubscriptionFactory::createOne([
            'owner' => $american,
            'cost' => new Money(150000, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);

        $client->loginUser($american);
        $client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('1,500.00', $content);
        self::assertStringNotContainsString('1.500,00', $content);
    }

    public function testInfersAndPersistsTheLocaleFromTheBrowserWhenUnresolved(): void
    {
        $client = self::createClient();

        $user = UserFactory::new()->unresolvedLocale()->create(['displayCurrency' => Currency::USD]);
        SubscriptionFactory::createOne([
            'owner' => $user,
            'cost' => new Money(150000, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);

        $client->loginUser($user);
        $client->request(method: Request::METHOD_GET, uri: '/app', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1.500,00', (string) $client->getResponse()->getContent());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();
        $persisted = $entityManager->find(User::class, $user->id);
        self::assertInstanceOf(User::class, $persisted);
        self::assertSame('de-DE', $persisted->locale);

        // A later request with a different Accept-Language must not re-guess - the locale is resolved.
        $client->request(method: Request::METHOD_GET, uri: '/app', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR']);
        $entityManager->clear();
        $stillGerman = $entityManager->find(User::class, $user->id);
        self::assertInstanceOf(User::class, $stillGerman);
        self::assertSame('de-DE', $stillGerman->locale);
    }
}
