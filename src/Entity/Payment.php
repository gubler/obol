<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentType;
use App\Repository\PaymentRepository;
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

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'payments')]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Subscription $subscription,
        #[ORM\Column(enumType: PaymentType::class)]
        public private(set) PaymentType $type,
        Money $amount,
        #[ORM\Column]
        public private(set) \DateTimeImmutable $paidDate = new \DateTimeImmutable(),
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
    }

    /**
     * Confirm or correct a payment: set the amount and paid date the user asserts,
     * and mark it Verified. Used for validating, adjusting, or fixing a typo on a payment.
     */
    public function amend(int $amount, \DateTimeImmutable $paidDate): void
    {
        Assertion::greaterThan(value: $amount, limit: 0, message: 'Payment amount must be greater than zero');

        // An amended amount is a minor-unit figure in the payment's existing currency.
        $this->amount = new Money($amount, $this->amount->currency);
        $this->paidDate = $paidDate;
        $this->type = PaymentType::Verified;
    }
}
