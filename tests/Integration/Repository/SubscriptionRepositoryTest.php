<?php

// ABOUTME: Integration tests for SubscriptionRepository's timezone-aware payment-generation finder.
// ABOUTME: A subscription is due when its naive nextRenewal has been reached in the owner's local zone (ADR-0016).

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Subscription;
use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SubscriptionRepositoryTest extends WebTestCase
{
    public function testFindAllPendingPaymentGenerationIsDueInTheOwnerLocalZoneNotUtc(): void
    {
        self::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var SubscriptionRepository $repository */
        $repository = $entityManager->getRepository(Subscription::class);

        // A single instant. At 04:00 UTC the local calendar day differs by zone:
        //   New York (UTC-4)  -> 2026-08-01 00:00  (Aug 1 has just begun)
        //   Tokyo    (UTC+9)  -> 2026-08-01 13:00  (well into Aug 1)
        //   Honolulu (UTC-10) -> 2026-07-31 18:00  (still Jul 31)
        $now = new \DateTimeImmutable('2026-08-01 04:00:00', new \DateTimeZone('UTC'));
        $augustFirst = new \DateTimeImmutable('2026-08-01 00:00:00');
        $julyThirtyFirst = new \DateTimeImmutable('2026-07-31 00:00:00');

        $newYorker = UserFactory::createOne(['timezone' => 'America/New_York']);
        $tokyoite = UserFactory::createOne(['timezone' => 'Asia/Tokyo']);
        $honoluluan = UserFactory::createOne(['timezone' => 'Pacific/Honolulu']);

        // Due: the local date has reached the Aug 1 renewal (NY just crossed midnight, Tokyo hours in).
        $nyDue = SubscriptionFactory::createOne(['owner' => $newYorker, 'category' => null, 'name' => 'ny-due', 'nextRenewal' => $augustFirst]);
        $tokyoDue = SubscriptionFactory::createOne(['owner' => $tokyoite, 'category' => null, 'name' => 'tokyo-due', 'nextRenewal' => $augustFirst]);
        // Honolulu is still on Jul 31: its Aug 1 renewal is NOT due yet (the bug this fixes - no day-early charge).
        SubscriptionFactory::createOne(['owner' => $honoluluan, 'category' => null, 'name' => 'honolulu-not-yet', 'nextRenewal' => $augustFirst]);
        // But Honolulu's Jul 31 renewal IS due there (local time is Jul 31 evening).
        $honoluluDue = SubscriptionFactory::createOne(['owner' => $honoluluan, 'category' => null, 'name' => 'honolulu-due', 'nextRenewal' => $julyThirtyFirst]);

        // Exclusions unrelated to timezone: future, archived, and manual are never generated.
        SubscriptionFactory::createOne(['owner' => $newYorker, 'category' => null, 'name' => 'future', 'nextRenewal' => new \DateTimeImmutable('2026-09-01 00:00:00')]);
        SubscriptionFactory::new(['owner' => $newYorker, 'category' => null, 'name' => 'archived', 'nextRenewal' => $augustFirst])->archived()->create();
        SubscriptionFactory::new(['owner' => $newYorker, 'category' => null, 'name' => 'manual', 'nextRenewal' => $augustFirst])->manual()->create();

        $due = $repository->findAllPendingPaymentGeneration($now);
        $dueNames = array_map(static fn (Subscription $s): string => $s->name, $due);
        sort($dueNames);

        self::assertSame(['honolulu-due', 'ny-due', 'tokyo-due'], $dueNames);
        // Sanity: the entities the finder chose are the ones we expect due, by id.
        $dueIds = array_map(static fn (Subscription $s): string => (string) $s->id, $due);
        self::assertContains((string) $nyDue->id, $dueIds);
        self::assertContains((string) $tokyoDue->id, $dueIds);
        self::assertContains((string) $honoluluDue->id, $dueIds);
    }
}
