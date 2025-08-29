<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SubscriptionEventType;
use App\Repository\SubscriptionEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SubscriptionEventRepository::class)]
class SubscriptionEvent
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    /**
     * @param array<string, array<string, float|int|string>> $context
     */
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'subscriptionEvents')]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) Subscription $subscription,
        #[ORM\Column(enumType: SubscriptionEventType::class)]
        public private(set) SubscriptionEventType $type,
        #[ORM\Column]
        public private(set) array $context,
        #[ORM\Column]
        public private(set) \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        $this->id = new Ulid();
    }
}
