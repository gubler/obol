<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\SubscriptionEventType;
use App\Repository\SubscriptionRepository;
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
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'subscription', orphanRemoval: true)]
    public private(set) Collection $payments;

    /**
     * @var Collection<int, SubscriptionEvent>
     */
    #[ORM\OneToMany(targetEntity: SubscriptionEvent::class, mappedBy: 'subscription', orphanRemoval: true)]
    public private(set) Collection $subscriptionEvents;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'subscriptions')]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Category $category,
        #[ORM\Column(length: 255)]
        public private(set) string $name,
        #[ORM\Column]
        public private(set) \DateTimeImmutable $lastPaidDate,
        #[ORM\Column(enumType: PaymentPeriod::class)]
        public private(set) PaymentPeriod $paymentPeriod,
        #[ORM\Column]
        public private(set) int $paymentPeriodCount,
        #[ORM\Column]
        public private(set) float $cost,
        #[ORM\Column(enumType: Currency::class)]
        public private(set) Currency $currency,
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $description = '',
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $link = '',
        #[ORM\Column(length: 255)]
        public private(set) string $logo = '',
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->payments = new ArrayCollection();
        $this->subscriptionEvents = new ArrayCollection();
    }

    public function recordPayment(
        \DateTimeImmutable $paidDate,
        bool $assumed,
    ): void {
        $this->lastPaidDate = $paidDate;
        $this->payments->add(
            new Payment(
                subscription: $this,
                assumed: $assumed,
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
        float $cost,
        Currency $currency,
    ): void {
        $costChangeContext = [];
        $updateContext = [];

        if ($this->category !== $category) {
            $updateContext['category']['old'] = $this->category->name;
            $updateContext['category']['new'] = $category->name;
        }

        if ($this->name !== $name) {
            $updateContext['name']['old'] = $this->name;
            $updateContext['name']['new'] = $name;
        }

        if ($this->lastPaidDate !== $lastPaidDate) {
            $updateContext['lastPaidDate']['old'] = $this->lastPaidDate->format('Y-m-d');
            $updateContext['lastPaidDate']['new'] = $lastPaidDate->format('Y-m-d');
        }

        if ($this->description !== $description) {
            $updateContext['description']['old'] = $this->description;
            $updateContext['description']['new'] = $description;
        }

        if ($this->link !== $link) {
            $updateContext['link']['old'] = $this->link;
            $updateContext['link']['new'] = $link;
        }

        if ($this->logo !== $logo) {
            $updateContext['logo']['old'] = $this->logo;
            $updateContext['logo']['new'] = $logo;
        }

        if ($this->paymentPeriod !== $paymentPeriod) {
            $costChangeContext['paymentPeriod']['old'] = $this->paymentPeriod->value;
            $costChangeContext['paymentPeriod']['new'] = $paymentPeriod->value;
        }

        if ($this->paymentPeriodCount !== $paymentPeriodCount) {
            $costChangeContext['paymentPeriodCount']['old'] = $this->paymentPeriodCount;
            $costChangeContext['paymentPeriodCount']['new'] = $paymentPeriodCount;
        }

        if ($this->cost !== $cost) {
            $costChangeContext['cost']['old'] = $this->cost;
            $costChangeContext['cost']['new'] = $cost;
        }

        if ($this->currency !== $currency) {
            $costChangeContext['currency']['old'] = $this->currency->value;
            $costChangeContext['currency']['new'] = $currency->value;
        }

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
