<?php

// ABOUTME: Feature tests for CreateSubscriptionController verifying subscription creation functionality.
// ABOUTME: Tests ensure proper form rendering, validation, and successful creation with redirects.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('get request displays create form', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/subscriptions/new');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'New Subscription');
    $this->assertSelectorExists(selector: 'form');
    $this->assertSelectorExists(selector: 'select[name="create_subscription[category]"]');
    $this->assertSelectorExists(selector: 'input[name="create_subscription[name]"]');
    $this->assertSelectorExists(selector: 'input[name="create_subscription[lastPaidDate]"]');
    $this->assertSelectorExists(selector: 'button[type="submit"]');
});

test('shows cancel link back to index', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/subscriptions/new');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/"]');
});

test('post request with valid data creates subscription', function (): void {
    $client = $this->createClient();
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

    $this->assertResponseRedirects(expectedLocation: '/');

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);

    $subscription = $repository->findOneBy(criteria: ['name' => 'Netflix Premium']);

    expect($subscription)->not->toBeNull();
    expect($subscription->name)->toBe('Netflix Premium');
    expect($subscription->cost)->toBe(1999);
    expect($subscription->description)->toBe('Streaming service');
    expect($subscription->link)->toBe('https://netflix.com');
});

test('post request with valid data shows success flash message', function (): void {
    $client = $this->createClient();
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

    $this->assertSelectorTextContains(selector: '.flash-success', text: 'Subscription created successfully');
});

test('post request with empty name shows validation error', function (): void {
    $client = $this->createClient();
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

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.text-red-700');
    $this->assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
});

test('post request without category shows validation error', function (): void {
    $client = $this->createClient();
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

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.text-red-700');
});

test('post request without last paid date shows validation error', function (): void {
    $client = $this->createClient();
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

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.text-red-700');
});

test('form includes csrf protection', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/subscriptions/new');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'input[name="create_subscription[_token]"]');
});

test('post request does not create subscription when validation fails', function (): void {
    $client = $this->createClient();
    CategoryFactory::createOne(['name' => 'Entertainment']);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');

    $initialCount = SubscriptionFactory::count();

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['create_subscription[name]'] = '';

    $client->submit(form: $form);

    $finalCount = SubscriptionFactory::count();

    expect($finalCount)->toBe($initialCount);
});
