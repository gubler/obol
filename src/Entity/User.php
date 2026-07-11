<?php

// ABOUTME: Doctrine entity for a passwordless user account - the security identity behind the magic-link firewall.
// ABOUTME: Carries a denormalized primary email (session identity); addresses live as UserEmail rows.

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\CitextType;
use App\Enum\Currency;
use App\Enum\DateFormat;
use App\Enum\SavingsDisplay;
use App\Repository\UserRepository;
use Assert\Assertion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

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

    /**
     * The stable, opaque WebAuthn user handle. Passkeys bind to this rather than to the email (which
     * can change) so a primary-email swap never orphans a credential. Generated once at construction.
     */
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    public private(set) Uuid $userHandle;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    /** @var Collection<int, UserEmail> */
    #[ORM\OneToMany(targetEntity: UserEmail::class, mappedBy: 'user', cascade: ['persist'])]
    public private(set) Collection $emails;

    /**
     * How the account refers to itself in the UI. Seeded to the email at construction so a User is
     * never nameless, then replaced by the answer to "What should we call you?" during onboarding.
     */
    #[ORM\Column]
    public private(set) string $displayName;

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
        // Null until resolved from the browser (or set by the user). The locale drives translation
        // (falling back to the en catalog) and money/number formatting; see UserLocaleListener.
        #[ORM\Column(nullable: true)]
        public private(set) ?string $locale = null,
        #[ORM\Column]
        public private(set) string $timezone = 'America/New_York',
        // How this user reads dates: a locale-aware Long/Medium/Short style or fixed ISO (see DateFormat).
        #[ORM\Column(enumType: DateFormat::class)]
        public private(set) DateFormat $dateFormat = DateFormat::Medium,
        // How this user wants savings targets shown: funded by the due month, a month ahead, or hidden.
        // Defaults to month-of, the way most people budget (see ADR-0009).
        #[ORM\Column(enumType: SavingsDisplay::class)]
        public private(set) SavingsDisplay $savingsDisplay = SavingsDisplay::MonthOf,
        /**
         * When first-run onboarding was completed; null until then. The onboarding gate keys off this.
         * Normally stamped only by completeOnboarding(); the constructor param lets seeding/fixtures mark
         * an account already onboarded (mirrors $createdAt), the way the founder backfill does in SQL.
         */
        #[ORM\Column(nullable: true)]
        public private(set) ?\DateTimeImmutable $onboardingCompletedAt = null,
    ) {
        $this->id = new Ulid();
        $this->userHandle = Uuid::v4();
        $this->emails = new ArrayCollection();
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();

        $email = trim($email);
        Assertion::notEq($email, '', 'User email must not be empty.');
        // The session identifier: a denormalized cache of the primary address.
        $this->email = $email;
        // Never nameless: seed the display name to the email until onboarding replaces it.
        $this->displayName = $this->email;

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
     * Re-express an instant in this user's timezone, leaving the instant itself unchanged. The single
     * seam for resolving "the user's local now/today": callers pass the application clock's instant and
     * read the wall-clock date off the result. A `nextRenewal` is a local date, so its zone is the
     * owner's zone applied here, never a stored offset (see ADR-0016).
     */
    public function toLocal(\DateTimeImmutable $instant): \DateTimeImmutable
    {
        return $instant->setTimezone(new \DateTimeZone($this->timezone));
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboardingCompletedAt instanceof \DateTimeImmutable;
    }

    /**
     * Adopt a locale (a BCP-47 tag) inferred from the browser, replacing the unresolved null. This is
     * strictly the one-time initial resolution: it refuses to run once a locale is set, so it can never
     * silently overwrite a value. Changing an already-resolved locale is the account-settings picker's
     * job (a later slice), which will carry its own intention-revealing mutation.
     */
    public function resolveLocale(string $locale): void
    {
        Assertion::null($this->locale, 'Locale is already resolved.');

        $this->locale = $locale;
    }

    /**
     * Confirm the first-run settings in one coherent, un-bypassable mutation and mark onboarding done.
     * The name is optional: a blank answer keeps the email default seeded at construction, so the
     * account is never nameless. Locale is deliberately untouched here - capturing a language and date
     * format is deferred until per-user locale application lands.
     */
    public function completeOnboarding(
        ?string $displayName,
        Currency $displayCurrency,
        string $timezone,
        ?\DateTimeImmutable $at = null,
    ): void {
        Assertion::null($this->onboardingCompletedAt, 'Onboarding is already complete.');

        $name = trim($displayName ?? '');
        if ('' !== $name) {
            $this->displayName = $name;
        }

        $this->displayCurrency = $displayCurrency;
        $this->timezone = $timezone;
        $this->onboardingCompletedAt = $at ?? new \DateTimeImmutable();
    }

    /**
     * Change the display name from the account settings hub. A blank answer reverts to the
     * primary-email default seeded at construction, so the account is never nameless. Distinct from
     * completeOnboarding(), which captures the name once; this edit runs any time afterwards.
     */
    public function changeDisplayName(?string $displayName): void
    {
        $name = trim($displayName ?? '');
        $this->displayName = '' !== $name ? $name : $this->email;
    }

    /**
     * Update the formatting/locale preferences in one coherent mutation from the account settings hub.
     * Unlike resolveLocale() (the one-shot initial inference, which refuses to overwrite), this is the
     * picker that deliberately changes an already-resolved locale.
     */
    public function changePreferences(
        Currency $displayCurrency,
        string $timezone,
        string $locale,
        DateFormat $dateFormat,
        SavingsDisplay $savingsDisplay,
    ): void {
        $this->displayCurrency = $displayCurrency;
        $this->timezone = $timezone;
        $this->locale = $locale;
        $this->dateFormat = $dateFormat;
        $this->savingsDisplay = $savingsDisplay;
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
