# ADR-0002: Event-sourced subscription audit trail

- Status: Accepted
- Date: 2026-06-02

## Context

Changes to a subscription (renaming, recategorizing, cost changes, archiving) need a
durable, human-readable history. The subscription's current state alone cannot answer
"what changed, when, and from what to what".

## Decision

Every change to a `Subscription` appends an immutable `SubscriptionEvent`. The event's
`type` is one of `Update`, `CostChange`, `Archive`, `Unarchive`. Its `context` is a map of
`field -> {old, new}` describing exactly what changed. The subscription's domain methods
own this: `update()` diffs old against new values (via `ChangeContextGenerator`) and emits
an `Update` event for descriptive fields and a separate `CostChange` event for
billing-affecting fields, only when something actually changed.

Invariant (enforced in the `SubscriptionEvent` constructor): `Archive`/`Unarchive` events
carry empty context; `Update`/`CostChange` events carry non-empty context.

## Consequences

- The audit trail is a first-class read model: it is rendered on the subscription detail
  page (`templates/subscription/show.html.twig`).
- This is event-sourcing for audit only. Current state stays denormalized on the
  `Subscription`; events are not replayed to reconstruct state.
- Cost changes are deliberately separated from descriptive updates so billing history can
  be reasoned about on its own.
