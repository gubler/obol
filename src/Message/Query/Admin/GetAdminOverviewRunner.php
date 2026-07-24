<?php

// ABOUTME: Runner for GetAdminOverviewQuery - counts accounts, signups, and subscriptions for the Overview.
// ABOUTME: All reads are cross-owner and cheap COUNTs; settings come through SystemSettingsRepository::get().

declare(strict_types=1);

namespace App\Message\Query\Admin;

use App\Repository\SubscriptionRepository;
use App\Repository\SystemSettingsRepository;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: GetAdminOverviewQuery::class)]
final readonly class GetAdminOverviewRunner
{
    // The "recent signups" window. One place defines the number; the read model carries it to the view.
    private const int RECENT_SIGNUP_DAYS = 7;

    public function __construct(
        private UserRepository $users,
        private SubscriptionRepository $subscriptions,
        private SystemSettingsRepository $systemSettings,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetAdminOverviewQuery $query): AdminOverview
    {
        $since = $this->clock->now()->sub(new \DateInterval('P' . self::RECENT_SIGNUP_DAYS . 'D'));

        return new AdminOverview(
            totalAccounts: $this->users->countAll(),
            onboardedAccounts: $this->users->countOnboarded(),
            recentSignups: $this->users->countCreatedSince($since),
            recentSignupDays: self::RECENT_SIGNUP_DAYS,
            activeSubscriptions: $this->subscriptions->countActive(),
            publicSignupEnabled: $this->systemSettings->get()->publicSignupEnabled,
        );
    }
}
