<?php

// ABOUTME: Integration tests for complete Subscription CRUD workflows end-to-end.
// ABOUTME: Tests verify create -> read -> update -> delete sequences with real data and no mocks.

declare(strict_types=1);

use App\Entity\Subscription;
use App\Enum\SubscriptionEventType;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;

test('complete create read update delete workflow', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    // Create
    $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');
    $form = $crawler->selectButton(value: 'Save')->form([
        'create_subscription[category]' => $category->id->toBase32(),
        'create_subscription[name]' => 'Workflow Test Subscription',
        'create_subscription[lastPaidDate]' => '2026-01-15',
        'create_subscription[paymentPeriod]' => 'month',
        'create_subscription[paymentPeriodCount]' => '1',
        'create_subscription[cost]' => '1599',
        'create_subscription[description]' => 'Test description',
        'create_subscription[link]' => 'https://example.com',
    ]);
    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/');
    $client->followRedirect();

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);

    $subscription = $repository->findOneBy(criteria: ['name' => 'Workflow Test Subscription']);
    expect($subscription)->not->toBeNull();
    $subscriptionId = $subscription->id;

    // Read
    $client->request(method: 'GET', uri: '/subscriptions/' . $subscriptionId);
    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'Workflow Test Subscription');

    // Update
    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscriptionId . '/edit');
    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[name]' => 'Updated Workflow Subscription',
        'edit_subscription[cost]' => '1999',
    ]);
    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscriptionId);
    $client->followRedirect();

    $entityManager->clear();
    $updatedSubscription = $repository->find($subscriptionId);
    expect($updatedSubscription)->not->toBeNull();
    expect($updatedSubscription->name)->toBe('Updated Workflow Subscription');
    expect($updatedSubscription->cost)->toBe(1999);

    // Delete
    $client->request(method: 'POST', uri: '/subscriptions/' . $subscriptionId . '/delete');

    $this->assertResponseRedirects(expectedLocation: '/');

    $entityManager->clear();
    $deletedSubscription = $repository->find($subscriptionId);
    expect($deletedSubscription)->toBeNull();
});

test('update creates subscription events', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category,
        'name' => 'Netflix',
        'cost' => 1599,
    ]);

    $initialEventCount = $subscription->subscriptionEvents->count();

    // Update the subscription
    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');
    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[name]' => 'Netflix Premium',
        'edit_subscription[cost]' => '1999',
    ]);
    $client->submit(form: $form);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $subscription = $repository->find($subscription->id);
    expect($subscription)->not->toBeNull();

    // Should have created at least 2 new events (Update + CostChange)
    expect($subscription->subscriptionEvents->count())->toBeGreaterThanOrEqual($initialEventCount + 2);

    // Verify event types
    $eventTypes = [];
    foreach ($subscription->subscriptionEvents as $event) {
        $eventTypes[] = $event->type;
    }

    expect($eventTypes)->toContain(SubscriptionEventType::Update);
    expect($eventTypes)->toContain(SubscriptionEventType::CostChange);
});

test('create multiple subscriptions and verify list order', function (): void {
    $client = $this->createClient();
    $category = CategoryFactory::createOne(['name' => 'Entertainment']);

    $subscriptions = ['Zebra Service', 'Alpha Service', 'Beta Service'];

    foreach ($subscriptions as $name) {
        $crawler = $client->request(method: 'GET', uri: '/subscriptions/new');
        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => $name,
            'create_subscription[lastPaidDate]' => '2026-01-01',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '999',
        ]);
        $client->submit(form: $form);
        $client->followRedirect();
    }

    $crawler = $client->request(method: 'GET', uri: '/');

    $subscriptionNames = $crawler->filter('table tbody tr td:first-child')->each(
        function (Symfony\Component\DomCrawler\Crawler $node) {
            return $node->text();
        }
    );

    // Should be sorted alphabetically
    expect($subscriptionNames)->toContain('Alpha Service');
    expect($subscriptionNames)->toContain('Beta Service');
    expect($subscriptionNames)->toContain('Zebra Service');

    // Verify Alpha comes before Beta comes before Zebra
    $alphaIndex = array_search('Alpha Service', $subscriptionNames, true);
    $betaIndex = array_search('Beta Service', $subscriptionNames, true);
    $zebraIndex = array_search('Zebra Service', $subscriptionNames, true);

    expect($alphaIndex)->toBeLessThan($betaIndex);
    expect($betaIndex)->toBeLessThan($zebraIndex);
});

test('changing category creates update event', function (): void {
    $client = $this->createClient();
    $category1 = CategoryFactory::createOne(['name' => 'Entertainment']);
    $category2 = CategoryFactory::createOne(['name' => 'Utilities']);
    $subscription = SubscriptionFactory::createOne([
        'category' => $category1,
        'name' => 'Test Service',
    ]);

    $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');
    $form = $crawler->selectButton(value: 'Save')->form([
        'edit_subscription[category]' => $category2->id->toBase32(),
    ]);
    $client->submit(form: $form);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Subscription::class);
    $entityManager->clear();

    $subscription = $repository->find($subscription->id);
    expect($subscription)->not->toBeNull();

    // Should have an Update event
    $hasUpdateEvent = false;
    foreach ($subscription->subscriptionEvents as $event) {
        if (SubscriptionEventType::Update === $event->type) {
            $hasUpdateEvent = true;
            break;
        }
    }

    expect($hasUpdateEvent)->toBeTrue('Expected Update event after category change');
});
