<?php

// ABOUTME: Feature tests for ShowSubscriptionController verifying subscription detail display.
// ABOUTME: Tests ensure proper rendering of subscription details, and 404 handling.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ShowSubscriptionControllerTest extends WebTestCase
{
    public function testShowsSubscriptionBasicResponse(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix Premium',
            'cost' => 1999,
            'description' => 'Streaming service',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
    }

    public function testShowsEditLink(): void
    {
        $client = static::createClient();
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
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'form[action="/subscriptions/' . $subscription->id . '/delete"]');
    }

    public function testShowsBackToListLink(): void
    {
        $client = static::createClient();
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
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testRendersWithoutErrors(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => 1599,
            'description' => 'Test description',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        // Template should render without errors
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        self::assertStringContainsString('Netflix', $content);
    }
}
