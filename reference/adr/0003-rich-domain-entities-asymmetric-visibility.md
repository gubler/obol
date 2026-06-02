# ADR-0003: Rich domain entities with asymmetric-visibility immutability

- Status: Accepted
- Date: 2026-06-02

## Context

Doctrine entities easily degrade into anemic data bags with public getters and setters,
scattering business rules into services and handlers. We want invariants and state
transitions to live with the data they govern.

## Decision

Entity properties use PHP's asymmetric visibility (`public private(set)`): readable from
anywhere, writable only from inside the class. State changes go through intention-revealing
domain methods rather than setters - `Subscription::update()`, `archive()`, `unarchive()`,
`recordPayment()` - and constructors enforce invariants up front (e.g. non-empty name,
positive cost and period count, positive payment amount) using `beberlei/assert`.

## Consequences

- Callers read properties directly (`$subscription->cost`) without getter boilerplate, but
  cannot mutate them.
- Invariants are enforced in one place, at construction and in the domain methods.
- Known exception: `Category` exposes a `setName()` method rather than funneling through a
  named domain method. This is a minor, tolerated inconsistency, not a second pattern - new
  entities should follow the `Subscription` style.
