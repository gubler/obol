<?php

// ABOUTME: Feature tests for ArchiveSubscriptionController verifying subscription archiving.
// ABOUTME: Tests ensure proper archiving, 404 handling, POST-only access, and flash messages.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArchiveSubscriptionControllerTest extends WebTestCase
{
    public function testArchiveRequestArchivesSubscription(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/archive');

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $archivedSubscription = $repository->find($subscription->id);
        self::assertNotNull($archivedSubscription);
        self::assertTrue($archivedSubscription->archived);
    }

    public function testArchiveRequestShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
        ]);

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/archive');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Subscription archived successfully');
    }

    public function testArchiveRequestWithInvalidIdReturns404(): void
    {
        $client = static::createClient();

        $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/archive');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/archive');

        self::assertResponseStatusCodeSame(405);
    }

    public function testArchiveCreatesSubscriptionEvent(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $initialEventCount = \count($subscription->subscriptionEvents);

        $client->request(method: 'POST', uri: '/subscriptions/' . $subscription->id . '/archive');

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $archivedSubscription = $repository->find($subscription->id);
        self::assertNotNull($archivedSubscription);
        self::assertGreaterThan($initialEventCount, \count($archivedSubscription->subscriptionEvents));
    }
}
