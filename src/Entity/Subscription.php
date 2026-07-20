<?php

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\CalendarDateType;
use App\Enum\PaymentGeneration;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
use App\Enum\TileColor;
use App\Lib\ChangeContextGenerator\Change;
use App\Lib\ChangeContextGenerator\ChangeContextGenerator;
use App\Repository\SubscriptionRepository;
use App\ValueObject\CalendarDate;
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
 * SubscriptionChangeNotifier::notifyChanged(), passing the owner's id, after the change, so that
 * owner's obligation snapshot series stays current (see ADR-0010). The notifier defers the event
 * until the command's transaction commits.
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

    /**
     * The next renewal, a calendar date whose meaning is the owner's local date resolved against the
     * owner's current timezone at read time (ADR-0016 / ADR-0021). Stored as a DATE; never an instant.
     */
    #[ORM\Column(type: CalendarDateType::NAME)]
    public private(set) CalendarDate $nextRenewal;

    /**
     * The canonical day of the month (1-31) a monthly or yearly subscription recurs on. Kept separate
     * from `nextRenewal->day` so a short month that clamps the anchor (Jan 31 to Feb 28) can restore
     * the intended day on the next long month, instead of drifting down forever. See ADR-0008.
     */
    #[ORM\Column]
    public private(set) int $renewalDay;

    public function __construct(
        /**
         * The user this subscription belongs to. Immutable: a subscription is never reassigned between
         * users, which is what makes Payment's denormalized owner safe to copy at birth (see ADR-0015).
         */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'owner_user_id', nullable: false)]
        public private(set) User $owner,
        #[ORM\ManyToOne(inversedBy: 'subscriptions')]
        #[ORM\JoinColumn(nullable: true)]
        public private(set) ?Category $category,
        string $name,
        CalendarDate $nextRenewal,
        #[ORM\Column(enumType: PaymentPeriod::class)]
        public private(set) PaymentPeriod $paymentPeriod,
        int $paymentPeriodCount,
        Money $cost,
        /*
         * The current instant (from the application clock), used to judge whether `$nextRenewal` is
         * already in the past in the owner's zone - in which case generation starts Manual so the
         * scheduler never fires a catch-up on a backfilled date. See `applyRenewalDate` and ADR-0008.
         */
        \DateTimeImmutable $now,
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $description = '',
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $link = '',
        #[ORM\Column(length: 255)]
        public private(set) string $logo = '',
        ?TileColor $color = null,
        /**
         * The payment source (method of payment) this subscription is charged to, or null when unassigned.
         * The form picker and audit-trail wiring land in a later slice; for now it is set at construction.
         */
        #[ORM\ManyToOne(inversedBy: 'subscriptions')]
        #[ORM\JoinColumn(nullable: true)]
        public private(set) ?PaymentSource $paymentSource = null,
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
        // Sets nextRenewal + renewalDay and, when the anchor is already past for the owner, starts the
        // subscription on manual generation (see applyRenewalDate).
        $this->applyRenewalDate($nextRenewal, $this->owner->localDateFor($now));
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
     * minor units. Models a monthly budget: `monthlyCost` is allocated on the first of each calendar
     * month, a renewal is fully funded by the first of the month `$leadMonths` ahead of it (0 funds by
     * the due month itself, 1 by the month before), and that full `cost` is held until the renewal is
     * recorded paid (which advances `nextRenewal`). Saving toward the renewal after it begins once the
     * current one is funded, so a bill that is funded but not yet paid is held in full *on top of* the
     * next cycle's accrual. The lead is the owner's per-user SavingsDisplay preference; see ADR-0009.
     */
    public function savingsTarget(CalendarDate $asOf, int $leadMonths): Money
    {
        Assertion::greaterOrEqualThan($leadMonths, 0, 'Savings lead must be zero or more months.');

        // Weekly bills renew several times within an allocation month, which the by-month model
        // cannot prorate; until by-week proration lands (#120) a weekly bill is treated as one
        // payment in hand.
        if (PaymentPeriod::Week === $this->paymentPeriod) {
            return $this->cost;
        }

        $costMinor = $this->cost->minorAmount;
        $monthlyCost = $this->monthlyCost()->minorAmount;
        $asOfMonth = $asOf->monthOrdinal();

        $total = 0;

        // Sum what should be set aside for each upcoming renewal. In practice no more than two ever
        // overlap (the funded-but-unpaid one and the next cycle just begun), so this settles in a
        // couple of iterations; the horizon is only a backstop against degenerate sub-cent chunks.
        // Each renewal is projected from the anchor by multiples so month-overflow never drifts it.
        for ($i = 0; $i < self::SAVINGS_HORIZON; ++$i) {
            $renewal = $this->advance($this->nextRenewal, $i);
            $fundByMonth = $renewal->monthOrdinal() - $leadMonths; // first of the month $leadMonths ahead of due
            $monthsToFund = max(0, $fundByMonth - $asOfMonth);
            $funded = max(0, $costMinor - $monthlyCost * $monthsToFund);

            if (0 === $funded) {
                break;
            }

            $total += $funded;
        }

        return new Money($total, $this->cost->currency);
    }

    /**
     * What is still owed by `$periodEnd`: the sum of `cost` for every renewal from `nextRenewal` up
     * to and including `$periodEnd`, in the subscription's currency. Payments are never consulted -
     * `nextRenewal` is the authoritative next-owed - so a `nextRenewal` already in the past counts its
     * overdue renewals and arrears fall out for free. Renewals are projected from the anchor by
     * multiples (never iterated by adding an interval), so a short month cannot drift the count. See
     * ADR-0008 and ADR-0010.
     */
    public function remainingInPeriod(CalendarDate $periodEnd): Money
    {
        $count = 0;
        while ($this->advance($this->nextRenewal, $count)->isOnOrBefore($periodEnd)) {
            ++$count;
        }

        return new Money($this->cost->minorAmount * $count, $this->cost->currency);
    }

    public function recordPayment(
        CalendarDate $paidDate,
        PaymentType $paymentType,
        ?int $amount = null,
    ): void {
        // A payment is denominated in its subscription's currency; a custom amount is the minor-unit
        // figure in that same currency, and the default is the full subscription cost.
        $money = null !== $amount ? new Money($amount, $this->cost->currency) : $this->cost;

        // A payment advances the anchor only when generation is automated and the payment falls in
        // the current open period (paid early-within-period, on time, or late) - not when it backfills
        // a prior period. The flag records that fact so removal can undo exactly this advance. The open
        // period starts strictly after the previous renewal (the anchor stepped back one interval).
        $advancesRenewal = $this->generatesPaymentsAutomatically()
            && $paidDate->isAfter($this->advance($this->nextRenewal, -1));

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
            $this->nextRenewal = $this->advance($this->nextRenewal, 1);
        }
    }

    public function removePayment(Payment $payment): void
    {
        $this->payments->removeElement($payment);

        if ($this->removalRollsBackAnchor($payment)) {
            $this->nextRenewal = $this->advance($this->nextRenewal, -1);
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
            $latest instanceof Payment && $payment->id->equals($latest->id),
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
    public function renewalAfterRemoving(Payment $payment): CalendarDate
    {
        return $this->removalRollsBackAnchor($payment)
            ? $this->advance($this->nextRenewal, -1)
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
     * The most recent payment, or null when there are none. Ordered by paid date, breaking a same-day
     * tie by which row was created last so "latest" is deterministic when two payments share a calendar
     * date (a calendar date carries no time to separate them). This is the only payment a user may
     * delete (see `removeLatestPayment`); the UI offers the delete action on it alone.
     */
    public function latestPayment(): ?Payment
    {
        $latest = null;
        foreach ($this->payments as $payment) {
            if (null === $latest
                || $payment->paidDate->isAfter($latest->paidDate)
                || ($payment->paidDate->equals($latest->paidDate) && $payment->createdAt > $latest->createdAt)) {
                $latest = $payment;
            }
        }

        return $latest;
    }

    /**
     * The renewal `$steps` billing intervals from `$from` (negative steps step backward), projected
     * from the anchor rather than accumulated, so month-overflow can never drift it. A weekly cadence
     * shifts by whole weeks; a monthly or yearly one lands on `renewalDay` clamped to the target
     * month's length - which is what restores the 31st after a short month, and makes the step exactly
     * reversible. See ADR-0008.
     */
    public function advance(CalendarDate $from, int $steps = 1): CalendarDate
    {
        return match ($this->paymentPeriod) {
            PaymentPeriod::Week => $from->plusWeeks($steps * $this->paymentPeriodCount),
            PaymentPeriod::Month => $this->onRenewalDay($from->monthOrdinal() + $steps * $this->paymentPeriodCount),
            PaymentPeriod::Year => $this->onRenewalDay($from->monthOrdinal() + $steps * 12 * $this->paymentPeriodCount),
        };
    }

    /**
     * The calendar date on `renewalDay` (clamped to the month's length) in the month named by the given
     * ordinal (`year * 12 + month - 1`, as CalendarDate::monthOrdinal produces).
     */
    private function onRenewalDay(int $monthOrdinal): CalendarDate
    {
        $year = intdiv($monthOrdinal, 12);
        $month = $monthOrdinal % 12 + 1;
        $daysInMonth = CalendarDate::for($year, $month, 1)->daysInMonth();

        return CalendarDate::for($year, $month, min($this->renewalDay, $daysInMonth));
    }

    /**
     * Whether the next renewal has been clamped off its canonical day by a short month (e.g. a
     * renewalDay of 31 showing as the 28th in February). Drives the adjusted-date affordance.
     */
    public function isNextRenewalAdjusted(): bool
    {
        return $this->nextRenewal->day !== $this->renewalDay;
    }

    public function update(
        ?Category $category,
        string $name,
        CalendarDate $nextRenewal,
        string $description,
        string $link,
        string $logo,
        PaymentPeriod $paymentPeriod,
        int $paymentPeriodCount,
        Money $cost,
        TileColor $color,
        \DateTimeImmutable $now,
        ?PaymentSource $paymentSource = null,
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
                // A subscription may have no category; the audit reads the absence as "Uncategorized".
                new Change(field: 'category', current: $this->category instanceof Category ? $this->category->name : 'Uncategorized', new: $category instanceof Category ? $category->name : 'Uncategorized'),
                // A subscription may have no payment source; the audit reads the absence as "Unassigned".
                new Change(field: 'paymentSource', current: $this->paymentSource instanceof PaymentSource ? $this->paymentSource->name : 'Unassigned', new: $paymentSource instanceof PaymentSource ? $paymentSource->name : 'Unassigned'),
                new Change(field: 'name', current: $this->name, new: $name),
                new Change(field: 'nextRenewal', current: (string) $this->nextRenewal, new: (string) $nextRenewal),
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
        $this->paymentSource = $paymentSource;
        $this->name = $name;
        $this->description = $description;
        $this->link = $link;
        $this->logo = $logo;
        $this->paymentPeriod = $paymentPeriod;
        $this->paymentPeriodCount = $paymentPeriodCount;
        $this->cost = $cost;
        $this->color = $color;
        // Sets nextRenewal + renewalDay and, when the new anchor is already past for the owner, hands
        // generation to the user (see applyRenewalDate). Kept last so the change is applied as one step.
        $this->applyRenewalDate($nextRenewal, $this->owner->localDateFor($now));
    }

    /**
     * Moves this subscription to a different payment source, recording the change in the audit trail as
     * an Update event. A move to the source it already carries is a no-op. Obligation is unaffected, so
     * this deliberately does not announce a SubscriptionsChanged event (see ADR-0010).
     */
    public function reassignPaymentSource(?PaymentSource $paymentSource): void
    {
        $generator = new ChangeContextGenerator(
            changes: [
                new Change(field: 'paymentSource', current: $this->paymentSource instanceof PaymentSource ? $this->paymentSource->name : 'Unassigned', new: $paymentSource instanceof PaymentSource ? $paymentSource->name : 'Unassigned'),
            ]
        );

        $context = $generator->buildContext();

        if ([] === $context) {
            return;
        }

        $this->subscriptionEvents->add(
            new SubscriptionEvent(
                subscription: $this,
                type: SubscriptionEventType::Update,
                context: $context,
            )
        );

        $this->paymentSource = $paymentSource;
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
     * Resumes automated generation, anchored to a future renewal. The anchor must be after the owner's
     * local today (`$now` is the current instant, judged in the owner's zone) so resuming never triggers
     * an immediate catch-up generation on the next scheduler run.
     */
    public function automatePayments(CalendarDate $nextRenewal, \DateTimeImmutable $now): void
    {
        $today = $this->owner->localDateFor($now);

        Assertion::true(
            $nextRenewal->isAfter($today),
            'Automated payments require a renewal date in the future',
        );

        // The anchor is future, so applyRenewalDate sets the date + renewalDay without forcing Manual;
        // flipping generation back to Automated is this method's own explicit intent.
        $this->applyRenewalDate($nextRenewal, $today);
        $this->paymentGeneration = PaymentGeneration::Automated;
    }

    /**
     * A sensible future anchor for resuming automated generation: the configured cadence projected
     * forward from the current anchor until it lands strictly after today. A renewal already in the
     * future is returned unchanged. Projected by multiples so a run of overdue months lands back on
     * renewalDay rather than drifting.
     */
    public function suggestedResumeRenewal(\DateTimeImmutable $now): CalendarDate
    {
        $today = $this->owner->localDateFor($now);

        $steps = 0;
        while ($this->advance($this->nextRenewal, $steps)->isOnOrBefore($today)) {
            ++$steps;
        }

        return $this->advance($this->nextRenewal, $steps);
    }

    /**
     * Sets the next renewal and derives `renewalDay` from it, in one place. When the anchor is already
     * before the owner's local `$today`, generation is forced to Manual: a past anchor means the user is
     * backfilling or correcting, and the scheduler must not fire a catch-up run against it (ADR-0008).
     * The one un-bypassable seam every renewal-date change routes through (constructor, update,
     * automatePayments).
     */
    private function applyRenewalDate(CalendarDate $nextRenewal, CalendarDate $today): void
    {
        $this->nextRenewal = $nextRenewal;
        $this->renewalDay = $nextRenewal->day;

        if ($nextRenewal->isBefore($today)) {
            $this->paymentGeneration = PaymentGeneration::Manual;
        }
    }
}
