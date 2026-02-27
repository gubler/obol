<?php

// ABOUTME: Feature tests for EditSubscriptionController verifying subscription editing functionality.
// ABOUTME: Tests ensure proper form rendering with existing data, validation, and successful updates.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EditSubscriptionControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysEditFormWithExistingData(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => 1599,
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Edit Subscription');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'input[name="edit_subscription[name]"][value="Netflix"]');
    }

    public function testGetRequestWithInvalidIdReturns404(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testPostRequestWithValidDataUpdatesSubscription(): void
    {
        $client = static::createClient();
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
            'edit_subscription[lastPaidDate]' => '2026-02-01',
            'edit_subscription[paymentPeriod]' => 'year',
            'edit_subscription[paymentPeriodCount]' => '1',
            'edit_subscription[cost]' => '1999',
            'edit_subscription[description]' => 'Updated description',
            'edit_subscription[link]' => 'https://netflix.com/premium',
        ]);

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        /** @var SubscriptionRepository $repository */
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $subscription = $repository->find($subscription->id);
        self::assertNotNull($subscription);

        self::assertSame('Netflix Premium', $subscription->name);
        self::assertSame(1999, $subscription->cost);
        self::assertSame('Updated description', $subscription->description);
        self::assertSame('https://netflix.com/premium', $subscription->link);
        self::assertTrue($newCategory->id->equals($subscription->category->id));
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();
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

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Subscription updated successfully');
    }

    public function testPostRequestWithEmptyNameShowsValidationError(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_subscription[name]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-red-700');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithInvalidIdReturns404(): void
    {
        $client = static::createClient();

        $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="edit_subscription[_token]"]');
    }

    public function testShowsCancelLinkBackToSubscription(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/subscriptions/' . $subscription->id . '"]');
    }

    public function testUpdatesCreateSubscriptionEvents(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => 1599,
        ]);

        $initialEventCount = \count($subscription->subscriptionEvents);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[name]' => 'Netflix Premium',
            'edit_subscription[cost]' => '1999',
        ]);

        $client->submit(form: $form);

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        /** @var SubscriptionRepository $repository */
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $subscription = $repository->find($subscription->id);
        self::assertNotNull($subscription);

        // Should have at least one new event (Update and/or CostChange)
        self::assertGreaterThan($initialEventCount, \count($subscription->subscriptionEvents));
    }
}
