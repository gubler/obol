<?php

// ABOUTME: Test trait that scans rendered HTML for unresolved translation keys.
// ABOUTME: A missing or un-trans'd key renders as its literal id; this is the i18n tripwire (ADR-0012).

declare(strict_types=1);

namespace App\Tests\Support;

trait TranslationAssertions
{
    /**
     * Reserved key namespaces. A rendered page must never contain one of these followed by a dotted
     * suffix - that is an unresolved (missing or un-trans'd) translation key surfacing as text.
     */
    private const array TRANSLATION_KEY_NAMESPACES = [
        'subscription',
        'category',
        'payment',
        'report',
        'enum',
        'common',
        'validation',
        'landing',
    ];

    /**
     * @return list<string> the unresolved keys found in the markup (empty when clean)
     */
    private static function findTranslationKeyLeaks(string $html): array
    {
        // Scripts and styles carry importmap JSON, asset paths, and CSS that legitimately contain
        // dotted tokens; keys only ever leak into visible markup, so strip those blocks first.
        $visible = preg_replace(pattern: '#<(script|style)\b[^>]*>.*?</\1>#is', replacement: '', subject: $html) ?? $html;

        $namespaces = implode(separator: '|', array: self::TRANSLATION_KEY_NAMESPACES);
        preg_match_all(pattern: '/\b(?:' . $namespaces . ')(?:\.[a-z0-9_]+)+/', subject: $visible, matches: $matches);

        return array_values(array_unique($matches[0]));
    }

    private static function assertNoTranslationKeyLeaks(string $html, string $context = ''): void
    {
        $leaks = self::findTranslationKeyLeaks($html);

        self::assertSame(
            [],
            $leaks,
            ('' !== $context ? $context . ': ' : '') . 'rendered output contains unresolved translation keys: ' . implode(separator: ', ', array: $leaks),
        );
    }
}
