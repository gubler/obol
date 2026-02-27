<?php

// ABOUTME: Feature tests for DeleteSubscriptionController verifying subscription deletion functionality.
// ABOUTME: Tests ensure proper deletion, 404 handling, and flash messages.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DeleteSubscriptionControllerTest extends WebTestCase
{
    public function testDeleteRequestWithValidIdDeletesSubscription(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $subscriptionId = $subscription->id;

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscriptionId . '/delete');

        self::assertResponseRedirects(expectedLocation: '/');

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);

        $entityManager->clear();
        $deletedSubscription = $repository->find($subscriptionId);

        self::assertNull($deletedSubscription);
    }

    public function testDeleteRequestWithValidIdShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
        ]);

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Subscription deleted successfully');
    }

    public function testDeleteRequestWithInvalidIdReturns404(): void
    {
        $client = static::createClient();

        $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/delete');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/delete');

        self::assertResponseStatusCodeSame(expectedCode: 405);
    }

    public function testDeleteReducesSubscriptionCount(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $initialCount = SubscriptionFactory::count();

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/delete');

        $finalCount = SubscriptionFactory::count();

        self::assertSame($initialCount - 1, $finalCount);
    }
}
