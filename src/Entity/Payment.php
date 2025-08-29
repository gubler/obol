<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'payments')]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Subscription $subscription,
        #[ORM\Column]
        public private(set) bool $assumed,
        #[ORM\Column]
        public private(set) \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        $this->id = new Ulid();
    }
}
