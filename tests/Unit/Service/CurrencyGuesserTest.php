<?php

// ABOUTME: Unit test for CurrencyGuesser - maps a request's Accept-Language region to a display currency.
// ABOUTME: Falls back to USD for missing/unmappable regions and regions whose currency Obol does not support.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\Currency;
use App\Service\CurrencyGuesser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CurrencyGuesserTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('provideGuessesTheDisplayCurrencyFromTheAcceptLanguageHeaderCases')]
    public function testGuessesTheDisplayCurrencyFromTheAcceptLanguageHeader(string $header, Currency $expected): void
    {
        $request = Request::create('/', Request::METHOD_GET, server: ['HTTP_ACCEPT_LANGUAGE' => $header]);

        self::assertSame($expected, new CurrencyGuesser()->guessFrom($request));
    }

    /**
     * @return iterable<string, array{string, Currency}>
     */
    public static function provideGuessesTheDisplayCurrencyFromTheAcceptLanguageHeaderCases(): iterable
    {
        yield 'German (Germany) -> EUR' => ['de-DE,de;q=0.9', Currency::EUR];
        yield 'English (UK) -> GBP' => ['en-GB', Currency::GBP];
        yield 'Japanese (Japan) -> JPY' => ['ja-JP', Currency::JPY];
        yield 'English (US) -> USD' => ['en-US', Currency::USD];
        yield 'Swedish (Sweden) -> SEK' => ['sv-SE', Currency::SEK];
        // Region present but its currency (KES) is not in Obol's exchange list -> fall back.
        yield 'English (Kenya) -> USD fallback' => ['en-KE', Currency::USD];
        // Language only, no region -> fall back.
        yield 'Language without region -> USD fallback' => ['fr', Currency::USD];
    }

    public function testFallsBackToUsdWhenNoAcceptLanguageIsSent(): void
    {
        self::assertSame(Currency::USD, new CurrencyGuesser()->guessFrom(Request::create('/')));
    }
}
