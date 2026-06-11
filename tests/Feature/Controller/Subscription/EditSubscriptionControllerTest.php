<?php

// ABOUTME: Feature tests for EditSubscriptionController verifying subscription editing functionality.
// ABOUTME: Tests ensure proper form rendering with existing data, validation, and successful updates.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

test('get request displays edit form with existing data', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'Edit Subscription');
    $this->assertSelectorExists(selector: 'form');
    $this->assertSelectorExists(selector: 'input[name="edit_subscription[name]"][value="Netflix"]');
});

test('get request with invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'GET', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});

test('post request with valid data updates subscription', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $newCategory = CategoryFactory::createOne(['name' => 'Utilities']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
    ]);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[category]' => $newCategory->id->toBase32(),
        'edit_subscription[name]' => 'Netflix Premium',
        'edit_subscription[nextRenewal]' => '2026-02-01',
        'edit_subscription[paymentPeriod]' => 'year',
        'edit_subscription[paymentPeriodCount]' => '1',
        'edit_subscription[cost]' => '1999',
        'edit_subscription[description]' => 'Updated description',
        'edit_subscription[link]' => 'https://netflix.com/premium',
        'edit_subscription[color]' => 'teal',
    ]);

    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    /** @var SubscriptionRepository $repository */
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $subscription = $repository->find($subscription->id);
    expect($subscription)->not->toBeNull();

    expect($subscription->name)->toBe('Netflix Premium');
    expect($subscription->cost)->toBe(1999);
    expect($subscription->description)->toBe('Updated description');
    expect($subscription->link)->toBe('https://netflix.com/premium');
    expect($subscription->color)->toBe(TileColor::Teal);
    expect($newCategory->id->equals($subscription->category->id))->toBeTrue();
});

test('does not offer the restart control for an automated subscription', function (): void {
    $client = $this->createClient();
    $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorNotExists(selector: '#edit_subscription_restartPaymentGeneration');
});

test('resumes automated generation from the edit form when restart is requested', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new()->manual()->create([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
    ]);

    $future = (new DateTimeImmutable('+45 days'))->format('Y-m-d');

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $this->assertSelectorExists(selector: '#edit_subscription_restartPaymentGeneration');

    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[category]' => $category->id->toBase32(),
        'edit_subscription[name]' => 'Netflix',
        'edit_subscription[nextRenewal]' => $future,
        'edit_subscription[paymentPeriod]' => 'month',
        'edit_subscription[paymentPeriodCount]' => '1',
        'edit_subscription[cost]' => '1599',
        'edit_subscription[color]' => 'teal',
        'edit_subscription[restartPaymentGeneration]' => '1',
    ]);
    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $entityManager->clear();

    $updated = $entityManager->getRepository(className: Subscription::class)->find($subscription->id);
    expect($updated->generatesPaymentsAutomatically())->toBeTrue()
        ->and($updated->nextRenewal->format('Y-m-d'))->toBe($future)
    ;
});

test('rejects a restart with a non-future renewal date', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::new()->manual()->create([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
    ]);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[category]' => $category->id->toBase32(),
        'edit_subscription[name]' => 'Netflix',
        'edit_subscription[nextRenewal]' => '2020-01-01',
        'edit_subscription[paymentPeriod]' => 'month',
        'edit_subscription[paymentPeriodCount]' => '1',
        'edit_subscription[cost]' => '1599',
        'edit_subscription[color]' => 'teal',
        'edit_subscription[restartPaymentGeneration]' => '1',
    ]);
    $client->submit(form: $form);

    $this->assertResponseStatusCodeSame(expectedCode: 422);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $entityManager->clear();

    // Still manual - the invalid restart did not take effect.
    $updated = $entityManager->getRepository(className: Subscription::class)->find($subscription->id);
    expect($updated->generatesPaymentsAutomatically())->toBeFalse();
});

test('post request with valid data shows success flash message', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Spotify',
    ]);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[name]' => 'Spotify Premium',
    ]);

    $client->submit(form: $form);
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-success', text: 'Subscription updated successfully');
});

test('post request with empty name shows validation error', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['edit_subscription[name]'] = '';

    $client->submit(form: $form);

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.text-red-700');
    $this->assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
});

test('post request with invalid id returns 404', function (): void {
    $client = $this->createClient();

    $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});

test('form includes csrf protection', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'input[name="edit_subscription[_token]"]');
});

test('shows cancel link back to subscription', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
    ]);

    $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/subscriptions/' . $subscription->id . '"]');
});

test('updates create subscription events', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
    ]);

    $initialEventCount = count($subscription->subscriptionEvents);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[name]' => 'Netflix Premium',
        'edit_subscription[cost]' => '1999',
    ]);

    $client->submit(form: $form);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    /** @var SubscriptionRepository $repository */
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $subscription = $repository->find($subscription->id);
    expect($subscription)->not->toBeNull();

    // Should have at least one new event (Update and/or CostChange)
    expect(count($subscription->subscriptionEvents))->toBeGreaterThan($initialEventCount);
});
