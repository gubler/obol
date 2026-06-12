# ADR-0010: Reporting and obligation-snapshot model

- Status: Accepted
- Date: 2026-06-12

## Context

Obol exists to track **obligations** - what the owner has committed to - not actual spend
(YNAB owns "did the money really leave"; Obol's payment log is incidental). Epic #28 adds the
reporting views the homepage stops short of: grand totals, what is still owed this period, how
obligations trend over time, and how they break down by category. This ADR records the model
those reports share, settled before the first slice (#142) commits any of it to code.

Three questions had to be pinned down before writing report math:

1. **What counts as the obligation?** Payments, archived subs, and the Generated/Verified
   distinction all could or could not feed the figures.
2. **How is "over time" sourced?** Either replay the subscription audit log, or record a
   running series.
3. **Where does currency conversion happen?** Obol is now multi-currency (#126): subscriptions
   are denominated in their own currency, and a report headline needs a single display currency.

## Decision

### Two core computations

Every report figure is one of two computations over **active (non-archived) subscriptions**:

- **Total obligation** - the sum of each active subscription's period-normalized cost
  (`Subscription::monthlyCost` for the monthly grain, scaled for week/year). Touches neither
  payments nor `nextRenewal`. It is "what you are on the hook for per period," independent of
  whether anything has been paid.
- **Remaining in period** - for each active subscription, project `nextRenewal` forward by its
  interval and sum each `cost` whose renewal falls on or before the end of the calendar period.
  Calendar boundaries ("March 12th -> what is left by March 31st"). Payments are never consulted:
  `nextRenewal` is the authoritative "next owed," so overdue/arrears fall out for free (a $100/mo
  sub genuinely unpaid since April reads $300 in June).

### What is in and out

- **Archived subscriptions are excluded** from all report math.
- **Generated payments count as paid.** Reports inherit ADR-0008's stance and do not distinguish
  Generated from Verified - autogenerating a payment is exactly the act of asserting it was paid.

### Obligation-over-time is recorded on change, not replayed or polled

Obligation-over-time is fed by an **`ObligationSnapshot`** series, not by replaying the audit log.
Each row stores the **native per-currency monthly obligation** as a JSON map
(e.g. `{"USD":4000,"EUR":3000,"JPY":500000}`) keyed by currency code, valued in that currency's
minor units. Native, unconverted storage means:

- a row **survives subscription deletion** - it is a standalone fact, not a derivation that needs
  the subscriptions to still exist;
- it **carries no FX assumptions** - no rate is baked in at write time.

A snapshot is recorded **whenever the obligation changes, and only then.** Every subscription
command that creates, updates, archives, unarchives, or deletes a subscription announces a
`SubscriptionsChanged` domain event (via `SubscriptionChangeNotifier`); a handler on the event bus
recomputes `Subscription::monthlyCost` summed by currency over active subscriptions and appends a
row **only when it differs from the latest**. The event is deferred until the command's transaction
commits (`DispatchAfterCurrentBusStamp`), so the recompute reads committed state; it runs in its
own transaction on the event bus, so a snapshot failure is logged and never rolls back the edit
that triggered it.

This is **complete, not sampled.** The total obligation is a pure function of subscription fields,
so it moves only when one is edited - never with the passage of time - and recording on change
captures every movement exactly, with no cron and no redundant identical rows. It ships first
(#142, the B0 slice) so the series accrues before the chart that consumes it (B4) is built. (The
event bus, removed in #76 as then-unused, is reinstated for this; see ADR-0011.)

### Convert at read time, with today's rate

Cross-currency conversion happens at **read** time via #126's `Converter` /
`DisplayCurrencyProvider`, never at write time. For the over-time series specifically, every
historical row is converted using **today's** rate, not the rate on its recording date. This is a
deliberate choice: it isolates real obligation change (you added or dropped a subscription) from FX
noise (the euro moved), and it needs no exchange-rate history - only the latest rate per currency.
The total-obligation and remaining-in-period headlines are likewise a converted figure plus a
per-currency disclosure; the category pie is converted-currency only, since proportions need a
common unit.

### Reading the series: a stock, with week boundaries decided at read time

Two properties govern how the series is read, and exist to stop future work baking in the wrong
assumption.

**It stores absolute dates, not week buckets.** A snapshot carries a `recorded_at` date, never a
week number or a write-time bucket. Week-start conventions - US (Sunday-start, week 1 contains
Jan 1) vs ISO 8601 (Monday-start, week 1 contains the first Thursday, carrying its own week-year) -
are therefore a read-time interpretation that can be chosen, or made per-user, without migrating a
row. Any week math (over-time labels, a future weekly "remaining") must route through a single
swappable week definition - `IntlCalendar::setFirstDayOfWeek()` + `setMinimalDaysInFirstWeek()` -
not `date('W')`, which hardcodes ISO. Note the homepage week/month/year totals are **not** a rollup
of this series: they are the current total obligation period-normalized (weekly = monthly x 12/52),
computed live from active subscriptions. The series feeds only the over-time trend.

**The figure is a stock, not a flow.** It is a level ("committed per month, as of this date"), not
an accumulation; because the obligation is recorded on every change, the series **is** the step
function, a row at each move. So a single number for a calendar period is never a **sum** of its
snapshots, nor the first/last/highest of them: it is a point-in-time read - the value as-of a
consistent reference day (e.g. the 1st, to reconcile against a month-start budget) - or a
time-weighted average of the levels held. Carry the last snapshot on or before the reference date
forward; that carried value is the **exact** level on that date, at any granularity (including
day), with no sampling lag to correct for.

## Considered options

- **Replay the audit log for over-time** - rejected. It needs every subscription (including
  deleted ones) to still be reconstructable, couples the chart to the event schema, and grows
  more expensive the longer the history. A dedicated on-change snapshot series is O(1) to read and
  self-contained.
- **Convert at write time / store a converted figure** - rejected. It bakes one day's FX rate into
  the row permanently, conflating obligation change with currency movement, and would need a stored
  display currency per row. Native storage + convert-at-read keeps every row currency-neutral.
- **Convert each historical row at its own date's rate** - rejected for the over-time chart. It
  reintroduces FX noise into a series meant to show obligation change, and requires a full rate
  history. Today's-rate conversion answers "how has what I owe changed, in today's money."
- **A periodic (weekly or daily) cron snapshot** - rejected, though an earlier draft of this ADR
  chose weekly. Because obligation moves only on a subscription edit, a fixed cadence records mostly
  identical rows yet still lags real changes by up to one interval. Recording on change is both
  exact and lower-volume (no row when nothing changed), so it strictly dominates. A low-frequency
  cron as a *backstop* against a missed announcement was considered and dropped for now: the
  `Subscription` reminder comment plus the single `SubscriptionChangeNotifier` seam are the guard.

## Consequences

- **New persistence: `obligation_snapshot`.** A ULID id, a `recorded_at` date, and the native
  per-currency JSON map. This is the epic's only new table; the two core computations derive
  everything else on read from existing data.
- **The series is only as old as the recorder.** Over-time charts show whatever has accrued since
  B0 shipped; there is no backfill, which is why the recorder lands early.
- **The write path must announce changes.** Recording on change means every subscription-mutating
  command handler calls `SubscriptionChangeNotifier::notifyChanged()` after its change. A comment on
  the `Subscription` entity flags this, and the event bus's reinstatement is recorded in ADR-0011. A
  future mutation path that forgets to announce would silently miss a movement - the cost of trading
  the cron's blunt liveness for on-change exactness.
- **Reports never reconcile to spend.** Because Generated counts as paid and payments are ignored
  by both computations, the figures answer "what am I committed to," not "what left my account" -
  by design (ADR-0004, ADR-0008).
- **Remaining-in-period surfaces arrears for free.** A subscription unpaid past its date keeps
  contributing each missed renewal to the remaining total until a payment advances `nextRenewal`.
- Builds on `Subscription::monthlyCost` (#118) and the `Money` embeddable (#128); the snapshot map
  stores raw minor-unit integers, not `Money` objects, so it is a flat, currency-keyed JSON value.
