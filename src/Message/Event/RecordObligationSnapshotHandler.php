<?php

// ABOUTME: Records an ObligationSnapshot for one user whenever their subscriptions change, only if their obligation moved.
// ABOUTME: Obligation changes only on subscription edits, so on-change recording captures each user's series exactly.

declare(strict_types=1);

namespace App\Message\Event;

use App\Entity\ObligationSnapshot;
use App\Repository\ObligationSnapshotRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'event.bus', handles: SubscriptionsChanged::class)]
final readonly class RecordObligationSnapshotHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private ObligationSnapshotRepository $obligationSnapshotRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SubscriptionsChanged $event): void
    {
        try {
            $current = $this->currentObligation($event->ownerUserId);

            $latest = $this->obligationSnapshotRepository->findLatestForOwner($event->ownerUserId);
            if ($latest instanceof ObligationSnapshot) {
                $previous = $latest->obligationsByCurrency;
                ksort($previous);
                // Append only when the obligation actually moved; a no-op edit (rename, colour) is skipped.
                // Both maps are key-sorted, so this strict comparison ignores currency order.
                if ($previous === $current) {
                    return;
                }
            }

            $owner = $this->userRepository->getForId($event->ownerUserId);
            $this->entityManager->persist(new ObligationSnapshot($owner, $current));
        } catch (\Throwable $throwable) {
            // A snapshot is a derived side effect; never let its failure roll back the edit that triggered it.
            $this->logger->error('Failed to record obligation snapshot', ['exception' => $throwable]);
        }
    }

    /**
     * One user's current total monthly obligation grouped by native currency, in each currency's minor
     * units. Key-sorted so storage and the change comparison have a deterministic, order-independent shape.
     *
     * @return array<string, int>
     */
    private function currentObligation(Ulid $ownerId): array
    {
        $obligationsByCurrency = [];
        foreach ($this->subscriptionRepository->findActiveForOwner($ownerId) as $subscription) {
            $monthly = $subscription->monthlyCost();
            $code = $monthly->currency->value;
            $obligationsByCurrency[$code] = ($obligationsByCurrency[$code] ?? 0) + $monthly->minorAmount;
        }

        ksort($obligationsByCurrency);

        return $obligationsByCurrency;
    }
}
