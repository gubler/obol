<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategoryRepository;
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

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'category')]
    public private(set) Collection $subscriptions;

    public function __construct(
        #[ORM\Column(length: 255)]
        public private(set) string $name,
    ) {
        $this->id = new Ulid();
        $this->subscriptions = new ArrayCollection();
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
