<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
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

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    public private(set) Category $category;

    #[ORM\Column(length: 255)]
    public private(set) string $name;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $lastPaidDate;

    #[ORM\Column(enumType: PaymentPeriod::class)]
    public private(set) PaymentPeriod $paymentPeriod;

    #[ORM\Column]
    public private(set) int $paymentPeriodCount;

    #[ORM\Column]
    public private(set) int $cost;

    #[ORM\Column(type: Types::TEXT)]
    public private(set) string $description;

    #[ORM\Column(type: Types::TEXT)]
    public private(set) string $link;

    #[ORM\Column(length: 255)]
    public private(set) string $logo;

    public function __construct(
        Category $category,
        string $name,
        \DateTimeImmutable $lastPaidDate,
        PaymentPeriod $paymentPeriod,
        int $paymentPeriodCount,
        int $cost,
        string $description = '',
        string $link = '',
        string $logo = '',
    ) {
        $name = trim(string: $name);
        Assertion::notEq(value1: $name, value2: '', message: 'Subscription name cannot be empty');
        Assertion::greaterThan(value: $cost, limit: 0, message: 'Subscription cost must be greater than zero');
        Assertion::greaterThan(value: $paymentPeriodCount, limit: 0, message: 'Payment period count must be greater than zero');

        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->payments = new ArrayCollection();
        $this->subscriptionEvents = new ArrayCollection();

        $this->category = $category;
        $this->name = $name;
        $this->lastPaidDate = $lastPaidDate;
        $this->paymentPeriod = $paymentPeriod;
        $this->paymentPeriodCount = $paymentPeriodCount;
        $this->cost = $cost;
        $this->description = $description;
        $this->link = $link;
        $this->logo = $logo;
    }

    public function recordPayment(
        \DateTimeImmutable $paidDate,
        PaymentType $paymentType,
        ?int $amount = null,
    ): void {
        $this->lastPaidDate = $paidDate;
        $this->payments->add(
            new Payment(
                subscription: $this,
                type: $paymentType,
                amount: $amount ?? $this->cost,
                createdAt: $paidDate,
            )
        );
    }

    public function update(
        Category $category,
        string $name,
        \DateTimeImmutable $lastPaidDate,
        string $description,
        string $link,
        string $logo,
        PaymentPeriod $paymentPeriod,
        int $paymentPeriodCount,
        int $cost,
    ): void {
        $updateGenerator = new ChangeContextGenerator(
            changes: [
                new Change(field: 'category', current: $this->category->name, new: $category->name),
                new Change(field: 'name', current: $this->name, new: $name),
                new Change(field: 'lastPaidDate', current: $this->lastPaidDate->format(format: 'c'), new: $lastPaidDate->format(format: 'c')),
                new Change(field: 'description', current: $this->description, new: $description),
                new Change(field: 'link', current: $this->link, new: $link),
                new Change(field: 'logo', current: $this->logo, new: $logo),
            ]
        );

        $costChangeGenerator = new ChangeContextGenerator(
            changes: [
                new Change(field: 'paymentPeriod', current: $this->paymentPeriod->value, new: $paymentPeriod->value),
                new Change(field: 'paymentPeriodCost', current: $this->paymentPeriodCount, new: $paymentPeriodCount),
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
        $this->lastPaidDate = $lastPaidDate;
        $this->description = $description;
        $this->link = $link;
        $this->logo = $logo;
        $this->paymentPeriod = $paymentPeriod;
        $this->paymentPeriodCount = $paymentPeriodCount;
        $this->cost = $cost;
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
