# ADR-0025: Money and number formatting resolve locale through LocaleSwitcher, not an ambient global

- Status: Accepted
- Date: 2026-07-23
- Amends: ADR-0012

## Context

ADR-0012 kept `Money::format(?string $locale = null)` on the value object, defaulting a null
locale to `\Locale::getDefault()`; `MoneyParser` did the same. On a request this renders
correctly: the per-user locale listener (ADR-0017) calls `LocaleSwitcher::setLocale()`, which
sets `\Locale::setDefault()`, so the global carries the right value on every HTTP path.

The defect is that correctness depends on invisible global state set earlier by something else.
Off the request path - a console command, a Symfony Scheduler task, a messenger worker, a queued
renewal-reminder email - no listener runs, and `\Locale::getDefault()` falls back to the bare
process default `en_US_POSIX`, which renders `$ 100.00` where `en` renders `$100.00`. That is a
silent wrong render with no error and no test to catch it. The app runs FrankenPHP HTTP worker
mode and has a dedicated worker container, so the non-HTTP path is live infrastructure, not
hypothetical - it simply does not format money yet.

The same ambient read already caused test flakiness: a chart-factory unit test passed only when
an earlier test happened to set the process locale first.

The contrast that resolved it: `DateFormatter` already reads the locale from an injected
`LocaleSwitcher` (`getLocale()`), never the global. Money formatting was the odd one out.

## Decision

Money and number formatting resolve locale the same way dates already do - through the injected
`LocaleSwitcher`, the single locale seam - and never through `\Locale::getDefault()`.

- **`Money` is a pure value object again.** It has no `format()` method and no `NumberFormatter`
  dependency. Rendering a `Money` is a presentation concern, not value-object state - a `Money` is
  the same value whatever locale it is later displayed in.
- **A `MoneyFormatter` service owns the formatting**, injecting `LocaleSwitcher` and exposing
  `format(Money): string` with no locale parameter - a direct sibling of `DateFormatter`.
- **A Twig `money` filter** (`MoneyExtension`) delegates to `MoneyFormatter`, so templates read
  `{{ amount|money }}` with no locale threaded through the view layer.
- **`MoneyParser` injects `LocaleSwitcher`** and drops both its `\Locale::getDefault()` fallback
  and the per-method `$locale` parameter.
- **Off-request callers that need a specific locale** wrap the work in
  `LocaleSwitcher::runWithLocale($locale, ...)` - a real injection point the raw global never
  offered.

Net effect: `\Locale::getDefault()` no longer appears in `src/`; every locale read goes through
the one `LocaleSwitcher` seam.

## Considered alternatives

- **Keep the ambient default, document the off-request hazard.** Cheapest, but it leaves the
  silent wrong render in place - a latent defect annotated rather than fixed.
- **Inject a resolved locale *string* into the service constructor.** Under FrankenPHP worker
  mode a shared service is booted once and reused across requests, so a constructor-captured
  locale string freezes the first request's locale for the life of the worker - reintroducing the
  exact staleness this removes, one layer down. Reading live from `LocaleSwitcher` on each call
  holds no per-request state, so there is nothing to freeze and nothing for `ResetInterface` to
  clear.
- **Thread an explicit locale through every call site** (including the eleven Twig templates).
  More churn, and each site becomes a chance to pass the wrong value. The `LocaleSwitcher` seam
  gives one correct source instead of many hand-passed ones.

## Consequences

- `Money` is dependency-free and trivially testable; formatting is unit-tested through
  `MoneyFormatter` with an explicit `LocaleSwitcher`, independent of any global.
- The chart-factory test flakiness is gone: those tests now construct `MoneyFormatter` with an
  explicit `LocaleSwitcher('en')`, so their output no longer depends on a locale another test set.
  The `PinsDefaultLocale` test trait that papered over the ambient read is deleted.
- This amends ADR-0012's consequence that "`Money::format()` keeps formatting in the value
  object." The presentation-helper route ADR-0012 rejected as unnecessary churn is the one taken
  here: the off-request hazard ADR-0012 did not foresee is what makes the value-object home wrong.
  Every other ADR-0012 convention (message-id scheme, catalogs, enum labels, the key-leak
  tripwire) stands unchanged.
- A worker-mode static-analysis linter to catch this whole class of shared-service state bug
  mechanically is tracked separately in the issue tracker.
