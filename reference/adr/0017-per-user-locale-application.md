# ADR-0017: Per-user locale application and an independent date-format preference

- Status: Accepted
- Date: 2026-07-06

## Context

ADR-0012 externalized every user-facing string and made value formatting *capable* of following a
locale (`Money::format(?locale)`, the `format_date` filter, ICU cadence), but deliberately deferred
three things to the multi-user milestone: applying a per-user locale, locale switching, and the
currency-locale policy. The per-user-settings slice then added `User.locale` (default `en-US`), but
nothing read it - money, dates, and translation all still ran at the hardcoded `en`.

This ADR settles how a user's locale is modeled, applied, and acquired, and how dates are rendered.

## Decision

**One region-aware locale tag drives translation and currency/number formatting; the date format is a
separate preference.**

- **`User.locale` is a single BCP-47 tag** (`en-GB`, `sv-SE`, `de-DE`, ...). The translator falls back
  to the `en` catalog for languages we have no catalog for (`framework.translator.fallbacks: [en]`), so
  UI text stays US English while money and numbers format natively for the region. There is no separate
  language/region split - a Stockholm user stores `sv-SE`, reads Swedish number formatting, and sees US
  English text via the fallback. `displayCurrency` remains "which currency"; `locale` is "how money and
  numbers read".

  The base `en` catalog is effectively **US English** (`en` behaves as `en-US`); the regional catalogs
  (`en-GB`, `en-CA`) carry only their divergent strings and fall back to `en` via ICU parent-shortening.
  So "falls back to English" means "falls back to US English", and a language-only guess like `en` is
  filled to `en-US` (see `LocaleGuesser`).

- **`User.dateFormat` is a rendering style, independent of locale** (`DateFormat` enum: `Long`, `Medium`,
  `Short`, `Iso`, defaulting to `Medium`). `Long`/`Medium`/`Short` follow the ambient locale's own form
  via an `IntlDateFormatter` length - so a British user reads `Medium` day-first and an American reads it
  month-first - while `Iso` pins `yyyy-MM-dd` regardless of locale, for those who want a fixed order.
  Formatting flows through a single seam,
  `App\Service\DateFormatter` (locale length or the ISO pattern), used by both the `user_date` Twig filter
  (`App\Twig\UserDateExtension`) and the account preferences picker, so every date display honors the
  preference. An independent currency-format preference is intentionally *not* added - number formatting
  follows the locale until a real need appears.

- **`LocaleSwitcher` is the application seam - never raw `\Locale::*`.** `App\EventListener\UserLocaleListener`
  runs on `kernel.request` at priority 7 - after the firewall (so the authenticated user is available)
  and after Symfony's `LocaleAwareListener` (so our value is the last word) - and calls
  `LocaleSwitcher::setLocale($tag)`, which sets the ambient `\Locale::setDefault` (read by `Money::format`
  and `format_date`), the translator, and the routing context in one call. The switcher is injected and
  mockable; the date extension reads the ambient locale via `LocaleSwitcher::getLocale()`. Setting a
  concrete locale on every authenticated main request (and leaving the framework default `en` when
  unauthenticated) keeps the long-lived FrankenPHP worker free of cross-request locale bleed.

- **`locale` is nullable: null means "not yet resolved".** It is inferred once from the browser
  (`App\Service\LocaleGuesser`, over `Accept-Language`, mirroring `CurrencyGuesser` but with no
  supported-set restriction - any region formats natively) and persisted through the command bus
  (`ResolveUserLocaleCommand`), so the write stays in the handler layer (ADR-0006). After that first
  resolution the column is non-null forever. The existing-rows migration blanks `locale` to NULL so
  every account re-infers a real, region-aware tag rather than freezing the old `en-US` default.

## Consequences

- Formatting is now per-user: two users with different locales see natively-formatted money and dates
  on the same data. English is the only UI *language*; the regional English catalogs (`en-GB`, `en-CA`)
  now resolve for users on those locales, and a second-language catalog would translate for free for
  users whose `locale` language matches it.
- The user-facing picker to change `locale` and `dateFormat` after resolution is a later slice (the
  account-settings hub); this ADR covers only the plumbing, policy, application, and inference.
- Inference happens in a request listener rather than at authentication success, so a user whose row was
  NULLed but who has a live session still gets resolved on their next request. If the one-time
  write-on-request proves noisy, moving inference to an auth-success subscriber is a clean retrofit (the
  command and guesser stay; only the trigger moves).

## Alternatives considered

- **Split `language` + `region`/date-format fields.** Rejected: more schema and wiring than a single
  region-aware tag needs while English is the only language; the translator's language-subtag fallback
  already separates "text language" from "formatting region".
- **`enabled_locales`.** Deliberately not set - it would clamp the set of formatting locales, defeating
  native number/currency formatting for arbitrary regions.
- **Raw `\Locale::setDefault()` in the listener.** Rejected in favor of `LocaleSwitcher` so locale
  management is a first-party, mockable service rather than scattered global-function calls.
