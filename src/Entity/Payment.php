<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentType;
use App\Repository\PaymentRepository;
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

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    public private(set) Subscription $subscription;

    #[ORM\Column(enumType: PaymentType::class)]
    public private(set) PaymentType $type;

    #[ORM\Column]
    public private(set) int $amount;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    public function __construct(
        Subscription $subscription,
        PaymentType $type,
        int $amount,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        Assertion::greaterThan(value: $amount, limit: 0, message: 'Payment amount must be greater than zero');

        $this->id = new Ulid();
        $this->subscription = $subscription;
        $this->type = $type;
        $this->amount = $amount;
        $this->createdAt = $createdAt;
    }
}
