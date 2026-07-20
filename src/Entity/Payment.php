<?php

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\CalendarDateType;
use App\Enum\PaymentType;
use App\Repository\PaymentRepository;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use Assert\Assertion;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]
    public private(set) Money $amount;

    /**
     * The user this payment belongs to. Denormalized from the subscription so a user's payments can be
     * queried without joining through Subscription. Derived in the constructor (never a parameter), so
     * the invariant owner == subscription.owner cannot be violated by a caller (see ADR-0015).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'owner_user_id', nullable: false)]
    public private(set) User $owner;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'payments')]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Subscription $subscription,
        #[ORM\Column(enumType: PaymentType::class)]
        public private(set) PaymentType $type,
        Money $amount,
        // The calendar date the charge fell on (the owner's local billing day), never an instant.
        #[ORM\Column(type: CalendarDateType::NAME)]
        public private(set) CalendarDate $paidDate,
        // The instant this row was written, separating same-day payments and ordering the audit trail.
        #[ORM\Column]
        public private(set) \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        // True when recording this payment advanced the subscription's renewal anchor, so removing
        // it rolls the anchor back. A historical backfill payment leaves the anchor (and this) alone.
        #[ORM\Column]
        public private(set) bool $advancedRenewal = false,
    ) {
        Assertion::greaterThan(value: $amount->minorAmount, limit: 0, message: 'Payment amount must be greater than zero');

        $this->id = new Ulid();
        $this->amount = $amount;
        $this->owner = $subscription->owner;
    }

    /**
     * Confirm or correct a payment: set the amount and paid date the user asserts,
     * and mark it Verified. Used for validating, adjusting, or fixing a typo on a payment.
     */
    public function amend(int $amount, CalendarDate $paidDate): void
    {
        Assertion::greaterThan(value: $amount, limit: 0, message: 'Payment amount must be greater than zero');

        // An amended amount is a minor-unit figure in the payment's existing currency.
        $this->amount = new Money($amount, $this->amount->currency);
        $this->paidDate = $paidDate;
        $this->type = PaymentType::Verified;
    }
}
