# ADR-0009: Savings target model

- Status: Accepted
- Date: 2026-06-08

## Context

Issue #26 asked for a "you should have this much saved" figure per subscription, but the
concept behind it was never written down. Pinning it down surfaced two independent
dimensions and a wrinkle from how Obol stores payments.

The owner budgets monthly and **allocates at the start of the month**: on the 1st, the
month's money is set aside for upcoming bills. A bill is funded **one month ahead** - the
last chunk lands the month before it is due - so the cash is ready by the 1st of the due
month whether the charge falls on the 1st or the 28th, independent of any single paycheck.

The wrinkle: Obol is not a budgeting app. It has no savings balance; a `Payment` is only a
flag plus history, and recording one merely advances `nextRenewal` (ADR-0008). So
`nextRenewal` is "the next *unpaid* renewal," and a bill stays owed until it is recorded
paid. Crucially, the owner does not de-allocate when a bill is funded-but-not-yet-paid: in
the gap between funding a renewal and paying it, they hold the full amount for it **and**
have already begun saving for the next one. The figures must also reconcile against an
external monthly budget (e.g. YNAB) checked at a consistent point in the month.

Two dimensions fall out, each a future per-user setting:

1. **Lead** - when the money must be fully saved: one month ahead / the month due / by the
   due date.
2. **Allocation cadence** - how saving accrues: by month / by week / by day.

## Decision

**For now the model is hard-coded to `one month ahead` + `by month`.** The two settings
above are deferred (#121 for the lead, #120 for the cadence).

`Subscription::savingsTarget($asOf)` sums, over each upcoming renewal, the amount that
should be set aside by `$asOf`:

- `monthlyCost` is allocated on the first of each calendar month (so the figure steps on the
  1st and is otherwise constant within a month).
- A renewal is fully funded by the **first of the month before** it falls due, ramping up one
  `monthlyCost` per earlier month.
- A funded renewal is **held at full `cost`** until it is recorded paid (which advances
  `nextRenewal` and drops it from the sum).
- Saving toward the renewal after it begins once the current one is funded.

So in the gap between funding a renewal and paying it - the unpaid due month - the target is
the held `cost` **plus** the next cycle's accrual. There is no stored "actual saved" balance;
the figure is purely what *should* be in hand.

### Worked example: 1200 every 6 months, due the 28th (monthlyCost 200)

Sampled mid-month, each renewal paid on its due date:

| Mid-month sample | Nov | Dec | Jan | Feb | Mar | **Apr** | May | Jun | Jul | Aug | Sep | **Oct** |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Target | 400 | 600 | 800 | 1000 | 1200 | **1400** | 400 | 600 | 800 | 1000 | 1200 | **1400** |

The ramp climbs 400 -> 1200 over the five months before the due month. In the **due month**
(Apr, Oct) the bill is funded and held (1200) while the next cycle's first 200 has begun ->
**1400**. Recording the payment on the 28th releases the 1200, leaving the next ramp (the
first sample after a paid April is 200, climbing to 400 by mid-May).

So on **April 15, unpaid: 1400** - the 1200 held for the April bill plus the 200 already set
aside toward October.

### Worked example: 100 monthly, due the 15th (monthlyCost 100)

| Window | Held for this month's bill | Saving toward next month | **Total** |
| --- | --- | --- | --- |
| 1st - 14th (this bill unpaid) | 100 | 100 | **200** |
| 15th onward (this bill paid) | - | 100 | 100 |

So on **the 8th: 200**. A monthly bill therefore sits between one and two `cost`s through the
month, which is why it stays in the category total (it must, to reconcile against a monthly
budget) rather than being treated as zero.

## Considered options

- **Cap the target at one renewal, prorating on the renewal's anniversary day** (the first
  implementation) - rejected. It understates by one lead for the whole unpaid due month
  (saying 1200 / 100 above instead of 1400 / 200) and so does not reconcile against a
  monthly budget allocated at the start of the month.
- **`by day` or `by week` allocation now** - deferred to #120. `by day` would ramp smoothly
  rather than stepping on the 1st; `by week` is needed before weekly bills are meaningful.
- **The lead as a setting now** (`month due` / `by due date`) - deferred to #121; only matters
  once the app is multi-user (ADR-0004) and other users budget differently.

## Consequences

- **No new persistence.** The target is derived on read from `nextRenewal`, `cost`, and the
  cadence. "Release on payment" falls out for free: paying advances `nextRenewal`, which
  re-bases the sum on the next renewal.
- **Overdue grows rather than capping.** A renewal left unpaid past its date stays held at
  full `cost` while saving for the next continues on top - e.g. a 12000 yearly bill two
  months overdue reads 15000 (12000 held + 3000 toward next). It self-corrects the moment the
  payment is recorded. In normal operation the scheduler records the payment on the due date
  (ADR-0008), so this is rarely seen.
- **Reconciles at a consistent moment.** Because monthly bills oscillate between one and two
  `cost`s through the month, the category total is a moving number; it lines up with an
  external monthly budget when checked at the same point each month (e.g. the start).
- **Weekly bills are a placeholder.** By-month proration cannot split a cadence that renews
  several times within an allocation month, so a weekly bill is treated as one payment in hand
  until by-week proration (#120) lands.
- Builds on `Subscription::monthlyCost` (#118) for the monthly chunk; does not re-implement
  cadence normalization.
