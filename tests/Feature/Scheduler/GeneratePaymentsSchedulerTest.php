<?php

// ABOUTME: Feature test for the payment generation scheduler with real database integration.
// ABOUTME: Tests that the scheduler correctly generates payments for due subscriptions and skips others.

declare(strict_types=1);

namespace App\Tests\Feature\Scheduler;

use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Message\Scheduler\GeneratePaymentsHandler;
use App\Message\Scheduler\GeneratePaymentsMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GeneratePaymentsSchedulerTest extends KernelTestCase
{
    private function createHandler(): GeneratePaymentsHandler
    {
        $container = static::getContainer();

        /** @var SubscriptionRepository $repository */
        $repository = $container->get(SubscriptionRepository::class);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        return new GeneratePaymentsHandler($repository, $entityManager);
    }

    public function testGeneratesPaymentForDueSubscription(): void
    {
        self::bootKernel();

        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => 1599,
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            'lastPaidDate' => new \DateTimeImmutable('-35 days'),
        ]);

        $handler = $this->createHandler();
        $handler(new GeneratePaymentsMessage());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $repository = $entityManager->getRepository(Subscription::class);
        $updatedSubscription = $repository->find($subscription->id);
        self::assertNotNull($updatedSubscription);
        self::assertCount(1, $updatedSubscription->payments);
    }

    public function testSkipsSubscriptionNotYetDue(): void
    {
        self::bootKernel();

        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
            'cost' => 999,
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            'lastPaidDate' => new \DateTimeImmutable('-10 days'),
        ]);

        $handler = $this->createHandler();
        $handler(new GeneratePaymentsMessage());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $repository = $entityManager->getRepository(Subscription::class);
        $allSubscriptions = $repository->findAll();
        foreach ($allSubscriptions as $sub) {
            self::assertCount(0, $sub->payments);
        }
    }

    public function testSkipsArchivedSubscription(): void
    {
        self::bootKernel();

        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Old Service',
            'cost' => 999,
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            'lastPaidDate' => new \DateTimeImmutable('-35 days'),
        ])->archived()->create();

        $handler = $this->createHandler();
        $handler(new GeneratePaymentsMessage());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $repository = $entityManager->getRepository(Subscription::class);
        $updatedSubscription = $repository->find($subscription->id);
        self::assertNotNull($updatedSubscription);
        self::assertCount(0, $updatedSubscription->payments);
    }
}
