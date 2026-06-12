<?php

// ABOUTME: Integration test for the event-driven obligation snapshot wiring, end to end through the buses.
// ABOUTME: A subscription command commits, then the deferred SubscriptionsChanged event records a snapshot if changed.

declare(strict_types=1);

use App\Entity\ObligationSnapshot;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Message\Command\Subscription\ArchiveSubscriptionCommand;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use App\Repository\ObligationSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

test('a subscription create, no-op update, and archive record obligation snapshots only when the obligation moves', function (): void {
    $this->createClient();
    $container = $this->getContainer();
    /** @var MessageBusInterface $commandBus */
    $commandBus = $container->get(MessageBusInterface::class);
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    /** @var ObligationSnapshotRepository $snapshots */
    $snapshots = $entityManager->getRepository(ObligationSnapshot::class);

    $category = CategoryFactory::createOne();

    // Create: obligation goes 0 -> 4000 USD/mo, so one snapshot is recorded.
    $commandBus->dispatch(new CreateSubscriptionCommand(
        categoryId: $category->id,
        name: 'Streaming',
        nextRenewal: new DateTimeImmutable('+1 month'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 4000,
        currency: Currency::USD,
        color: TileColor::cases()[0],
    ));

    expect($snapshots->count([]))->toBe(1)
        ->and($snapshots->findLatest()?->obligationsByCurrency)->toEqual(['USD' => 4000])
    ;

    $subscription = $entityManager->getRepository(Subscription::class)->findOneBy([]);
    assert($subscription instanceof Subscription);

    // No-op update (rename only): obligation is unchanged, so no new snapshot.
    $commandBus->dispatch(new UpdateSubscriptionCommand(
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

    expect($snapshots->count([]))->toBe(1);

    // Archive: obligation drops to nothing, so a new snapshot with an empty map is recorded.
    $commandBus->dispatch(new ArchiveSubscriptionCommand($subscription->id));

    expect($snapshots->count([]))->toBe(2)
        ->and($snapshots->findLatest()?->obligationsByCurrency)->toBe([])
    ;
});
