<?php

// ABOUTME: Feature tests for ShowSubscriptionController verifying subscription detail display.
// ABOUTME: Tests ensure proper rendering of subscription details, and 404 handling.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShowSubscriptionControllerTest extends WebTestCase
{
    public function testShowsSubscriptionBasicResponse(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix Premium',
            'cost' => new Money(1999, Currency::USD),
            'description' => 'Streaming service',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
    }

    public function testShowsEditLink(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/subscriptions/' . $subscription->id . '/edit"]');
    }

    public function testShowsDeleteButton(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'form[action="/subscriptions/' . $subscription->id . '/delete"]');
    }

    public function testPaymentDeleteConfirmationStatesTheRollbackAndManualSwitch(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::createOne([
            'name' => 'Netflix',
            'nextRenewal' => new \DateTimeImmutable('2024-03-01'),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);
        // A generated payment that advanced the anchor: deleting it both rolls back and goes manual.
        PaymentFactory::createOne([
            'subscription' => $subscription,
            'type' => PaymentType::Generated,
            'paidDate' => new \DateTimeImmutable('2024-02-15'),
            'advancedRenewal' => true,
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        $confirm = $crawler->filter('.payment-delete-form')->attr('data-turbo-confirm');
        self::assertStringContainsString('rolls the next renewal back from 2024-03-01 to 2024-02-01', (string) $confirm);
        self::assertStringContainsString('switches this subscription to manual payments', (string) $confirm);
    }

    public function testPaymentDeleteConfirmationIsPlainWhenRemovalHasNoConsequence(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::createOne([
            'name' => 'Netflix',
            'nextRenewal' => new \DateTimeImmutable('2024-03-01'),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
        ]);
        // A backfilled verified payment that did not advance the anchor: deletion changes nothing.
        PaymentFactory::createOne([
            'subscription' => $subscription,
            'type' => PaymentType::Verified,
            'paidDate' => new \DateTimeImmutable('2023-11-01'),
            'advancedRenewal' => false,
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        $confirm = $crawler->filter('.payment-delete-form')->attr('data-turbo-confirm');
        self::assertSame('Delete this payment?', (string) $confirm);
    }

    public function testShowsBackToListLink(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/"]');
    }

    public function testInvalidIdReturns404(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testRendersWithoutErrors(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
            'description' => 'Test description',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        // Template should render without errors
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        self::assertStringContainsString('Netflix', $content);
    }

    public function testShowsAManualPaymentsBadgeForAManualSubscription(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::new()->manual()->create(['name' => 'Netflix']);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '.manual-payments-badge');
    }

    public function testShowsNoManualPaymentsBadgeForAnAutomatedSubscription(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(selector: '.manual-payments-badge');
    }

    public function testOffersTheDeleteActionOnlyOnTheLatestPayment(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);
        PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => new \DateTimeImmutable('2024-01-01')]);
        PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => new \DateTimeImmutable('2024-02-01')]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        // Two payments listed, but only the latest one is deletable.
        self::assertSame(1, $crawler->filter('.payment-delete-form')->count());
    }
}
