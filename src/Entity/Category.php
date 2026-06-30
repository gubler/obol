<?php

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

    public function setName(string $name): void
    {
        $name = trim(string: $name);
        Assertion::notEq(value1: $name, value2: '');
        $this->name = $name;
    }

    public function update(string $name, TileColor $color, CategoryIcon $icon): void
    {
        $this->setName($name);
        $this->color = $color;
        $this->icon = $icon;
    }
}
