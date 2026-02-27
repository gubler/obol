<?php

// ABOUTME: Feature tests for DeletePaymentController verifying payment deletion.
// ABOUTME: Tests deletion, flash message, 404 handling, and POST-only access.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Payment;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DeletePaymentControllerTest extends WebTestCase
{
    public function testDeletesPayment(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'amount' => 1599,
        ]);

        $client->request('POST', '/payments/' . $payment->id . '/delete');

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Subscription::class);
        $entityManager->clear();

        $updatedSubscription = $repository->find($subscription->id);
        self::assertNotNull($updatedSubscription);
        self::assertCount(0, $updatedSubscription->payments);
    }

    public function testShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'amount' => 1599,
        ]);

        $client->request('POST', '/payments/' . $payment->id . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Payment deleted successfully');
    }

    public function testReturns404ForInvalidPaymentId(): void
    {
        $client = static::createClient();

        $client->request('POST', '/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/delete');

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
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'amount' => 1599,
        ]);

        $client->request('GET', '/payments/' . $payment->id . '/delete');

        self::assertResponseStatusCodeSame(405);
    }
}
