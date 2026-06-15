<?php

// ABOUTME: Feature tests for CreateSubscriptionController verifying subscription creation functionality.
// ABOUTME: Tests ensure proper form rendering, validation, and successful creation with redirects.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreateSubscriptionControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysCreateForm(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'New Subscription');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'select[name="create_subscription[category]"]');
        self::assertSelectorExists(selector: 'input[name="create_subscription[name]"]');
        self::assertSelectorExists(selector: 'input[name="create_subscription[nextRenewal]"]');
        self::assertSelectorExists(selector: 'button[type="submit"]');
    }

    public function testShowsCancelLinkBackToIndex(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/"]');
    }

    public function testPostRequestWithValidDataCreatesSubscription(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Netflix Premium',
            'create_subscription[nextRenewal]' => '2026-01-15',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '1999',
            'create_subscription[description]' => 'Streaming service',
            'create_subscription[link]' => 'https://netflix.com',
            'create_subscription[color]' => 'blue',
        ]);

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/');

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);

        $subscription = $repository->findOneBy(criteria: ['name' => 'Netflix Premium']);

        self::assertNotNull($subscription);
        self::assertSame('Netflix Premium', $subscription->name);
        self::assertSame(1999, $subscription->cost->minorAmount);
        self::assertSame('Streaming service', $subscription->description);
        self::assertSame('https://netflix.com', $subscription->link);
        self::assertSame(TileColor::Blue, $subscription->color);
    }

    public function testPostRequestCreatesASubscriptionInAChosenNonDefaultCurrency(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Manga Box',
            'create_subscription[nextRenewal]' => '2026-01-15',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '1500',
            'create_subscription[currency]' => 'EUR',
        ]);

        $client->submit(form: $form);
        self::assertResponseRedirects(expectedLocation: '/');

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $subscription = $entityManager->getRepository(Subscription::class)->findOneBy(['name' => 'Manga Box']);

        self::assertNotNull($subscription);
        self::assertSame(Currency::EUR, $subscription->cost->currency);
        self::assertSame(1500, $subscription->cost->minorAmount);

        // The chosen currency drives rendering on the detail page.
        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);
        self::assertSelectorTextContains(selector: 'body', text: '€15.00');
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Spotify',
            'create_subscription[nextRenewal]' => '2026-01-01',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);

        $client->submit(form: $form);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Subscription created successfully');
    }

    public function testPostRequestWithEmptyNameShowsValidationError(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => '',
            'create_subscription[nextRenewal]' => '2026-01-01',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-danger');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithoutCategoryShowsValidationError(): void
    {
        $client = self::createClient();
        CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[name]' => 'Test Sub',
            'create_subscription[nextRenewal]' => '2026-01-01',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);
        $form['create_subscription[category]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-danger');
    }

    public function testPostRequestWithoutNextRenewalDateShowsValidationError(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Test Sub',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);
        $form['create_subscription[nextRenewal]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-danger');
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="create_subscription[_token]"]');
    }

    public function testPostRequestDoesNotCreateSubscriptionWhenValidationFails(): void
    {
        $client = self::createClient();
        CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $initialCount = SubscriptionFactory::count();

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['create_subscription[name]'] = '';

        $client->submit(form: $form);

        $finalCount = SubscriptionFactory::count();

        self::assertSame($initialCount, $finalCount);
    }
}
