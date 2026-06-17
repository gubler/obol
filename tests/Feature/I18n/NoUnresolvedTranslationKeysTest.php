<?php

// ABOUTME: The i18n tripwire - crawls the param-less pages and fails if any unresolved translation
// ABOUTME: key leaks into the rendered markup (ADR-0012). The red-green driver for the i18n epic.

declare(strict_types=1);

namespace App\Tests\Feature\I18n;

use App\Tests\Support\TranslationAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NoUnresolvedTranslationKeysTest extends WebTestCase
{
    use TranslationAssertions;

    #[DataProvider('provideParamlessPagesAreKeyCleanCases')]
    public function testParamlessPagesAreKeyClean(string $route): void
    {
        $client = self::createClient();

        $url = self::getContainer()->get('router')->generate($route);

        $client->request(method: 'GET', uri: $url);

        self::assertResponseIsSuccessful();
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), $route);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideParamlessPagesAreKeyCleanCases(): iterable
    {
        yield 'subscriptions index' => ['subscription_index'];
        yield 'new subscription' => ['subscription_new'];
        yield 'categories index' => ['category_index'];
        yield 'new category' => ['category_new'];
        yield 'reports index' => ['reports_index'];
        yield 'uncategorized report' => ['reports_uncategorized'];
    }

    public function testTripwireFlagsALeakedKey(): void
    {
        try {
            self::assertNoTranslationKeyLeaks('<p>subscription.flash.created</p>');
            self::fail('expected the tripwire to flag a leaked translation key');
        } catch (ExpectationFailedException) {
            self::addToAssertionCount(1);
        }
    }
}
