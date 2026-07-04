<?php

// ABOUTME: Doctrine entity for a category grouping subscriptions; a named, colored, icon-tagged bucket.
// ABOUTME: Owned by one user (immutable owner FK); read-only from outside via update().

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use App\Repository\CategoryRepository;
use Assert\Assertion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
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
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'category')]
    public private(set) Collection $subscriptions;

    public function __construct(
        /**
         * The user this category belongs to. Immutable: a category is never reassigned between users,
         * so a subscription and its category always share one owner (see ADR-0015).
         */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'owner_user_id', nullable: false)]
        public private(set) User $owner,
        string $name,
        ?TileColor $color = null,
        #[ORM\Column(enumType: CategoryIcon::class)]
        public private(set) CategoryIcon $icon = CategoryIcon::Tag,
    ) {
        $this->id = new Ulid();
        $this->subscriptions = new ArrayCollection();

        $name = trim(string: $name);
        Assertion::notEq(value1: $name, value2: '');
        $this->name = $name;
        // A category always carries a color; pick a random swatch when one is not supplied.
        $this->color = $color ?? TileColor::random();
    }

    /**
     * Updates any combination of the name, color, and icon; a null argument leaves that field
     * unchanged. At least one field must be provided.
     */
    public function update(?string $name = null, ?TileColor $color = null, ?CategoryIcon $icon = null): void
    {
        Assertion::true(
            null !== $name || $color instanceof TileColor || $icon instanceof CategoryIcon,
            'At least one field must be provided to update a category.',
        );

        if (null !== $name) {
            $name = trim(string: $name);
            Assertion::notEq(value1: $name, value2: '');
            $this->name = $name;
        }

        if ($color instanceof TileColor) {
            $this->color = $color;
        }

        if ($icon instanceof CategoryIcon) {
            $this->icon = $icon;
        }
    }
}
