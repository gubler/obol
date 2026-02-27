<?php

// ABOUTME: Feature tests for UnarchiveSubscriptionController verifying subscription unarchiving.
// ABOUTME: Tests ensure proper unarchiving, 404 handling, POST-only access, and flash messages.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UnarchiveSubscriptionControllerTest extends WebTestCase
{
    public function testUnarchiveRequestUnarchivesSubscription(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Netflix',
        ])->archived()->create();

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/unarchive');

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $unarchivedSubscription = $repository->find($subscription->id);
        self::assertNotNull($unarchivedSubscription);
        self::assertFalse($unarchivedSubscription->archived);
    }

    public function testUnarchiveRequestShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Spotify',
        ])->archived()->create();

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/unarchive');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Subscription unarchived successfully');
    }

    public function testUnarchiveRequestWithInvalidIdReturns404(): void
    {
        $client = static::createClient();

        $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/unarchive');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Netflix',
        ])->archived()->create();

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/unarchive');

        self::assertResponseStatusCodeSame(405);
    }

    public function testUnarchiveCreatesSubscriptionEvent(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Netflix',
        ])->archived()->create();

        $initialEventCount = \count($subscription->subscriptionEvents);

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/unarchive');

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $unarchivedSubscription = $repository->find($subscription->id);
        self::assertNotNull($unarchivedSubscription);
        self::assertGreaterThan($initialEventCount, \count($unarchivedSubscription->subscriptionEvents));
    }
}
