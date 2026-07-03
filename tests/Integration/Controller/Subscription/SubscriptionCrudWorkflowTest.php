<?php

// ABOUTME: Integration tests for complete Subscription CRUD workflows end-to-end.
// ABOUTME: Tests verify create -> read -> update -> delete sequences with real data and no mocks.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Subscription;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\SubscriptionEventType;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

final class SubscriptionCrudWorkflowTest extends AuthenticatedTestCase
{
    public function testCompleteCreateReadUpdateDeleteWorkflow(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        // Create
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/new');
        $form = $crawler->selectButton(value: 'Save')->form([
            'create_subscription[category]' => $category->id->toBase32(),
            'create_subscription[name]' => 'Workflow Test Subscription',
            'create_subscription[nextRenewal]' => '2026-01-15',
            'create_subscription[paymentPeriod]' => 'month',
            'create_subscription[paymentPeriodCount]' => '1',
            'create_subscription[cost]' => '15.99',
            'create_subscription[description]' => 'Test description',
            'create_subscription[link]' => 'https://example.com',
        ]);
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/');
        $client->followRedirect();

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);

        $subscription = $repository->findOneBy(criteria: ['name' => 'Workflow Test Subscription']);
        self::assertNotNull($subscription);
        $subscriptionId = $subscription->id;

        // Read
        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/' . $subscriptionId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Workflow Test Subscription');

        // Update
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/' . $subscriptionId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[name]' => 'Updated Workflow Subscription',
            'edit_subscription[cost]' => '19.99',
        ]);
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscriptionId);
        $client->followRedirect();

        $entityManager->clear();
        $updatedSubscription = $repository->find($subscriptionId);
        self::assertNotNull($updatedSubscription);
        self::assertSame('Updated Workflow Subscription', $updatedSubscription->name);
        self::assertSame(1999, $updatedSubscription->cost->minorAmount);

        // Delete
        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/subscriptions/' . $subscriptionId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/');

        $entityManager->clear();
        $deletedSubscription = $repository->find($subscriptionId);
        self::assertNull($deletedSubscription);
    }

    public function testUpdateCreatesSubscriptionEvents(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $initialEventCount = $subscription->subscriptionEvents->count();

        // Update the subscription
        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/' . $subscription->id . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[name]' => 'Netflix Premium',
            'edit_subscription[cost]' => '19.99',
        ]);
        $client->submit(form: $form);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $subscription = $repository->find($subscription->id);
        self::assertNotNull($subscription);

        // Should have created at least 2 new events (Update + CostChange)
        self::assertGreaterThanOrEqual($initialEventCount + 2, $subscription->subscriptionEvents->count());

        // Verify event types
        $eventTypes = [];
        foreach ($subscription->subscriptionEvents as $event) {
            $eventTypes[] = $event->type;
        }

        self::assertContains(SubscriptionEventType::Update, $eventTypes);
        self::assertContains(SubscriptionEventType::CostChange, $eventTypes);
    }

    public function testCreateMultipleSubscriptionsAndVerifyListOrder(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);

        $subscriptions = ['Zebra Service', 'Alpha Service', 'Beta Service'];

        foreach ($subscriptions as $name) {
            $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/new');
            $form = $crawler->selectButton(value: 'Save')->form([
                'create_subscription[category]' => $category->id->toBase32(),
                'create_subscription[name]' => $name,
                'create_subscription[nextRenewal]' => '2026-01-01',
                'create_subscription[paymentPeriod]' => 'month',
                'create_subscription[paymentPeriodCount]' => '1',
                'create_subscription[cost]' => '9.99',
            ]);
            $client->submit(form: $form);
            $client->followRedirect();
        }

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/?view=list');

        $subscriptionNames = $crawler->filter('table tbody tr td:first-child')->each(
            fn (Crawler $node) => $node->text()
        );

        // Should be sorted alphabetically
        self::assertContains('Alpha Service', $subscriptionNames);
        self::assertContains('Beta Service', $subscriptionNames);
        self::assertContains('Zebra Service', $subscriptionNames);

        // Verify Alpha comes before Beta comes before Zebra
        $alphaIndex = array_search('Alpha Service', $subscriptionNames, true);
        $betaIndex = array_search('Beta Service', $subscriptionNames, true);
        $zebraIndex = array_search('Zebra Service', $subscriptionNames, true);

        self::assertLessThan($betaIndex, $alphaIndex);
        self::assertLessThan($zebraIndex, $betaIndex);
    }

    public function testChangingCategoryCreatesUpdateEvent(): void
    {
        $client = $this->authenticatedClient();
        $category1 = CategoryFactory::createOne(['name' => 'Entertainment']);
        $category2 = CategoryFactory::createOne(['name' => 'Utilities']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category1,
            'name' => 'Test Service',
        ]);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/' . $subscription->id . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[category]' => $category2->id->toBase32(),
        ]);
        $client->submit(form: $form);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $subscription = $repository->find($subscription->id);
        self::assertNotNull($subscription);

        // Should have an Update event
        $hasUpdateEvent = false;
        foreach ($subscription->subscriptionEvents as $event) {
            if (SubscriptionEventType::Update === $event->type) {
                $hasUpdateEvent = true;
                break;
            }
        }

        self::assertTrue($hasUpdateEvent, 'Expected Update event after category change');
    }
}
