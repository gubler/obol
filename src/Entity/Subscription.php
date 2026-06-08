<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
use App\Enum\TileColor;
use App\Lib\ChangeContextGenerator\Change;
use App\Lib\ChangeContextGenerator\ChangeContextGenerator;
use App\Repository\SubscriptionRepository;
use Assert\Assertion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

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

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'subscription', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    #[ORM\Column]
    public private(set) int $cost;

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
        int $cost,
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
     * The subscription's cost normalized to a one-month equivalent, in the currency's minor units
     * (rounded to the nearest whole cent). Weekly cadences use 52 weeks per year.
     */
    public function monthlyCost(): int
    {
        $monthsPerPeriod = match ($this->paymentPeriod) {
            PaymentPeriod::Year => 12.0,
            PaymentPeriod::Month => 1.0,
            PaymentPeriod::Week => 12.0 / 52.0,
        };

        return (int) round($this->cost / ($this->paymentPeriodCount * $monthsPerPeriod));
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
    public function savingsTarget(\DateTimeImmutable $asOf): int
    {
        // Weekly bills renew several times within an allocation month, which the by-month model
        // cannot prorate; until by-week proration lands (#120) a weekly bill is treated as one
        // payment in hand.
        if (PaymentPeriod::Week === $this->paymentPeriod) {
            return $this->cost;
        }

        $monthlyCost = $this->monthlyCost();
        $asOfMonth = $this->monthOrdinal($asOf);

        $renewal = $this->nextRenewal;
        $total = 0;

        // Sum what should be set aside for each upcoming renewal. In practice no more than two ever
        // overlap (the funded-but-unpaid one and the next cycle just begun), so this settles in a
        // couple of iterations; the horizon is only a backstop against degenerate sub-cent chunks.
        for ($i = 0; $i < self::SAVINGS_HORIZON; ++$i) {
            $fundByMonth = $this->monthOrdinal($renewal) - 1; // first of the month before it falls due
            $monthsToFund = max(0, $fundByMonth - $asOfMonth);
            $funded = max(0, $this->cost - $monthlyCost * $monthsToFund);

            if (0 === $funded) {
                break;
            }

            $total += $funded;
            $renewal = $renewal->add($this->renewalInterval());
        }

        return $total;
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
        $this->payments->add(
            new Payment(
                subscription: $this,
                type: $paymentType,
                amount: $amount ?? $this->cost,
                paidDate: $paidDate,
            )
        );
        $this->nextRenewal = $this->nextRenewal->add($this->renewalInterval());
    }

    public function removePayment(Payment $payment): void
    {
        $this->payments->removeElement($payment);
        $this->nextRenewal = $this->nextRenewal->sub($this->renewalInterval());
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
        int $cost,
        TileColor $color,
    ): void {
        $name = $this->normalizeAndAssert(name: $name, cost: $cost, paymentPeriodCount: $paymentPeriodCount);

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
                new Change(field: 'cost', current: $this->cost, new: $cost),
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
    private function normalizeAndAssert(string $name, int $cost, int $paymentPeriodCount): string
    {
        $name = trim(string: $name);
        Assertion::notEq(value1: $name, value2: '', message: 'Subscription name cannot be empty');
        Assertion::greaterThan(value: $cost, limit: 0, message: 'Subscription cost must be greater than zero');
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
}
