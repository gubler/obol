<?php

// ABOUTME: Feature tests for UnarchiveSubscriptionController verifying subscription unarchiving.
// ABOUTME: Tests ensure proper unarchiving, 404 handling, POST-only access, and flash messages.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\SameOriginPostTrait;
use Doctrine\ORM\EntityManagerInterface;

final class UnarchiveSubscriptionControllerTest extends AuthenticatedTestCase
{
    use SameOriginPostTrait;

    public function testUnarchiveRequestUnarchivesSubscription(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Netflix',
        ])->archived()->create();

        $this->postSameOrigin($client, '/app/subscriptions/' . $subscription->id . '/unarchive');

        self::assertResponseRedirects('/app/subscriptions/' . $subscription->id);

        $container = self::getContainer();
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
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Spotify',
        ])->archived()->create();

        $this->postSameOrigin($client, '/app/subscriptions/' . $subscription->id . '/unarchive');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Subscription unarchived successfully');
    }

    public function testUnarchiveRequestWithInvalidIdReturns404(): void
    {
        $client = $this->authenticatedClient();

        $this->postSameOrigin($client, '/app/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/unarchive');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Netflix',
        ])->archived()->create();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/subscriptions/' . $subscription->id . '/unarchive');

        self::assertResponseStatusCodeSame(405);
    }

    public function testUnarchiveCreatesSubscriptionEvent(): void
    {
        $client = $this->authenticatedClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Netflix',
        ])->archived()->create();

        $initialEventCount = \count($subscription->subscriptionEvents);

        $this->postSameOrigin($client, '/app/subscriptions/' . $subscription->id . '/unarchive');

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $unarchivedSubscription = $repository->find($subscription->id);
        self::assertNotNull($unarchivedSubscription);
        self::assertGreaterThan($initialEventCount, \count($unarchivedSubscription->subscriptionEvents));
    }
}
