# ADR-0008: Payment lifecycle and fixed-cadence renewal

- Status: Accepted
- Date: 2026-06-06

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
  scheduler recreate it next run; an interval change mid-life may need a manual
  `nextRenewal` correction.
- Supersedes the glossary's "Payment is immutable" line. ADR-0002 (subscription audit) is
  unaffected.
