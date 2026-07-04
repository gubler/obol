<?php

// ABOUTME: Doctrine entity for a payment source (method of payment) optionally attached to subscriptions.
// ABOUTME: A named, color-tagged source with an optional free-text comment; read-only from outside via update().

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TileColor;
use App\Repository\PaymentSourceRepository;
use Assert\Assertion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PaymentSourceRepository::class)]
class PaymentSource
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    #[ORM\Column(length: 255)]
    public private(set) string $name;

    #[ORM\Column(enumType: TileColor::class)]
    public private(set) TileColor $color;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'paymentSource')]
    public private(set) Collection $subscriptions;

    public function __construct(
        /**
         * The user this payment source belongs to. Immutable: a source is never reassigned between users,
         * so a subscription and its payment source always share one owner (see ADR-0015).
         */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'owner_user_id', nullable: false)]
        public private(set) User $owner,
        string $name,
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $comment = '',
        ?TileColor $color = null,
    ) {
        $this->id = new Ulid();
        $this->subscriptions = new ArrayCollection();

        $name = trim(string: $name);
        Assertion::notEq(value1: $name, value2: '');
        $this->name = $name;
        // A payment source always carries a color; pick a random swatch when one is not supplied.
        $this->color = $color ?? TileColor::random();
    }

    /**
     * Updates any combination of the name, comment, and color; a null argument leaves that field
     * unchanged. At least one field must be provided.
     */
    public function update(?string $name = null, ?string $comment = null, ?TileColor $color = null): void
    {
        Assertion::true(
            null !== $name || null !== $comment || $color instanceof TileColor,
            'At least one field must be provided to update a payment source.',
        );

        if (null !== $name) {
            $name = trim(string: $name);
            Assertion::notEq(value1: $name, value2: '');
            $this->name = $name;
        }

        if (null !== $comment) {
            $this->comment = $comment;
        }

        if ($color instanceof TileColor) {
            $this->color = $color;
        }
    }
}
