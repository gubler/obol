<?php

// ABOUTME: Runner for FindPaymentSourceCompositionQuery: each source's share of the monthly obligation.
// ABOUTME: Groups active subscriptions by payment source, converts each share via CurrencyTotaller, sorts by size.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Enum\TileColor;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'query.bus', handles: FindPaymentSourceCompositionQuery::class)]
final readonly class FindPaymentSourceCompositionRunner
{
    /**
     * Bucket key for subscriptions with no payment source. The empty string never collides with a
     * source's RFC 4122 id.
     */
    private const string UNASSIGNED_KEY = '';

    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(FindPaymentSourceCompositionQuery $query): Composition
    {
        $asOf = new \DateTimeImmutable();
        $subscriptions = $this->subscriptionRepository->findBy(['archived' => false]);

        /** @var array<string, array{source: ?PaymentSource, costs: list<Money>}> $bySource */
        $bySource = [];
        foreach ($subscriptions as $subscription) {
            // Subscriptions with no payment source collapse into a single unassigned slice.
            $key = $subscription->paymentSource?->id->toRfc4122() ?? self::UNASSIGNED_KEY;
            $bySource[$key] ??= ['source' => $subscription->paymentSource, 'costs' => []];
            $bySource[$key]['costs'][] = $subscription->monthlyCost();
        }

        $slices = array_map(
            function (array $group) use ($asOf): CompositionSlice {
                $share = $this->currencyTotaller->total($group['costs'], $asOf);
                $source = $group['source'];

                return new CompositionSlice(
                    label: null !== $source ? $source->name : $this->translator->trans('subscription.group.unassigned'),
                    converted: $share->converted,
                    breakdown: $share->breakdown,
                    isApproximate: $share->isApproximate,
                    id: $source?->id,
                    // Unassigned takes the reserved neutral Charcoal swatch; payment sources carry no icon.
                    color: null !== $source ? $source->color : TileColor::Charcoal,
                );
            },
            array_values($bySource),
        );

        usort(
            $slices,
            static fn (CompositionSlice $a, CompositionSlice $b): int => $b->converted->minorAmount <=> $a->converted->minorAmount,
        );

        $total = $this->currencyTotaller->total(
            array_map(static fn (Subscription $s): Money => $s->monthlyCost(), $subscriptions),
            $asOf,
        );

        return new Composition(slices: $slices, total: $total, asOf: $asOf);
    }
}
