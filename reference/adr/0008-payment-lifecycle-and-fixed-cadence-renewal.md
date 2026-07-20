# ADR-0008: Payment lifecycle and fixed-cadence renewal

- Status: Accepted
- Date: 2026-06-06
- Amended by: ADR-0021

> **Amended by ADR-0021.** The fixed-cadence renewal now projects from the anchor by multiples rather
> than adding a `DateInterval` each step. `Subscription` stores a `renewalDay` (1-31); `advance()`
> lands on `min(renewalDay, target month's length)`, so a run of months no longer drifts (Jan 31 ->
> Feb 28 -> Mar 31) and each step is reversible. The old "resume requires a future renewal" rule is
> generalized: any renewal already in the past (in the owner's zone) forces `Manual` generation in one
> place (`applyRenewalDate`), preserving the anti-catch-up intent while allowing backfill.

## Context

The #82 grill revisited the glossary's "a Payment is immutable" characterization and
surfaced an adjacent scheduling bug. Two problems:

1. Payments could only be created and deleted - no way to validate a scheduler-generated
   payment, adjust its amount (e.g. a renewal discount), or fix a typo on a user-entered
   one. The only recourse was delete-and-recreate, which discards the original record.
2. The scheduler computed the next charge as `lastPaidDate + interval`, and
   `lastPaidDate` tracked when the user *actually* paid. Paying late or early therefore
   walked the billing date forward every cycle - wrong for subscriptions, which bill on a
   fixed cadence (due the 1st, paying the 6th must not move the next due date off the 1st).

## Decision

**Fixed-cadence renewal.** A `Subscription` stores `nextRenewal` (the renewal anchor;
glossary term "renewal"), and `lastPaidDate` is removed. Adding a payment - by the user or
the scheduler - advances `nextRenewal` by one interval *from the current `nextRenewal`*;
deleting a payment rolls it back one interval; amending a payment leaves it unchanged. The
scheduler fires when `nextRenewal <= today`, adding a `Generated` payment dated to the
renewal date. `nextRenewal` is also directly user-editable via the subscription edit form,
for interval changes and manual correction.

**Payment lifecycle.** A `Payment` is amendable in `amount` and `paidDate`. The amend
operation also flips `Generated -> Verified`: any human touch asserts a confirmed fact, and
`type` never moves the other way. "Validate" is amend with unchanged values; "adjust" is
amend with corrected values; fixing a typo is amend on an already-`Verified` payment. There
is no payment audit trail - this is a personal tracker, not a ledger, so amends overwrite.

**Payment fields.** Rename the misnamed `createdAt -> paidDate` (it has always held the
paid date). Add a genuine `createdAt` row-creation timestamp, set in the constructor -
cheap, and not reconstructable after the fact if later needed.

## Considered options

- **Free `Payment` mutability** - rejected; constrained to `amount`/`paidDate` plus the
  one-way `type` transition, which are the only real needs.
- **A `PaymentEvent` audit trail** mirroring `SubscriptionEvent` - rejected as overkill for
  a personal tracker; amends overwrite.
- **Keep `lastPaidDate` as the scheduling anchor** - rejected; it conflates "last paid"
  with "next due" and drifts on late/early payment.
- **Derive `nextRenewal` from payment count x interval** - rejected; the interval can
  change mid-life, so `nextRenewal` is stored and adjusted incrementally.

## Consequences

- Scheduling no longer drifts; the billing cadence is anchored.
- Creation captures `nextRenewal` instead of a last-paid-date; the subscription edit form
  can change it, audited via the existing `Update` event.
- `lastPaidDate` is removed everywhere (entity, create/update DTOs, commands, forms,
  scheduler, templates, and the many tests that seed it). This is the larger effort and
  lands first as a bug fix (#106); the payment validate/adjust capability (#107) rides on
  top.
- Accepted edges (not engineered around): deleting a still-due `Generated` payment lets the
  scheduler recreate it next run (**superseded by the 2026-06-11 amendment below**); an
  interval change mid-life may need a manual `nextRenewal` correction.
- Supersedes the glossary's "Payment is immutable" line. ADR-0002 (subscription audit) is
  unaffected.

## Amendment (2026-06-11): payment generation mode - automated vs manual (#124)

The original "accepted edge" - deleting a still-due `Generated` payment lets the scheduler
recreate it next run - proved user-hostile: a deliberately deleted payment reappearing every
day reads as the system overriding the user. Deleting a payment has one meaning - *this was
not paid* - and it must stay deleted.

**Decision.** A `Subscription` carries a `paymentGeneration` mode (`PaymentGeneration` enum:
`Automated` | `Manual`), defaulting to `Automated`. The enum names exactly what is tracked -
who generates payments, Obol or the user - rather than a vaguer "paused" flag.

- **Deleting the latest payment** switches the subscription to `Manual`. Only the most recent
  payment is deletable; deleting a historical one would desync the anchor and is unsupported.
  The triggering delete still rolls `nextRenewal` back (it happens while generation is still
  automated), so the unpaid cycle correctly reads as owed.
- **Under `Manual`** the scheduler skips the subscription entirely and the anchor is the
  user's: recording or removing a payment no longer shifts `nextRenewal` (anchor automation is
  gated on `Automated`). `nextRenewal` is edited directly on the subscription edit form.
- **Resuming is always explicit and requires a future renewal date**, so it never triggers an
  immediate catch-up generation. Two surfaces offer it: a "restart automatic payments?" control
  on the record-payment form (revealing a next-renewal field prefilled with the next cadence
  after today) and on the edit form. `Subscription::automatePayments()` validates the future
  date, flips back to `Automated`, and sets the anchor.

We deliberately do **not** infer the user's finances (e.g. "they recorded enough catch-up
payments, so they must be current"); pausing and resuming are explicit user actions. This
respects that someone may choose to run a subscription entirely by hand for the life of their
account. Supersedes the delete-recreate accepted edge above; implemented in #124.

## Amendment (2026-06-16): backfill-aware advancement and type-aware deletion (#208)

Entering historical payments to seed a subscription exposed two flaws in the rules above:
recording *any* payment advanced `nextRenewal`, so backfilling old payments shoved the anchor
a cycle into the future each time; and deleting the latest payment *always* flipped the
subscription to `Manual`, even when the deletion was a correction of hand-entered history.

**Conditional advancement.** Recording a payment advances `nextRenewal` only when generation is
`Automated` *and* the payment falls in the current open period - `paidDate > nextRenewal -
interval` (strict, so the period's opening boundary belongs to the prior period). There is no
upper bound, so a genuinely late payment for the current period still advances. A payment that
backfills a prior period leaves the anchor untouched.

**An advance is recorded on the payment, not re-derived.** A `Payment` carries an
`advancedRenewal` boolean, set true only when recording it advanced the anchor. Removal undoes
*exactly* that: `removePayment` rolls `nextRenewal` back one interval only when the payment's
`advancedRenewal` is set and generation is still `Automated`. Reconstructing "did this advance?"
from dates at delete time was rejected - an early-but-on-time payment is dated before the period
it pays for, so window math misclassifies it. Accepted edge: amending a payment's `paidDate`
across the period boundary leaves `advancedRenewal` stale; amend is rare and anchor-neutral, so
this is documented rather than engineered around.

**Type-aware deletion.** Deleting the latest payment switches to `Manual` only when that payment
is `Generated` - the scheduler's guess that the user is rejecting ("I did not pay this"). Deleting
a `Verified` payment is data correction and leaves the generation mode alone. This narrows the
2026-06-11 amendment's "deleting the latest payment switches to Manual", which flipped on every
deletion regardless of type.

**Delete confirmation.** The delete action states the computed consequence before confirming:
whether it rolls the next renewal back (and between which dates) and whether it switches the
subscription to manual payments. Implemented in #208.

Existing payments at migration time backfill to `advancedRenewal = false` (the safe default: a
payment of unknown provenance does not roll the anchor back on deletion).
