# ADR-0013: Payment source tracking

- Status: Accepted
- Date: 2026-06-30

## Context

Issue #264 asks Obol to track methods of payment as first-class
things and optionally attach one to each subscription, to answer two questions: how much
obligation rides on each method, and which subscriptions sit on a given method (the "my
card got reissued, what do I need to update" case).

The domain already has a near-identical shape: `Category` is a named, optional grouping of
subscriptions with its own CRUD, a nullable `ManyToOne` on `Subscription`, and a reporting
view (the category pie + drill-down, ADR-0010). The decision is how closely payment sources
should follow that template, and where they should deliberately diverge.

## Decision

### A PaymentSource is a Category-shaped entity

`PaymentSource` carries `name` (required), an optional free-text `comment`, and a `TileColor`
for its report tile/slice. No icon and no payment-method type enum: the identifying detail
("Amex 1234") lives in the name, and a type taxonomy is structure the use case does not call
for. `Subscription` gains a nullable `ManyToOne $paymentSource`, mirroring `$category`. A
subscription with no source is **Unassigned**, modeled as null end to end, the analog of
**Uncategorized**.

The term is **PaymentSource**, not "payment method", to avoid colliding with the existing
`PaymentType` (Verified/Generated).

### Changing a subscription's source is audited

The `paymentSource` field joins `Subscription::update()`'s change context (old/new by name,
`Unassigned` for null) and is recorded as a `SubscriptionEvent::Update`, exactly as `category`
is. Bulk reassignment goes through a dedicated `Subscription::reassignPaymentSource()` method
that records its own `Update` event per moved subscription, so the "every change is audited"
invariant has no gap on a high-impact move. Because reassigning a source leaves obligation
untouched, the reassign path deliberately does not announce a `SubscriptionsChanged` event
(see ADR-0010).

### Deletion blocks while in use; reassignment is explicit and separate

Deleting a source that still holds subscriptions throws `PaymentSourceHasSubscriptionsException`,
mirroring `CategoryHasSubscriptionsException`. The reissue case is served two ways, neither of
which weakens that rule: rename the source in place (same provider, links preserved), or use a
standalone "Move all to..." action on the source's Show page to reassign every subscription,
after which the now-empty source can be deleted. Reassignment is kept out of the delete flow so
it is discoverable on its own; it is offered only when the source has subscriptions and another
source exists to move them to.

### The report mirrors the category report, current-state only

Per-source obligation reuses ADR-0010's model: the sum of period-normalized
`Subscription::monthlyCost` over active (non-archived) subscriptions on that source, with
`Unassigned` as one slice and multi-currency converted at read time. The report is a
composition pie plus per-source drill-down at `/reports/payment-sources/{id}` (and a reserved
`/reports/payment-sources/unassigned`), surfaced as a section on the reports overview.

Obligation-by-source is **not** added to the obligation snapshot series. ADR-0010 snapshots
stay global per-currency; a historical per-source dimension is a schema change #264 does not
need and is out of scope.

## Consequences

- Payment sources inherit Category's full pattern: five CRUD controllers, a Foundry factory,
  the nullable-FK migration, and the convert-at-read report path. Low novelty, high consistency.
- The audit trail records source changes for free via the existing change-context machinery; a
  bulk reassign can write many events at once, which is acceptable for a single-user app.
- Reports gain a second composition view with no new persistence; the snapshot series is
  untouched, so there is no "obligation per source over time" until a later ADR adds one.
- "Method" is reserved away from `PaymentType`; the glossary gains `PaymentSource` and
  `Unassigned` entries.

## Alternatives considered

- **Many sources per subscription (ManyToMany).** Rejected: the issue attaches a method to
  each subscription (singular); split billing is unrequested complexity.
- **A plain free-text column on Subscription.** Rejected: no canonical grouping, so the
  per-source reports Derek wants are impossible.
- **A historical obligation-per-source series.** Rejected as out of scope (see above).
- **Allow-delete-and-null-out.** Rejected: diverges from Category's safety rule and silently
  loses the link a reissue depends on.
