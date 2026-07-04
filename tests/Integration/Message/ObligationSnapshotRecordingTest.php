<?php

// ABOUTME: Integration test for the event-driven obligation snapshot wiring, end to end through the buses.
// ABOUTME: A subscription command commits, then the deferred SubscriptionsChanged event records a snapshot if changed.

declare(strict_types=1);

namespace App\Tests\Integration\Message;

use App\Entity\ObligationSnapshot;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Factory\UserFactory;
use App\Message\Command\Subscription\ArchiveSubscriptionCommand;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use App\Repository\ObligationSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class ObligationSnapshotRecordingTest extends WebTestCase
{
    public function testASubscriptionCreateNoOpUpdateAndArchiveRecordObligationSnapshotsOnlyWhenTheObligationMoves(): void
    {
        self::createClient();
        $container = self::getContainer();
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get(MessageBusInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var ObligationSnapshotRepository $snapshots */
        $snapshots = $entityManager->getRepository(ObligationSnapshot::class);

        $owner = UserFactory::createOne();
        $category = CategoryFactory::createOne(['owner' => $owner]);

        // Create: obligation goes 0 -> 4000 USD/mo, so one snapshot is recorded.
        $commandBus->dispatch(new CreateSubscriptionCommand(
            ownerUserId: $owner->id,
            categoryId: $category->id,
            name: 'Streaming',
            nextRenewal: new \DateTimeImmutable('+1 month'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 4000,
            currency: Currency::USD,
            color: TileColor::cases()[0],
        ));

        self::assertSame(1, $snapshots->count([]));
        self::assertEqualsCanonicalizing(['USD' => 4000], $snapshots->findLatest()?->obligationsByCurrency);

        $subscription = $entityManager->getRepository(Subscription::class)->findOneBy([]);
        self::assertInstanceOf(Subscription::class, $subscription);

        // No-op update (rename only): obligation is unchanged, so no new snapshot.
        $commandBus->dispatch(new UpdateSubscriptionCommand(
            ownerUserId: $owner->id,
            subscriptionId: $subscription->id,
            categoryId: $category->id,
            name: 'Renamed',
            nextRenewal: $subscription->nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 4000,
            currency: Currency::USD,
            color: TileColor::cases()[0],
        ));

        self::assertSame(1, $snapshots->count([]));

        // Archive: obligation drops to nothing, so a new snapshot with an empty map is recorded.
        $commandBus->dispatch(new ArchiveSubscriptionCommand(ownerUserId: $owner->id, subscriptionId: $subscription->id));

        self::assertSame(2, $snapshots->count([]));
        self::assertSame([], $snapshots->findLatest()?->obligationsByCurrency);
    }
}
