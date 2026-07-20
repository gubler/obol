# ADR-0021: The CalendarDate value object encodes the naive/zoned frame in the type system

- Status: Accepted
- Date: 2026-07-20
- Refines: ADR-0016

## Context

ADR-0016 decided that `nextRenewal` is "a timezone-naive calendar date whose meaning is the owner's
local date, resolved against the owner's current timezone at read time." That decision is correct and
stands. But it was enforced only by *convention*: the value was a `\DateTimeImmutable` in a
`timestamp` column, so nothing in the type system stopped a caller from comparing it against a zoned
instant. ADR-0016 itself warned about exactly that hazard - and the bug shipped anyway, in four places.

The clearest symptom: `Subscription::remainingInPeriod()` compared a naive `nextRenewal` against a
zoned `$periodEnd`. `Jul 31 23:59 America/New_York` is `Aug 1 03:59 UTC`, so a bill due on the 1st of
next month was counted in this month - wrong 24 hours a day for every user behind UTC. The same
naive-vs-zoned comparison lived in `savingsTarget`, the obligation-trend runner, and
`ExchangeRateRepository::latestRate`. A random time-of-day in the test fixtures masked it for ~20 hours
a day, which is why the suite only failed for a few hours each evening (#397).

Separately, PHP's month arithmetic drifts: `Jan 31 + P1M` lands on `Mar 3` (February overflows), so a
renewal anchored on the 31st silently skipped February and then stayed on the 3rd forever.

## Decision

**Introduce `App\ValueObject\CalendarDate` - a `final readonly` value object of year/month/day - and make
it the type of every calendar-date field.** A calendar date is "a day on the wall," not an instant; it
has no time and no offset, structurally (there is no field that could hold one).

Crossing between a `CalendarDate` and a `\DateTimeImmutable` *requires naming a zone*, at every crossing:

- `CalendarDate::forDatetimeInTimezone(\DateTimeImmutable $instant, \DateTimeZone $tz)` - the one place an
  instant becomes a date. The zone is required: this is the naive/zoned boundary, and naming the zone at
  the call site is what stops the confusion.
- `CalendarDate::toDateTimeImmutable(\DateTimeZone $tz)` - midnight on the date in a named zone.

There is no ambient default to fall back to, so the mistake the whole type exists to prevent becomes
un-writable. The two legitimate boundary types are `User` (which owns the owner's zone) and
`CalendarDate` itself; an architecture test forbids any other entity or value object from touching a
timezone.

### Persistence

`CalendarDate` persists as a **single-column custom DBAL type mapping to `DATE`** (`CalendarDateType`,
mirroring `CitextType`), registered under `doctrine.dbal.types` plus a `mapping_types: date:
calendar_date` so that *every* `DATE` column hydrates as a `CalendarDate`. That reverse mapping is why
the swap had to be atomic: `ObligationSnapshot.recordedAt` and `ExchangeRate.asOf` were already `DATE`
columns, so they moved to `CalendarDate` in the same commit as `next_renewal` and `paid_date`. A
`DATE` column (not an embeddable and not a timestamp) keeps `ORDER BY`, DQL `<=`, and the `AT TIME ZONE`
generation filter working unchanged: `DATE <= (timestamptz AT TIME ZONE tz)` still flips at the owner's
local midnight.

### Renewal day and month arithmetic

`Subscription` stores a `renewalDay` (1-31), the canonical day-of-month a monthly or yearly subscription
recurs on, kept separate from `nextRenewal->day`. `advance(CalendarDate $from, int $steps)` replaces the
old `DateInterval` math: it projects from the anchor **by multiples** (never iterating +1 interval at a
time), landing on `min(renewalDay, target month's length)`. That restores the 31st after a short month
(Jan 31 -> Feb 28 -> Mar 31), never skips a month, and is exactly reversible (`advance($x, +1)` then
`advance(_, -1)` returns to `$x`). `remainingInPeriod` and `savingsTarget` project the same way, so a
count over a run of months can no longer drift.

### Past dates start Manual

The future-date form validation is deleted. **Any date is allowed; a `nextRenewal` already in the past
(judged in the owner's zone) forces `paymentGeneration = Manual`** in one un-bypassable place -
`Subscription::applyRenewalDate()`, which the constructor, `update()`, and `automatePayments()` all route
through. This preserves the anti-catch-up intent (the scheduler never fires a backfill run against a past
anchor) while letting a user backfill a subscription they are entering after the fact. A soft,
JS-only past-date warning replaces the old hard block; the explicit resume-to-automated flow keeps a
future-date check, surfaced by the controller as a form error rather than a 500.

### Boot timezone

The application pins `date_default_timezone_set('UTC')` at boot (`public/index.php`, `bin/console`).
Calendar dates carry the owner's zone applied at read time, but instant storage (`createdAt` timestamps)
and any stray ambient-zone date path must not vary with the host's `TZ`. Production deployments should
also set `date.timezone=UTC`; the boot pin is belt-and-braces.

## The OS-vs-PHP timezone question

Two distinct clocks are involved and it is worth being explicit about which does what:

- **The OS/container clock** provides the absolute instant (`ClockInterface::now()`). Its wall-clock
  presentation depends on the process timezone, which the boot pin fixes to UTC.
- **PHP's date functions** read that same process timezone (`date.timezone` /
  `date_default_timezone_set`) for anything that lacks an explicit zone - `new \DateTime('today')`,
  Doctrine's built-in `DateImmutableType`, an `IntlDateFormatter` built with a null zone.

`CalendarDate` is designed to be independent of the process timezone: every internal `\DateTimeImmutable`
it constructs names an explicit zone (usually UTC, since the date has none of its own). The owner's zone
enters only at the two boundary methods above, sourced from `User.timezone`, never from the process
default. So the answer to "OS or PHP timezone?" is: **neither drives domain behavior.** The instant comes
from the OS clock; the *interpretation* comes from the owner's stored zone; the process default is pinned
to UTC purely so instant-storage and any un-migrated ambient path stay stable.

## Consequences

- The naive/zoned bug family is now unrepresentable, not just discouraged: a field cannot regress to a
  bare instant (reflection arch test), no non-boundary type can touch a zone, and no code can hand-roll
  `->format('Y-m-d')` (arch tests).
- The `#397` "flaky suite" is gone because the bug it masked is fixed; fixtures produce `CalendarDate`
  values with no time-of-day, so the `FOUNDRY_FAKER_SEED` no longer changes whether the suite passes.
- Renewal anchors self-heal across short months instead of drifting, and the stored `renewalDay` makes
  the healing reversible. A pre-existing drifted anchor is frozen at its current day by the migration
  backfill, not retroactively corrected (repairing drifted anchors is out of scope here).
- `down()` on the migration is lossy and one-way: calendar dates come back as timestamps at midnight and
  the `renewalDay` is discarded.

## Considered options

- **Keep `\DateTimeImmutable`, add a throwaway hotfix to `remainingInPeriod`.** Rejected: it fixes one of
  four sites and leaves the frame a convention the next caller can break again.
- **Model `CalendarDate` as a Doctrine embeddable (multiple columns).** Rejected: it would break `ORDER
  BY`, DQL `<=`, and the `AT TIME ZONE` filter, all of which need a single `DATE` column.
- **Give `CalendarDate` `plusMonths`/`plusYears`.** Rejected: month clamping is a *billing* rule (it needs
  `renewalDay`), so it belongs on `Subscription::advance`, not on the general-purpose date type.
