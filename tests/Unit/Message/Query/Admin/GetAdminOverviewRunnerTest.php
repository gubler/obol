<?php

// ABOUTME: Unit tests for GetAdminOverviewRunner - assembles the admin Overview metrics from the repos.
// ABOUTME: A MockClock fixes "now" so the recent-signups window (now minus 7 days) is asserted exactly.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Admin;

use App\Entity\SystemSettings;
use App\Message\Query\Admin\AdminOverview;
use App\Message\Query\Admin\GetAdminOverviewQuery;
use App\Message\Query\Admin\GetAdminOverviewRunner;
use App\Repository\SubscriptionRepository;
use App\Repository\SystemSettingsRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GetAdminOverviewRunnerTest extends TestCase
{
    public function testItAssemblesEveryMetricFromTheRepositories(): void
    {
        $users = self::createStub(UserRepository::class);
        $users->method('countAll')->willReturn(12);
        $users->method('countOnboarded')->willReturn(9);
        $users->method('countCreatedSince')->willReturn(4);

        $subscriptions = self::createStub(SubscriptionRepository::class);
        $subscriptions->method('countActive')->willReturn(37);

        $settings = self::createStub(SystemSettingsRepository::class);
        $settings->method('get')->willReturn(new SystemSettings(publicSignupEnabled: true));

        $overview = $this->invokeRunner($users, $subscriptions, $settings, '2026-07-24T10:00:00');

        self::assertSame(12, $overview->totalAccounts);
        self::assertSame(9, $overview->onboardedAccounts);
        self::assertSame(3, $overview->notOnboardedAccounts());
        self::assertSame(4, $overview->recentSignups);
        self::assertSame(7, $overview->recentSignupDays);
        self::assertSame(37, $overview->activeSubscriptions);
        self::assertTrue($overview->publicSignupEnabled);
    }

    public function testTheRecentWindowIsSevenDaysBeforeNow(): void
    {
        $users = self::createMock(UserRepository::class);
        $users->method('countAll')->willReturn(0);
        $users->method('countOnboarded')->willReturn(0);
        $subscriptions = self::createStub(SubscriptionRepository::class);
        $subscriptions->method('countActive')->willReturn(0);
        $settings = self::createStub(SystemSettingsRepository::class);
        $settings->method('get')->willReturn(new SystemSettings());

        // The runner asks the repository for signups since exactly seven days before "now".
        $users->expects(self::once())
            ->method('countCreatedSince')
            ->with(self::callback(static fn (\DateTimeImmutable $since): bool => '2026-07-17 10:00:00' === $since->format('Y-m-d H:i:s')))
            ->willReturn(0)
        ;

        $this->invokeRunner($users, $subscriptions, $settings, '2026-07-24T10:00:00');
    }

    private function invokeRunner(
        UserRepository $users,
        SubscriptionRepository $subscriptions,
        SystemSettingsRepository $settings,
        string $now,
    ): AdminOverview {
        $runner = new GetAdminOverviewRunner($users, $subscriptions, $settings, new MockClock($now));

        return $runner(new GetAdminOverviewQuery());
    }
}
