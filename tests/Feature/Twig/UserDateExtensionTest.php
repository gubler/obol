<?php

// ABOUTME: Feature test for the user_date Twig filter - renders dates per the user's DateFormat preference.
// ABOUTME: An explicit pattern ignores locale; LocaleDefault follows the locale's own date order.

declare(strict_types=1);

namespace App\Tests\Feature\Twig;

use App\Enum\DateFormat;
use App\Factory\SubscriptionEventFactory;
use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use App\ValueObject\CalendarDate;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class UserDateExtensionTest extends WebTestCase
{
    public function testAnExplicitDateFormatIsAppliedRegardlessOfLocale(): void
    {
        // A British user (whose locale renders dates day-first) with the ISO style still sees ISO -
        // the fixed pattern fixes the order independent of locale.
        $client = self::createClient();
        $user = UserFactory::createOne(['dateFormat' => DateFormat::Iso, 'locale' => 'en-GB']);
        SubscriptionFactory::createOne(['owner' => $user, 'nextRenewal' => CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('2027-03-09'), new \DateTimeZone('UTC'))]);

        $client->loginUser($user);
        $client->request(method: Request::METHOD_GET, uri: '/app?view=list');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('2027-03-09', (string) $client->getResponse()->getContent());
    }

    public function testALocaleAwareStyleFollowsTheUsersLocaleDateOrder(): void
    {
        // Medium defers to the locale: a British user reads the medium form day-first ("9 Mar 2027"),
        // not the American month-first form, proving the ambient locale drives it.
        $client = self::createClient();
        $user = UserFactory::createOne(['dateFormat' => DateFormat::Medium, 'locale' => 'en-GB']);
        SubscriptionFactory::createOne(['owner' => $user, 'nextRenewal' => CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('2027-03-09'), new \DateTimeZone('UTC'))]);

        $client->loginUser($user);
        $client->request(method: Request::METHOD_GET, uri: '/app?view=list');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('9 Mar 2027', $content);
        self::assertStringNotContainsString('Mar 9, 2027', $content);
        self::assertStringNotContainsString('2027-03-09', $content);
    }

    public function testTheAuditLogTimestampFollowsTheLocaleHourCycle(): void
    {
        // An American on a locale-aware style reads the history time on a 12-hour clock (AM/PM).
        $client = self::createClient();
        $user = UserFactory::createOne(['dateFormat' => DateFormat::Medium, 'locale' => 'en-US']);
        $subscription = SubscriptionFactory::createOne(['owner' => $user]);
        SubscriptionEventFactory::new()->update()->create([
            'subscription' => $subscription,
            'createdAt' => new \DateTimeImmutable('2024-02-15 14:30'),
        ]);

        $client->loginUser($user);
        $client->request(method: Request::METHOD_GET, uri: '/app/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Feb 15, 2024', $content);
        self::assertStringContainsString('2:30', $content);
        self::assertStringContainsString('PM', $content);
    }

    public function testTheAuditLogTimestampIsFixed24HourForTheIsoStyle(): void
    {
        // The ISO style pins a 24-hour timestamp regardless of locale.
        $client = self::createClient();
        $user = UserFactory::createOne(['dateFormat' => DateFormat::Iso, 'locale' => 'en-US']);
        $subscription = SubscriptionFactory::createOne(['owner' => $user]);
        SubscriptionEventFactory::new()->update()->create([
            'subscription' => $subscription,
            'createdAt' => new \DateTimeImmutable('2024-02-15 14:30'),
        ]);

        $client->loginUser($user);
        $client->request(method: Request::METHOD_GET, uri: '/app/subscriptions/' . $subscription->id);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('2024-02-15 14:30', (string) $client->getResponse()->getContent());
    }
}
