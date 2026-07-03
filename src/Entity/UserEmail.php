<?php

// ABOUTME: Doctrine entity for one (user, email) address - independently verified, at most one primary per user.
// ABOUTME: A magic link resolves to its User via any verified row; the primary carries session identity.

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\CitextType;
use App\Repository\UserEmailRepository;
use Assert\Assertion;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserEmailRepository::class)]
#[ORM\Table(name: 'user_email')]
#[ORM\UniqueConstraint(name: 'uniq_user_email_per_user', columns: ['user_id', 'email'])]
// Partial unique indexes (PostgreSQL): exactly one primary per user, and one owner per verified
// address. Declared here so the ORM metadata stays authoritative and migrations:diff does not churn.
#[ORM\UniqueConstraint(name: 'uniq_user_email_primary', columns: ['user_id'], options: ['where' => 'is_primary'])]
#[ORM\UniqueConstraint(name: 'uniq_user_email_verified', columns: ['email'], options: ['where' => '(verified_at IS NOT NULL)'])]
class UserEmail
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    #[ORM\Column(type: CitextType::NAME)]
    public private(set) string $email;

    #[ORM\Column]
    public private(set) bool $isPrimary;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $verifiedAt;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'emails')]
        #[ORM\JoinColumn(nullable: false)]
        public private(set) User $user,
        string $email,
        bool $isPrimary,
        ?\DateTimeImmutable $verifiedAt,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $email = trim($email);
        Assertion::notEq($email, '', 'User email must not be empty.');

        // Invariant: a primary address is always a verified one (enforced again at the DB level by the
        // partial unique index). Rejecting it here keeps in-memory objects honest before flush.
        if ($isPrimary && !$verifiedAt instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('A primary email must be verified.');
        }

        $this->id = new Ulid();
        $this->email = $email;
        $this->isPrimary = $isPrimary;
        $this->verifiedAt = $verifiedAt;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();

        $this->user->addEmail($this);
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt instanceof \DateTimeImmutable;
    }

    public function markVerified(?\DateTimeImmutable $at = null): void
    {
        if (!$this->verifiedAt instanceof \DateTimeImmutable) {
            $this->verifiedAt = $at ?? new \DateTimeImmutable();
        }
    }

    public function markPrimary(): void
    {
        if (!$this->isVerified()) {
            throw new \InvalidArgumentException('Cannot make an unverified email primary.');
        }

        $this->isPrimary = true;
    }

    public function unmarkPrimary(): void
    {
        $this->isPrimary = false;
    }
}
