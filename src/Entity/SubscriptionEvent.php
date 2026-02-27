<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SubscriptionEventType;
use App\Repository\SubscriptionEventRepository;
use Assert\Assertion;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SubscriptionEventRepository::class)]
class SubscriptionEvent
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'subscriptionEvents')]
    #[ORM\JoinColumn(nullable: false)]
    public private(set) Subscription $subscription;

    #[ORM\Column(enumType: SubscriptionEventType::class)]
    public private(set) SubscriptionEventType $type;

    /**
     * @var array<string, array<string, float|int|string>>
     */
    #[ORM\Column]
    public private(set) array $context;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    /**
     * @param array<string, array<string, float|int|string>> $context
     */
    public function __construct(
        Subscription $subscription,
        SubscriptionEventType $type,
        array $context,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        if (SubscriptionEventType::Archive === $type || SubscriptionEventType::Unarchive === $type) {
            Assertion::same(value: $context, value2: [], message: 'Archive and Unarchive events must have empty context');
        } else {
            Assertion::notEq(value1: $context, value2: [], message: 'Update and CostChange events must have non-empty context');
        }

        $this->id = new Ulid();
        $this->subscription = $subscription;
        $this->type = $type;
        $this->context = $context;
        $this->createdAt = $createdAt;
    }
}
