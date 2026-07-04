<?php

// ABOUTME: Doctrine entity for a passwordless user account - the security identity behind the magic-link firewall.
// ABOUTME: Carries a denormalized primary email (session identity); addresses live as UserEmail rows.

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\CitextType;
use App\Enum\Currency;
use App\Repository\UserRepository;
use Assert\Assertion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    #[ORM\Column(type: CitextType::NAME)]
    public private(set) string $email;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    /** @var Collection<int, UserEmail> */
    #[ORM\OneToMany(targetEntity: UserEmail::class, mappedBy: 'user', cascade: ['persist'])]
    public private(set) Collection $emails;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        string $email,
        #[ORM\Column(type: Types::JSON)]
        public private(set) array $roles = [],
        ?\DateTimeImmutable $createdAt = null,
        #[ORM\Column(enumType: Currency::class)]
        public private(set) Currency $displayCurrency = Currency::USD,
        #[ORM\Column]
        public private(set) string $locale = 'en-US',
        #[ORM\Column]
        public private(set) string $timezone = 'America/New_York',
    ) {
        $this->id = new Ulid();
        $this->emails = new ArrayCollection();
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();

        $email = trim($email);
        Assertion::notEq($email, '', 'User email must not be empty.');
        // The session identifier: a denormalized cache of the primary address.
        $this->email = $email;

        // A User is never valid without its primary address, so it creates one here rather than trusting
        // a caller to remember. Verified on creation because control of the address is always established
        // before a User exists at all: the console (app:user:create) is an operator vouching for it, and
        // the eventual public signup flow only runs after the sign-up link is clicked. The firewall lookup
        // (UserEmailRepository::findVerifiedByEmail) reads the persisted user_email table, not this
        // in-memory collection.
        //
        // Not a dead statement: UserEmail's constructor calls $user->addEmail($this), so the new row
        // mounts itself onto $this->emails and is cascade-persisted with the User.
        new UserEmail(user: $this, email: $this->email, isPrimary: true, verifiedAt: $this->createdAt);
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique(['ROLE_USER', ...$this->roles]));
    }

    public function getUserIdentifier(): string
    {
        // Guaranteed non-empty by the constructor + the primary-email cache; assert so the
        // non-empty-string contract on UserInterface::getUserIdentifier() is satisfied.
        \assert('' !== $this->email);

        return $this->email;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $this->id->equals($user->id)
            && $this->email === $user->email
            && $this->getRoles() === $user->getRoles();
    }

    /**
     * Adopt the primary UserEmail's address as the denormalized cache used for session identity.
     * Called after a primary swap (a later slice); a no-op when no primary row is loaded.
     */
    public function syncPrimaryEmailCache(): void
    {
        foreach ($this->emails as $email) {
            if ($email->isPrimary) {
                $this->email = $email->email;

                return;
            }
        }
    }

    /**
     * Register a UserEmail on this user's in-memory collection. Called by UserEmail's constructor so the
     * object graph is consistent before flush (Doctrine owns the foreign key on the UserEmail side).
     */
    public function addEmail(UserEmail $email): void
    {
        if (!$this->emails->contains($email)) {
            $this->emails->add($email);
        }
    }
}
