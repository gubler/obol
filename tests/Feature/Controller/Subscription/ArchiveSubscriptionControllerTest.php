<?php

// ABOUTME: Feature tests for ArchiveSubscriptionController verifying subscription archiving.
// ABOUTME: Tests ensure proper archiving, 404 handling, POST-only access, and flash messages.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ArchiveSubscriptionControllerTest extends AuthenticatedTestCase
{
    public function testArchiveRequestArchivesSubscription(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/subscriptions/' . $subscription->id . '/archive');

        self::assertResponseRedirects('/app/subscriptions/' . $subscription->id);

        $container = self::getContainer();
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
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
        ]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/subscriptions/' . $subscription->id . '/archive');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Subscription archived successfully');
    }

    public function testArchiveRequestWithInvalidIdReturns404(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/archive');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/subscriptions/' . $subscription->id . '/archive');

        self::assertResponseStatusCodeSame(405);
    }

    public function testArchiveCreatesSubscriptionEvent(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $initialEventCount = \count($subscription->subscriptionEvents);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_POST, uri: '/app/subscriptions/' . $subscription->id . '/archive');

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $archivedSubscription = $repository->find($subscription->id);
        self::assertNotNull($archivedSubscription);
        self::assertGreaterThan($initialEventCount, \count($archivedSubscription->subscriptionEvents));
    }
}
