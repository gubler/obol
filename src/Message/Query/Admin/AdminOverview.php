<?php

// ABOUTME: Read model for the admin Overview - the system metrics shown on the hub landing.
// ABOUTME: Returned by GetAdminOverviewRunner; a plain read projection, not an entity.

declare(strict_types=1);

namespace App\Message\Query\Admin;

final readonly class AdminOverview
{
    public function __construct(
        public int $totalAccounts,
        public int $onboardedAccounts,
        public int $recentSignups,
        public int $recentSignupDays,
        public int $activeSubscriptions,
        public bool $publicSignupEnabled,
    ) {
    }

    /**
     * Accounts that have not finished first-run onboarding - the complement of the onboarded count, so
     * the two always sum to the total without a second query.
     */
    public function notOnboardedAccounts(): int
    {
        return $this->totalAccounts - $this->onboardedAccounts;
    }
}
