<?php

// ABOUTME: Integration tests for complete Subscription CRUD workflows end-to-end.
// ABOUTME: Tests verify create → read → update → delete sequences with real data and no mocks.

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Subscription;

use App\Entity\Subscription;
use App\Enum\SubscriptionEventType;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SubscriptionCrudWorkflowTest extends WebTestCase
{
    public function testCompleteCreateReadUpdateDeleteWorkflow(): void
    {
        $client = static::createClient();
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

        self::assertResponseRedirects(expectedLocation: '/');
        $client->followRedirect();

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);

        $subscription = $repository->findOneBy(criteria: ['name' => 'Workflow Test Subscription']);
        self::assertNotNull($subscription);
        $subscriptionId = $subscription->id;

        // Read
        $client->request(method: 'GET', uri: '/subscriptions/' . $subscriptionId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Workflow Test Subscription');

        // Update
        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscriptionId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[name]' => 'Updated Workflow Subscription',
            'edit_subscription[cost]' => '1999',
        ]);
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscriptionId);
        $client->followRedirect();

        $entityManager->clear();
        $updatedSubscription = $repository->find($subscriptionId);
        self::assertNotNull($updatedSubscription);
        self::assertSame('Updated Workflow Subscription', $updatedSubscription->name);
        self::assertSame(1999, $updatedSubscription->cost);

        // Delete
        $client->request(method: 'POST', uri: '/subscriptions/' . $subscriptionId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/');

        $entityManager->clear();
        $deletedSubscription = $repository->find($subscriptionId);
        self::assertNull($deletedSubscription);
    }

    public function testUpdateCreatesSubscriptionEvents(): void
    {
        $client = static::createClient();
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

        $container = static::getContainer();
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
        $client = static::createClient();
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
            function (\Symfony\Component\DomCrawler\Crawler $node) {
                return $node->text();
            }
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
        $client = static::createClient();
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

        $container = static::getContainer();
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
