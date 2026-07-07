<?php

// ABOUTME: Unit test for LocaleGuesser - derives a BCP-47 locale tag from a request's Accept-Language.
// ABOUTME: Any well-formed tag is accepted (native formatting for any region); falls back to en-US.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\LocaleGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class LocaleGuesserTest extends TestCase
{
    #[DataProvider('provideGuessesTheLocaleTagFromTheAcceptLanguageHeaderCases')]
    public function testGuessesTheLocaleTagFromTheAcceptLanguageHeader(string $header, string $expected): void
    {
        $request = Request::create('/', Request::METHOD_GET, server: ['HTTP_ACCEPT_LANGUAGE' => $header]);

        self::assertSame($expected, new LocaleGuesser()->guessFrom($request));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideGuessesTheLocaleTagFromTheAcceptLanguageHeaderCases(): iterable
    {
        yield 'German (Germany)' => ['de-DE,de;q=0.9', 'de-DE'];
        yield 'English (UK)' => ['en-GB', 'en-GB'];
        yield 'Swedish (Sweden)' => ['sv-SE', 'sv-SE'];
        yield 'Japanese (Japan)' => ['ja-JP', 'ja-JP'];
        // A language-only tag is filled to its most likely region via ICU (de -> de-DE, en -> en-US).
        yield 'German (no region)' => ['de', 'de-DE'];
        yield 'French (no region)' => ['fr', 'fr-FR'];
        yield 'English (no region)' => ['en', 'en-US'];
    }

    public function testFallsBackToUsEnglishWhenNoAcceptLanguageIsSent(): void
    {
        self::assertSame('en-US', new LocaleGuesser()->guessFrom(Request::create('/')));
    }
}
