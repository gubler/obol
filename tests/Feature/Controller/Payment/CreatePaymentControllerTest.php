<?php

// ABOUTME: Feature tests for CreatePaymentController verifying payment creation via form.
// ABOUTME: Tests form display, valid submission, validation errors, and 404 for invalid subscription.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Payment;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreatePaymentControllerTest extends WebTestCase
{
    public function testDisplaysCreatePaymentForm(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testCreatesPaymentWithValidData(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => 1599,
        ]);

        $initialPaymentCount = \count($subscription->payments);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '1599',
            'create_payment[paidDate]' => '2025-01-15',
        ]);

        self::assertResponseRedirects('/subscriptions/' . $subscription->id);

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Subscription::class);
        $entityManager->clear();

        $updatedSubscription = $repository->find($subscription->id);
        self::assertNotNull($updatedSubscription);
        self::assertGreaterThan($initialPaymentCount, \count($updatedSubscription->payments));
    }

    public function testShowsSuccessFlashMessageAfterCreation(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '1599',
            'create_payment[paidDate]' => '2025-01-15',
        ]);
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'Payment recorded successfully');
    }

    public function testShowsValidationErrorsForInvalidData(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request('GET', '/subscriptions/' . $subscription->id . '/payments/new');
        $client->submitForm('Save', [
            'create_payment[amount]' => '',
            'create_payment[paidDate]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.text-red-700');
    }

    public function testReturns404ForInvalidSubscriptionId(): void
    {
        $client = static::createClient();

        $client->request('GET', '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/payments/new');

        self::assertResponseStatusCodeSame(404);
    }
}
