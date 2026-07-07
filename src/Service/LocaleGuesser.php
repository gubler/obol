<?php

// ABOUTME: Guesses a user's locale (BCP-47 tag) from their browser's Accept-Language header.
// ABOUTME: Any well-formed tag is accepted - formatting is native to any region; falls back to en-US.

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class LocaleGuesser
{
    public function guessFrom(Request $request): string
    {
        // getLanguages() is already RFC-parsed, returned as e.g. 'de_DE' in preference order. Unlike
        // currency, we do not restrict to a supported set: ICU formats any region natively, and the
        // translator falls back to the en catalog for languages we have no messages for.
        $preferred = $request->getLanguages()[0] ?? null;
        if (null === $preferred || '' === $preferred) {
            return 'en-US';
        }

        /**
         * A browser may send only a language (`de`, `fr`, `en`). Fill in its most likely region via ICU
         * (`de` -> `de_DE`, `en` -> `en_US`, `fr` -> `fr_FR`) so formatting is still region-aware; a full
         * tag passes through unchanged. Then emit a hyphenated BCP-47 tag (de_DE -> de-DE).
         *
         * @phpstan-ignore staticMethod.notFound (a real ext-intl method missing from PHPStan's stubs)
         */
        $maximized = \Locale::addLikelySubtags($preferred);
        $language = \Locale::getPrimaryLanguage($maximized);
        if (null === $language || '' === $language) {
            return 'en-US';
        }

        $region = \Locale::getRegion($maximized);

        return null === $region || '' === $region ? $language : $language . '-' . $region;
    }
}
