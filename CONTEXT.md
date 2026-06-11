# Obol - Context

Obol is a personal, single-user web app for tracking recurring subscriptions and
their payments. It records each subscription's cost and billing cadence, organizes
subscriptions by category, keeps a complete audit trail of every change, and helps
budget for upcoming renewals.

It is deliberately single-tenant: no user accounts, no team or household sharing, no
public API, no bank integration. See `reference/out-of-scope/` for what is explicitly
not being built, and `reference/adr/` for the decisions behind how it is built.

## Domain glossary

Use these terms exactly. The **Avoid** notes call out synonyms that have drifted across
older docs.

- **Subscription** - the central entity: a recurring charge with a cost, a billing
  cadence, and a lifecycle. Read-only from outside; all state changes go through its
  domain methods (`update`, `archive`, `unarchive`, `recordPayment`).
- **Payment** - one recorded transaction against a subscription. Carries an **amount**, a
  **type** (`Verified` or `Generated`), a **paidDate** (when the charge happened), and a
  **createdAt** (when the row was written). Amendable via `Payment::amend()`, which corrects
  the amount/date and marks it `Verified` (used to validate, adjust, or fix a typo). See
  ADR-0008.
- **SubscriptionEvent** - an immutable audit entry recording one change to a subscription.
  Its `type` is `Update`, `CostChange`, `Archive`, or `Unarchive`. Its `context` is a
  map of `field -> {old, new}`. Invariant: `Archive`/`Unarchive` carry empty context;
  `Update`/`CostChange` carry non-empty context.
- **Category** - a named grouping of subscriptions. A category cannot be deleted while it
  still holds subscriptions (`CategoryHasSubscriptionsException`).
- **PaymentPeriod** - the billing cadence enum. The only cases are **`Year`**, **`Month`**,
  and **`Week`**. (There is no `Day` case, despite what older docs claimed.)
- **paymentPeriodCount** - the multiplier on the period, e.g. `paymentPeriodCount: 3` with
  `PaymentPeriod::Month` means "every three months".
- **PaymentType** - how a payment arose: **`Verified`** (asserted by the user) or
  **`Generated`** (created automatically by the scheduler). A `Generated` payment becomes
  `Verified` when the user validates or adjusts it; the reverse never happens.
- **cost** - a subscription's recurring charge, stored as an integer in the currency's
  minor units (e.g. cents). _Avoid_ calling this "amount"; **amount** refers specifically
  to the value recorded on a `Payment` (which defaults to the subscription's cost).
- **monthly cost** - a subscription's `cost` normalized to a one-month equivalent in the
  currency's minor units (`Subscription::monthlyCost`), rounded to the nearest whole cent.
  Yearly costs divide by twelve; weekly costs use 52 weeks per year. Used for the homepage
  category totals and the list view. _Avoid_ "cost per month" as a separate term.
- **record a payment** - append a `Payment` to a subscription. Under automated **payment
  generation** this advances `nextRenewal` by one billing interval (`Subscription::recordPayment`)
  and deleting the latest payment rolls the anchor back; under manual generation the anchor is left
  untouched. _Avoid_ "create a payment".
- **archive / unarchive** - reversibly retire a subscription. Archived subscriptions are
  hidden by default but keep their full history. _Avoid_ "soft-delete" / "delete".
- **renewal** - the point at which a subscription's next charge falls due, stored as the
  `nextRenewal` anchor the scheduler keys off (advanced one interval per payment, not by
  when the user actually paid). _Avoid_ "payment due date" as a separate term.
- **payment generation** - whether Obol generates a subscription's payments automatically or the
  user manages them, stored as the `paymentGeneration` mode (`Automated` | `Manual`). Deleting a
  subscription's latest payment switches it to **manual**: the scheduler stops generating and the
  `nextRenewal` anchor is left entirely to the user. Resuming **automated** generation is an
  explicit user action requiring a future renewal date. _Avoid_ "paused" - the subscription is not
  dormant, only its generation is manual. See ADR-0008.
- **savings target** - the amount that should be set aside by now to cover upcoming renewals
  (`Subscription::savingsTarget`), in the currency's minor units. Models a monthly budget saved
  one month ahead: a **monthly cost** is allocated on the first of each calendar month, a renewal
  is fully funded by the first of the month before it falls due, and that `cost` is held until the
  renewal is recorded paid (which advances `nextRenewal`). A funded-but-unpaid renewal is therefore
  held in full while the next cycle's saving has already begun, so the target peaks at one `cost`
  plus a lead in the unpaid due month, and a monthly bill sits between one and two `cost`s. It is a
  forward-looking budgeting hint only - there is no stored "actual saved" balance. Summed per
  category on the homepage (`CategoryGroup::savingsTotal`) to reconcile against an external monthly
  budget. The lead and allocation cadence become per-user settings later (#121, #120); weekly bills
  are a placeholder (one payment) until by-week proration. See ADR-0009.

## Architecture decisions

Recorded under `reference/adr/`:

- ADR-0001 - ULID primary keys
- ADR-0002 - Event-sourced subscription audit trail
- ADR-0003 - Rich domain entities with asymmetric-visibility immutability
- ADR-0004 - No authentication (single-tenant)
- ADR-0005 - PostgreSQL as the database of record
- ADR-0006 - CQRS command/query buses; data access confined to the handler layer
- ADR-0007 - Write-path message conventions (DTOs stay separate from Commands; Commands carry Ulid)
- ADR-0008 - Payment lifecycle and fixed-cadence renewal
- ADR-0009 - Savings target model (one-month lead, whole-months proration)

ADR-0006 records the CQRS-via-Messenger decision (keep the command/query buses; data
access confined to the handler layer) settled in #79. ADR-0007 extends it with the
write-path conventions settled in #80: form DTOs stay distinct from Commands, and
Commands carry Ulid value objects rather than entities or stringified ids.
