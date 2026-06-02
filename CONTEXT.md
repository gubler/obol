# Obol - Context

Obol is a personal, single-user web app for tracking recurring subscriptions and
their payments. It records each subscription's cost and billing cadence, organizes
subscriptions by category, keeps a complete audit trail of every change, and (planned)
helps budget for upcoming renewals.

It is deliberately single-tenant: no user accounts, no team or household sharing, no
public API, no bank integration. See `reference/out-of-scope/` for what is explicitly
not being built, and `reference/adr/` for the decisions behind how it is built.

## Domain glossary

Use these terms exactly. The **Avoid** notes call out synonyms that have drifted across
older docs.

- **Subscription** - the central entity: a recurring charge with a cost, a billing
  cadence, and a lifecycle. Read-only from outside; all state changes go through its
  domain methods (`update`, `archive`, `unarchive`, `recordPayment`).
- **Payment** - one recorded transaction against a subscription. Immutable once created.
  Carries an **amount** and a **type** (`Verified` or `Generated`).
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
- **PaymentType** - how a payment arose: **`Verified`** (recorded by the user) or
  **`Generated`** (created automatically by the scheduler).
- **cost** - a subscription's recurring charge, stored as an integer in the currency's
  minor units (e.g. cents). _Avoid_ calling this "amount"; **amount** refers specifically
  to the value recorded on a `Payment` (which defaults to the subscription's cost).
- **record a payment** - append a `Payment` to a subscription and advance its
  `lastPaidDate` (`Subscription::recordPayment`). _Avoid_ "create a payment".
- **archive / unarchive** - reversibly retire a subscription. Archived subscriptions are
  hidden by default but keep their full history. _Avoid_ "soft-delete" / "delete".
- **renewal** - the point at which a subscription's next charge falls due. _Avoid_
  "payment due date" as a separate term.
- **savings target** - _(planned, see #26)_ the prorated amount that should be set aside by
  now to cover the next renewal. Not yet implemented.

## Architecture decisions

Recorded under `reference/adr/`:

- ADR-0001 - ULID primary keys
- ADR-0002 - Event-sourced subscription audit trail
- ADR-0003 - Rich domain entities with asymmetric-visibility immutability
- ADR-0004 - No authentication (single-tenant)
- ADR-0005 - PostgreSQL as the database of record

The CQRS-via-Messenger pattern (command/query buses) is under active review (#79, #80)
and will get its ADR once those design conversations settle, so it is intentionally not
recorded yet.
