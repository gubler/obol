<?php

// ABOUTME: Feature test for the payment generation scheduler with real database integration.
// ABOUTME: Dispatches via the command bus so the doctrine_transaction middleware commits the handler's work.

declare(strict_types=1);

namespace App\Tests\Feature\Scheduler;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Lib\Bus\CommandBus;
use App\Message\Scheduler\GeneratePaymentsMessage;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GeneratePaymentsSchedulerTest extends WebTestCase
{
    public function testGeneratesPaymentForDueSubscription(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            'nextRenewal' => new \DateTimeImmutable('-35 days'),
        ]);

        $container = self::getContainer();
        $container->get(CommandBus::class)->dispatch(new GeneratePaymentsMessage());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();

        $updatedSubscription = $entityManager->getRepository(Subscription::class)->find($subscription->id);
        self::assertNotNull($updatedSubscription);
        self::assertCount(1, $updatedSubscription->payments);
    }

    public function testSkipsSubscriptionNotYetDue(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
            'cost' => new Money(999, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            'nextRenewal' => new \DateTimeImmutable('+10 days'),
        ]);

        $container = self::getContainer();
        $container->get(CommandBus::class)->dispatch(new GeneratePaymentsMessage());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();

        foreach ($entityManager->getRepository(Subscription::class)->findAll() as $sub) {
            self::assertCount(0, $sub->payments);
        }
    }

    public function testSkipsArchivedSubscription(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new([
            'category' => $category,
            'name' => 'Old Service',
            'cost' => new Money(999, Currency::USD),
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 1,
            'nextRenewal' => new \DateTimeImmutable('-35 days'),
        ])->archived()->create();

        $container = self::getContainer();
        $container->get(CommandBus::class)->dispatch(new GeneratePaymentsMessage());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();

        $updatedSubscription = $entityManager->getRepository(Subscription::class)->find($subscription->id);
        self::assertNotNull($updatedSubscription);
        self::assertCount(0, $updatedSubscription->payments);
    }
}
