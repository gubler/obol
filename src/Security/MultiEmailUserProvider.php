<?php

// ABOUTME: User provider that resolves any verified UserEmail address (primary or secondary) to its User.
// ABOUTME: Runs at the pre-firewall bootstrap seam, so it reads repositories directly (see ADR-0014).

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserEmailRepository;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<User>
 */
final readonly class MultiEmailUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserEmailRepository $userEmailRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $userEmail = $this->userEmailRepository->findVerifiedByEmail($identifier);

        // Unknown and unverified are indistinguishable here - both look like "no such account" so
        // the login path cannot be used to enumerate registered addresses.
        if (!$userEmail instanceof \App\Entity\UserEmail) {
            throw new UserNotFoundException(\sprintf('No verified email row for "%s".', $identifier));
        }

        return $userEmail->user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Expected %s, got "%s".', User::class, $user::class));
        }

        $refreshed = $this->userRepository->find($user->id);
        if (!$refreshed instanceof User) {
            throw new UserNotFoundException(\sprintf('User "%s" no longer exists.', (string) $user->id));
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, User::class, true);
    }
}
