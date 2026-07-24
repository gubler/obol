<?php

// ABOUTME: Feature tests for the admin Overview - the at-a-glance system metrics on the hub landing.
// ABOUTME: Seeds a known delta and asserts each metric moves by it, so the seeded baseline is irrelevant.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Admin;

use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use App\Lib\Bus\CommandBus;
use App\Message\Command\System\SetPublicSignupCommand;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminOverviewTest extends WebTestCase
{
    public function testEachMetricCountsTheSeededDelta(): void
    {
        // The test database keeps a seeded account, so the metrics are asserted as deltas: snapshot the
        // counts, seed a known population, and check each metric moved by exactly what that population adds.
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $before = $this->metrics($client);

        // Two onboarded recent accounts, one not-onboarded recent account, one onboarded account that
        // signed up outside the recent window (so it lifts the totals but not "recent signups").
        UserFactory::createMany(2);
        UserFactory::new()->notOnboarded()->create();
        UserFactory::createOne(['createdAt' => new \DateTimeImmutable('-10 days')]);

        // Three active subscriptions and one archived (the archived one must not lift the active count).
        SubscriptionFactory::createMany(3);
        SubscriptionFactory::new()->archived()->create();

        $after = $this->metrics($client);

        self::assertSame(4, $after['total'] - $before['total'], 'total accounts');
        self::assertSame(3, $after['onboarded'] - $before['onboarded'], 'onboarded');
        self::assertSame(1, $after['not_onboarded'] - $before['not_onboarded'], 'not onboarded');
        self::assertSame(3, $after['recent'] - $before['recent'], 'recent signups (last 10-day account excluded)');
        self::assertSame(3, $after['active'] - $before['active'], 'active subscriptions (archived excluded)');
    }

    public function testThePublicSignupMetricReflectsTheSetting(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        // Seeded default is off.
        $crawler = $client->request(Request::METHOD_GET, '/app/admin');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Off', $crawler->filter('[data-test="metric-public-signup"]')->text());

        self::getContainer()->get(CommandBus::class)->dispatch(new SetPublicSignupCommand(enabled: true));

        $crawler = $client->request(Request::METHOD_GET, '/app/admin');
        self::assertStringContainsString('On', $crawler->filter('[data-test="metric-public-signup"]')->text());
    }

    public function testARegularUserCannotSeeTheOverview(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/app/admin');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * The Overview's numeric metrics, keyed by a short name, read from a fresh render of the hub landing.
     *
     * @return array{total: int, onboarded: int, not_onboarded: int, recent: int, active: int}
     */
    private function metrics(KernelBrowser $client): array
    {
        $crawler = $client->request(Request::METHOD_GET, '/app/admin');
        self::assertResponseIsSuccessful();

        $value = static fn (string $test): int => (int) trim($crawler->filter('[data-test="' . $test . '"]')->text());

        return [
            'total' => $value('metric-total-accounts'),
            'onboarded' => $value('metric-onboarded'),
            'not_onboarded' => $value('metric-not-onboarded'),
            'recent' => $value('metric-recent-signups'),
            'active' => $value('metric-active-subscriptions'),
        ];
    }
}
