<?php

// ABOUTME: Integration test for the ordering of a subscription's payments collection.
// ABOUTME: Verifies Doctrine hydrates payments newest-paid-first, with createdAt as the tie-break.

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\Subscription;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SubscriptionPaymentOrderingTest extends WebTestCase
{
    private function entityManager(): EntityManagerInterface
    {
        /* @var EntityManagerInterface $entityManager */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testPaymentsAreOrderedByPaidDateDescending(): void
    {
        self::createClient();
        $subscription = SubscriptionFactory::createOne();

        // Persisted out of chronological order, mirroring backfilling historical data by hand.
        PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => new \DateTimeImmutable('2024-03-01')]);
        PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => new \DateTimeImmutable('2024-01-01')]);
        PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => new \DateTimeImmutable('2024-02-01')]);

        $entityManager = $this->entityManager();
        $entityManager->clear();

        $reloaded = $entityManager->find(Subscription::class, $subscription->id);
        self::assertInstanceOf(Subscription::class, $reloaded);

        $paidDates = array_map(
            static fn ($payment): string => $payment->paidDate->format('Y-m-d'),
            $reloaded->payments->toArray(),
        );

        self::assertSame(['2024-03-01', '2024-02-01', '2024-01-01'], $paidDates);
    }

    public function testPaymentsOnTheSameDayBreakTiesByCreatedAtDescending(): void
    {
        self::createClient();
        $subscription = SubscriptionFactory::createOne();

        $paidDate = new \DateTimeImmutable('2024-05-01');
        PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => $paidDate, 'createdAt' => new \DateTimeImmutable('2024-05-01 09:00:00')]);
        PaymentFactory::createOne(['subscription' => $subscription, 'paidDate' => $paidDate, 'createdAt' => new \DateTimeImmutable('2024-05-01 11:00:00')]);

        $entityManager = $this->entityManager();
        $entityManager->clear();

        $reloaded = $entityManager->find(Subscription::class, $subscription->id);
        self::assertInstanceOf(Subscription::class, $reloaded);

        $createdAt = array_map(
            static fn ($payment): string => $payment->createdAt->format('H:i'),
            $reloaded->payments->toArray(),
        );

        self::assertSame(['11:00', '09:00'], $createdAt);
    }
}
