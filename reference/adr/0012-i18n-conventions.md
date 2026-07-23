# ADR-0012: Internationalization conventions

- Status: Accepted
- Date: 2026-06-17
- Amended by: ADR-0025

> **Amended by ADR-0025.** The value-formatting decision below - keep `Money::format()` on the
> value object with an optional locale defaulting to `\Locale::getDefault()` - is superseded.
> Formatting moved off the `Money` value object into a `MoneyFormatter` service (and a `money`
> Twig filter) that resolves the locale through the injected `LocaleSwitcher`, never the ambient
> global; `MoneyParser` did the same. Every other convention here (message-id scheme, catalogs,
> enum labels, the key-leak tripwire) stands.

## Context

Obol's user-facing text and value formatting are hardcoded for one locale. Every
flash message, form label, Twig string, and enum `label()` returns English
literals; `Money::format()` renders with a fixed symbol prefix and `number_format(...,
'.', ',')`; dates print through `|date('M j, Y')`; and cadence is pluralized with a
`paymentPeriodCount > 1 ? 's' : ''` hack. The translator is installed and configured
(`default_locale: en`, `default_path: translations/`) but unused - `translations/` is
empty and there are zero `trans` calls.

Internationalization is a prerequisite for the multi-user work (ADR-0004): once
accounts exist, locale becomes a per-user preference. This work does the i18n
groundwork now, decoupled from accounts, so the day a second locale lands the app is
already fully internationalized. The first target locale will be en-GB (a safe,
checkable variant), then others.

Several conventions here are hard to reverse once catalogs exist (the message-id
scheme in particular) and several deviate from the obvious path, so they are recorded
together.

## Decision

**Internationalize fully now - both message translation and value formatting - but add
no locale switching.** `default_locale: en` stays the only locale; there is no
`_locale` routing, no switcher, no `enabled_locales` list. The payoff is the
externalization itself: a single `messages.en-GB.yaml` (or any locale) dropped in later
is immediately live once switching is wired.

Scope line: **this decision covers full i18n (strings and formatting). Locale
*switching*, the per-user locale preference, and the currency-locale policy** (e.g.
whether JPY always reads as Yen regardless of UI language) belong to the later
multi-user work. This decision does not answer those.

Conventions:

- **Keyed/symbolic message IDs**, dot-namespaced by area: `subscription.flash.created`,
  `enum.payment_period.month`, `common.save`. Not English-as-key.
- **Two authored domains**: `messages` (UI copy, flashes, form labels, enum labels) and
  `validation` (custom constraint messages, the domain Symfony's validator resolves in).
  `time` is inherited from KnpTimeBundle and left alone.
- **YAML catalogs**. ICU only where genuinely needed, via the `messages+intl-icu.en.yaml`
  variant; cadence pluralization ("every 3 Months") forces at least one ICU message.
- **Enum `label()` returns a translation key**, not English. Symfony auto-translates form
  choice labels in the form's domain; templates apply `|trans`. All `label()` call sites
  are audited for the semantic change.
- **Value formatting follows the request locale** via `\Locale::getDefault()` (set
  per-request by Symfony; always `en` today). `Money::format(?string $locale = null)`
  gains the optional locale parameter - matching the existing `MoneyParser` signature -
  and switches internals to `NumberFormatter::CURRENCY`. Dates move to the locale-aware
  `format_date()` filter; cadence moves to an ICU plural message. In `en` the rendered
  output is unchanged.
- **Test backbone: a "no unresolved key leaks into rendered output" tripwire.** An
  unresolved or misspelled key renders as the literal key string, so a functional
  assertion that rendered HTML contains none of the reserved key namespaces
  (`subscription.`, `category.`, `payment.`, `report.`, `enum.`, `common.`, `validation.`)
  catches both missing catalog entries and un-`trans`'d labels. This also provides the
  red-green loop the work otherwise lacks (in `en`, a correctly externalized page renders
  byte-identical to the hardcoded one): converting a call site to a key before adding the
  catalog entry turns the suite red. `lint:yaml translations/` joins the check suite and
  pre-commit hook.

## Considered options

- **English-as-key** (the natural string is the msgid) - rejected. It minimizes the
  first locale's catalog (only differing strings need entries, the rest fall back to the
  source), which is tempting given en-GB-first. But editing English wording changes the
  msgid, silently orphaning every translation for that string - invisible decay as
  languages multiply. Keyed IDs keep the catalog the single source of truth for copy and
  stay stable across copy edits. The en-GB minimal-catalog benefit is preserved under
  keyed IDs anyway (fallback works identically); the only cost is that the `en` base
  catalog must be complete, which the key-leak test enforces.
- **Deferring value formatting to the multi-user work** - rejected. Formatting is inert
  in `en` (no visible change), which argued for deferral, but i18n is the natural home for
  value localization and the seam (`MoneyParser`'s optional locale) already exists. Doing
  it now while drawing the currency-locale policy line at multi-user keeps the formatting
  seam correct without prematurely answering the per-user questions.
- **Single big-bang PR** for the whole externalization - rejected. A diff touching every
  template, controller, form, and enum hides missed strings and is miserable to review.
  The work is split into per-area child PRs instead.
- **A "no bare text in Twig" linter** to catch un-externalized strings - rejected as not
  worth building (too many false positives). The key-leak test proves every key resolves;
  catching strings nobody converted is left to per-surface human review, aided by
  `debug:translation --only-missing`.

## Consequences

- The `en` base catalog must stay complete or raw keys leak into the UI; the key-leak
  test makes that a hard failure rather than a silent gap.
- `Money::format()` keeps formatting in the value object (gaining a locale parameter)
  rather than moving to a presentation helper - least churn, consistent with
  `MoneyParser`, and call sites (`{{ amount.format() }}`) are untouched.
- Shared mechanisms (the enum key pattern, `Money::format`, the cadence ICU message, the
  test helper) land in dedicated horizontal child PRs so they are not split across the
  per-area surface PRs.
- The key-leak tripwire proves keys *resolve*, not that every string was *externalized*; a
  forgotten hardcoded string stays English and the test will not flag it.