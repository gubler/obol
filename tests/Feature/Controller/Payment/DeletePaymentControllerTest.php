<?php

// ABOUTME: Feature tests for DeletePaymentController verifying payment deletion.
// ABOUTME: Tests deletion, flash message, 404 handling, and POST-only access.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Payment;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DeletePaymentControllerTest extends WebTestCase
{
    public function testDeletesPayment(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'amount' => new Money(1599, Currency::USD),
        ]);

        $client->request('POST', '/payments/' . $payment->id . '/delete');

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = self::getContainer();
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
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'amount' => new Money(1599, Currency::USD),
        ]);

        $client->request('POST', '/payments/' . $payment->id . '/delete');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Payment deleted successfully');
    }

    public function testReturns404ForInvalidPaymentId(): void
    {
        $client = self::createClient();

        $client->request('POST', '/payments/01JKXXXXXXXXXXXXXXXXXXXXXXX/delete');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyAcceptsPostMethod(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);
        $payment = PaymentFactory::createOne([
            'subscription' => $subscription,
            'amount' => new Money(1599, Currency::USD),
        ]);

        $client->request('GET', '/payments/' . $payment->id . '/delete');

        self::assertResponseStatusCodeSame(405);
    }
}
