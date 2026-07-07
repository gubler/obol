<?php

// ABOUTME: Feature test for the user_date Twig filter - renders dates per the user's DateFormat preference.
// ABOUTME: An explicit pattern ignores locale; LocaleDefault follows the locale's own date order.

declare(strict_types=1);

namespace App\Tests\Feature\Twig;

use App\Enum\DateFormat;
use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class UserDateExtensionTest extends WebTestCase
{
    public function testAnExplicitDateFormatIsAppliedRegardlessOfLocale(): void
    {
        // A British user (whose locale renders dates day-first) with the ISO pattern still sees ISO -
        // the explicit pattern fixes the order independent of locale.
        $client = self::createClient();
        $user = UserFactory::createOne(['dateFormat' => DateFormat::YearMonthDayDash, 'locale' => 'en-GB']);
        SubscriptionFactory::createOne(['owner' => $user, 'nextRenewal' => new \DateTimeImmutable('2027-03-09')]);

        $client->loginUser($user);
        $client->request(method: Request::METHOD_GET, uri: '/?view=list');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('2027-03-09', (string) $client->getResponse()->getContent());
    }

    public function testLocaleDefaultFollowsTheUsersLocaleDateOrder(): void
    {
        // LocaleDefault defers to the locale: a British user reads the medium form day-first ("9 Mar
        // 2027"), not the American month-first form, proving the ambient locale drives it.
        $client = self::createClient();
        $user = UserFactory::createOne(['dateFormat' => DateFormat::LocaleDefault, 'locale' => 'en-GB']);
        SubscriptionFactory::createOne(['owner' => $user, 'nextRenewal' => new \DateTimeImmutable('2027-03-09')]);

        $client->loginUser($user);
        $client->request(method: Request::METHOD_GET, uri: '/?view=list');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('9 Mar 2027', $content);
        self::assertStringNotContainsString('Mar 9, 2027', $content);
        self::assertStringNotContainsString('2027-03-09', $content);
    }
}
