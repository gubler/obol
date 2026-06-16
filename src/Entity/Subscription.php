<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentGeneration;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
use App\Enum\TileColor;
use App\Lib\ChangeContextGenerator\Change;
use App\Lib\ChangeContextGenerator\ChangeContextGenerator;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Assert\Assertion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * Any command handler that creates, mutates, or removes a subscription must announce it via
 * SubscriptionChangeNotifier::notifyChanged() after the change, so the obligation snapshot series
 * stays current (see ADR-0010). The notifier defers the event until the command's transaction commits.
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    /**
     * Look-ahead bound for the savings-target sum; only the next renewal or two ever contribute, so
     * this is purely a backstop against an unbounded loop for a degenerate sub-cent monthly chunk.
     */
    private const int SAVINGS_HORIZON = 24;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    #[ORM\Column]
    public private(set) bool $archived = false;

    /**
     * Whether Obol generates this subscription's payments automatically or the user manages them.
     * Under `Manual` the scheduler generates nothing and the renewal anchor is left entirely in the
     * user's hands; recording or removing a payment no longer shifts `nextRenewal`. Set to `Manual`
     * when the user deletes the latest payment; returned to `Automated` only by an explicit resume
     * with a future renewal date. See ADR-0008.
     */
    #[ORM\Column(enumType: PaymentGeneration::class)]
    public private(set) PaymentGeneration $paymentGeneration = PaymentGeneration::Automated;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'subscription', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(value: ['paidDate' => 'DESC', 'createdAt' => 'DESC'])]
    public private(set) Collection $payments;

    /**
     * @var Collection<int, SubscriptionEvent>
     */
    #[ORM\OneToMany(targetEntity: SubscriptionEvent::class, mappedBy: 'subscription', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public private(set) Collection $subscriptionEvents;

    #[ORM\Column(length: 255)]
    public private(set) string $name;

    #[ORM\Column]
    public private(set) int $paymentPeriodCount;

    #[ORM\Embedded(class: Money::class, columnPrefix: 'cost_')]
    public private(set) Money $cost;

    #[ORM\Column(enumType: TileColor::class)]
    public private(set) TileColor $color;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'subscriptions')]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Category $category,
        string $name,
        #[ORM\Column]
        public private(set) \DateTimeImmutable $nextRenewal,
        #[ORM\Column(enumType: PaymentPeriod::class)]
        public private(set) PaymentPeriod $paymentPeriod,
        int $paymentPeriodCount,
        Money $cost,
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $description = '',
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $link = '',
        #[ORM\Column(length: 255)]
        public private(set) string $logo = '',
        ?TileColor $color = null,
    ) {
        $name = $this->normalizeAndAssert(name: $name, cost: $cost, paymentPeriodCount: $paymentPeriodCount);

        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->payments = new ArrayCollection();
        $this->subscriptionEvents = new ArrayCollection();
        $this->name = $name;
        $this->paymentPeriodCount = $paymentPeriodCount;
        $this->cost = $cost;
        // A subscription always carries a tile color; pick a random swatch when one is not supplied.
        $this->color = $color ?? TileColor::random();
    }

    /**
     * The subscription's cost normalized to a one-month equivalent, in its own currency (rounded to
     * the nearest whole minor unit). Weekly cadences use 52 weeks per year.
     */
    public function monthlyCost(): Money
    {
        $monthly = (int) round($this->cost->minorAmount / ($this->paymentPeriodCount * $this->paymentPeriod->monthsPerPeriod()));

        return new Money($monthly, $this->cost->currency);
    }

    /**
     * The amount that should be set aside by `$asOf` to cover upcoming renewals, in the currency's
     * minor units. Models a monthly budget saved one month ahead: `monthlyCost` is allocated on the
     * first of each calendar month, a renewal is fully funded by the first of the month before it
     * falls due, and that full `cost` is held until the renewal is recorded paid (which advances
     * `nextRenewal`). Saving toward the renewal after it begins once the current one is funded, so a
     * bill that is funded but not yet paid is held in full *on top of* the next cycle's accrual. See
     * ADR-0009; the lead and allocation cadence become per-user settings later (#120, #121).
     */
    public function savingsTarget(\DateTimeImmutable $asOf): Money
    {
        // Weekly bills renew several times within an allocation month, which the by-month model
        // cannot prorate; until by-week proration lands (#120) a weekly bill is treated as one
        // payment in hand.
        if (PaymentPeriod::Week === $this->paymentPeriod) {
            return $this->cost;
        }

        $costMinor = $this->cost->minorAmount;
        $monthlyCost = $this->monthlyCost()->minorAmount;
        $asOfMonth = $this->monthOrdinal($asOf);

        $renewal = $this->nextRenewal;
        $total = 0;

        // Sum what should be set aside for each upcoming renewal. In practice no more than two ever
        // overlap (the funded-but-unpaid one and the next cycle just begun), so this settles in a
        // couple of iterations; the horizon is only a backstop against degenerate sub-cent chunks.
        for ($i = 0; $i < self::SAVINGS_HORIZON; ++$i) {
            $fundByMonth = $this->monthOrdinal($renewal) - 1; // first of the month before it falls due
            $monthsToFund = max(0, $fundByMonth - $asOfMonth);
            $funded = max(0, $costMinor - $monthlyCost * $monthsToFund);

            if (0 === $funded) {
                break;
            }

            $total += $funded;
            $renewal = $renewal->add($this->renewalInterval());
        }

        return new Money($total, $this->cost->currency);
    }

    /**
     * What is still owed by `$periodEnd`: the sum of `cost` for every renewal from `nextRenewal` up
     * to and including `$periodEnd`, in the subscription's currency. Payments are never consulted -
     * `nextRenewal` is the authoritative next-owed - so a `nextRenewal` already in the past counts its
     * overdue renewals and arrears fall out for free. See #145 and ADR-0010.
     */
    public function remainingInPeriod(\DateTimeImmutable $periodEnd): Money
    {
        $count = 0;
        $renewal = $this->nextRenewal;
        while ($renewal <= $periodEnd) {
            ++$count;
            $renewal = $renewal->add($this->renewalInterval());
        }

        return new Money($this->cost->minorAmount * $count, $this->cost->currency);
    }

    /**
     * The calendar month of `$date` as a count of months since year zero, for month arithmetic.
     */
    private function monthOrdinal(\DateTimeImmutable $date): int
    {
        return (int) $date->format('Y') * 12 + (int) $date->format('n') - 1;
    }

    public function recordPayment(
        \DateTimeImmutable $paidDate,
        PaymentType $paymentType,
        ?int $amount = null,
    ): void {
        // A payment is denominated in its subscription's currency; a custom amount is the minor-unit
        // figure in that same currency, and the default is the full subscription cost.
        $money = null !== $amount ? new Money($amount, $this->cost->currency) : $this->cost;

        // A payment advances the anchor only when generation is automated and the payment falls in
        // the current open period (paid early-within-period, on time, or late) - not when it backfills
        // a prior period. The flag records that fact so removal can undo exactly this advance.
        $advancesRenewal = $this->generatesPaymentsAutomatically()
            && $paidDate > $this->nextRenewal->sub($this->renewalInterval());

        $this->payments->add(
            new Payment(
                subscription: $this,
                type: $paymentType,
                amount: $money,
                paidDate: $paidDate,
                advancedRenewal: $advancesRenewal,
            )
        );

        if ($advancesRenewal) {
            $this->nextRenewal = $this->nextRenewal->add($this->renewalInterval());
        }
    }

    public function removePayment(Payment $payment): void
    {
        $this->payments->removeElement($payment);

        if ($this->removalRollsBackAnchor($payment)) {
            $this->nextRenewal = $this->nextRenewal->sub($this->renewalInterval());
        }
    }

    /**
     * Deletes the most recent payment, the only one a user may remove. Removing a payment that
     * advanced the anchor rolls it back (see `removalRollsBackAnchor`). Deleting a `Generated`
     * payment means "the scheduler was wrong, I did not pay this", so it hands generation to the
     * user (`Manual`); deleting a `Verified` payment is data correction and leaves generation alone.
     * See ADR-0008.
     */
    public function removeLatestPayment(Payment $payment): void
    {
        $latest = $this->latestPayment();
        Assertion::true(
            null !== $latest && $payment->id->equals($latest->id),
            'Only the latest payment can be deleted',
        );

        $switchesToManual = $this->removalSwitchesToManual($payment);

        $this->removePayment($payment);

        if ($switchesToManual) {
            $this->switchToManualPayments();
        }
    }

    /**
     * Whether removing the given payment would roll the renewal anchor back one interval: it does so
     * only for an advancing payment while generation is still automated. Drives both the actual
     * rollback and the consequence shown in the delete confirmation.
     */
    public function removalRollsBackAnchor(Payment $payment): bool
    {
        return $this->generatesPaymentsAutomatically() && $payment->advancedRenewal;
    }

    /**
     * The renewal anchor that would result from removing the given payment, for the delete
     * confirmation. Returns the unchanged anchor when removal would not shift it.
     */
    public function renewalAfterRemoving(Payment $payment): \DateTimeImmutable
    {
        return $this->removalRollsBackAnchor($payment)
            ? $this->nextRenewal->sub($this->renewalInterval())
            : $this->nextRenewal;
    }

    /**
     * Whether removing the given payment would switch the subscription to manual generation: only a
     * `Generated` payment does. Used by the delete confirmation.
     */
    public function removalSwitchesToManual(Payment $payment): bool
    {
        return PaymentType::Generated === $payment->type;
    }

    /**
     * The most recent payment by paid date, or null when there are none. This is the only payment a
     * user may delete (see `removeLatestPayment`); the UI offers the delete action on it alone.
     */
    public function latestPayment(): ?Payment
    {
        $latest = null;
        foreach ($this->payments as $payment) {
            if (null === $latest || $payment->paidDate > $latest->paidDate) {
                $latest = $payment;
            }
        }

        return $latest;
    }

    private function renewalInterval(): \DateInterval
    {
        return new \DateInterval(match ($this->paymentPeriod) {
            PaymentPeriod::Week => \sprintf('P%dW', $this->paymentPeriodCount),
            PaymentPeriod::Month => \sprintf('P%dM', $this->paymentPeriodCount),
            PaymentPeriod::Year => \sprintf('P%dY', $this->paymentPeriodCount),
        });
    }

    public function update(
        Category $category,
        string $name,
        \DateTimeImmutable $nextRenewal,
        string $description,
        string $link,
        string $logo,
        PaymentPeriod $paymentPeriod,
        int $paymentPeriodCount,
        Money $cost,
        TileColor $color,
    ): void {
        $name = $this->normalizeAndAssert(name: $name, cost: $cost, paymentPeriodCount: $paymentPeriodCount);

        // A subscription's currency is fixed once it has any recorded payment: the payments are
        // denominated in it, so changing it would silently restate that history. It stays editable
        // only while no payment exists (the picker is disabled in the edit form once one does).
        Assertion::true(
            $this->payments->isEmpty() || $cost->currency === $this->cost->currency,
            'Currency cannot be changed once a payment has been recorded',
        );

        $updateGenerator = new ChangeContextGenerator(
            changes: [
                new Change(field: 'category', current: $this->category->name, new: $category->name),
                new Change(field: 'name', current: $this->name, new: $name),
                new Change(field: 'nextRenewal', current: $this->nextRenewal->format(format: 'c'), new: $nextRenewal->format(format: 'c')),
                new Change(field: 'description', current: $this->description, new: $description),
                new Change(field: 'link', current: $this->link, new: $link),
                new Change(field: 'logo', current: $this->logo, new: $logo),
                new Change(field: 'color', current: $this->color->value, new: $color->value),
            ]
        );

        $costChangeGenerator = new ChangeContextGenerator(
            changes: [
                new Change(field: 'paymentPeriod', current: $this->paymentPeriod->value, new: $paymentPeriod->value),
                new Change(field: 'paymentPeriodCount', current: $this->paymentPeriodCount, new: $paymentPeriodCount),
                new Change(field: 'cost', current: $this->cost->minorAmount, new: $cost->minorAmount),
            ]
        );

        $updateContext = $updateGenerator->buildContext();
        $costChangeContext = $costChangeGenerator->buildContext();

        if ([] !== $updateContext) {
            $event = new SubscriptionEvent(
                subscription: $this,
                type: SubscriptionEventType::Update,
                context: $updateContext,
            );
            $this->subscriptionEvents->add($event);
        }

        if ([] !== $costChangeContext) {
            $event = new SubscriptionEvent(
                subscription: $this,
                type: SubscriptionEventType::CostChange,
                context: $costChangeContext,
            );
            $this->subscriptionEvents->add($event);
        }

        $this->category = $category;
        $this->name = $name;
        $this->nextRenewal = $nextRenewal;
        $this->description = $description;
        $this->link = $link;
        $this->logo = $logo;
        $this->paymentPeriod = $paymentPeriod;
        $this->paymentPeriodCount = $paymentPeriodCount;
        $this->cost = $cost;
        $this->color = $color;
    }

    /**
     * Trims the name and asserts the subscription's invariants, returning the normalized name.
     */
    private function normalizeAndAssert(string $name, Money $cost, int $paymentPeriodCount): string
    {
        $name = trim(string: $name);
        Assertion::notEq(value1: $name, value2: '', message: 'Subscription name cannot be empty');
        Assertion::greaterThan(value: $cost->minorAmount, limit: 0, message: 'Subscription cost must be greater than zero');
        Assertion::greaterThan(value: $paymentPeriodCount, limit: 0, message: 'Payment period count must be greater than zero');

        return $name;
    }

    public function archive(): void
    {
        $this->archived = true;
        $this->subscriptionEvents->add(
            new SubscriptionEvent(
                subscription: $this,
                type: SubscriptionEventType::Archive,
                context: [],
            )
        );
    }

    public function unarchive(): void
    {
        $this->archived = false;
        $this->subscriptionEvents->add(
            new SubscriptionEvent(
                subscription: $this,
                type: SubscriptionEventType::Unarchive,
                context: [],
            )
        );
    }

    public function generatesPaymentsAutomatically(): bool
    {
        return PaymentGeneration::Automated === $this->paymentGeneration;
    }

    /**
     * Hands renewal management to the user: the scheduler stops generating payments and the anchor
     * is no longer shifted automatically. Returned to automated by an explicit resume with a future
     * renewal date.
     */
    public function switchToManualPayments(): void
    {
        $this->paymentGeneration = PaymentGeneration::Manual;
    }

    /**
     * Resumes automated generation, anchored to a future renewal. The anchor must be after today so
     * resuming never triggers an immediate catch-up generation on the next scheduler run.
     */
    public function automatePayments(\DateTimeImmutable $nextRenewal): void
    {
        Assertion::true(
            $nextRenewal > new \DateTimeImmutable('today'),
            'Automated payments require a renewal date in the future',
        );

        $this->paymentGeneration = PaymentGeneration::Automated;
        $this->nextRenewal = $nextRenewal;
    }

    /**
     * A sensible future anchor for resuming automated generation: the configured cadence stepped
     * forward from the current anchor until it lands strictly after today. A renewal already in the
     * future is returned unchanged.
     */
    public function suggestedResumeRenewal(): \DateTimeImmutable
    {
        $today = new \DateTimeImmutable('today');
        $renewal = $this->nextRenewal;
        while ($renewal <= $today) {
            $renewal = $renewal->add($this->renewalInterval());
        }

        return $renewal;
    }
}
