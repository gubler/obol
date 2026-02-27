<?php

// ABOUTME: Feature tests for CreateSubscriptionController verifying subscription creation functionality.
// ABOUTME: Tests ensure proper form rendering, validation, and successful creation with redirects.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreateSubscriptionControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysCreateForm(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'New Subscription');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'select[name="create_subscription[category]"]');
        self::assertSelectorExists(selector: 'input[name="create_subscription[name]"]');
        self::assertSelectorExists(selector: 'input[name="create_subscription[lastPaidDate]"]');
        self::assertSelectorExists(selector: 'button[type="submit"]');
    }

    public function testShowsCancelLinkBackToIndex(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/"]');
    }

    public function testPostRequestWithValidDataCreatesSubscription(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Netflix Premium',
            'create_subscription[lastPaidDate]' => '2026-01-15',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '1999',
            'create_subscription[description]' => 'Streaming service',
            'create_subscription[link]' => 'https://netflix.com',
        ]);

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/');

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);

        $subscription = $repository->findOneBy(criteria: ['name' => 'Netflix Premium']);

        self::assertNotNull($subscription);
        self::assertSame('Netflix Premium', $subscription->name);
        self::assertSame(1999, $subscription->cost);
        self::assertSame('Streaming service', $subscription->description);
        self::assertSame('https://netflix.com', $subscription->link);
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Spotify',
            'create_subscription[lastPaidDate]' => '2026-01-01',
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
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => '',
            'create_subscription[lastPaidDate]' => '2026-01-01',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-red-700');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithoutCategoryShowsValidationError(): void
    {
        $client = static::createClient();
        CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[name]' => 'Test Sub',
            'create_subscription[lastPaidDate]' => '2026-01-01',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);
        $form['create_subscription[category]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-red-700');
    }

    public function testPostRequestWithoutLastPaidDateShowsValidationError(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Test Sub',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);
        $form['create_subscription[lastPaidDate]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-red-700');
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="create_subscription[_token]"]');
    }

    public function testPostRequestDoesNotCreateSubscriptionWhenValidationFails(): void
    {
        $client = static::createClient();
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
