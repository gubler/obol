# ADR-0006: CQRS command/query buses with data access confined to the handler layer

- Status: Accepted
- Date: 2026-06-06

> Note (2026-06-12): the absence of an event bus recorded here (removed in #76) is superseded by
> ADR-0011, which reinstates a synchronous `event.bus` for domain events. The command/query buses
> and the data-access boundary below stand unchanged.

## Context

The 2026-06-02 survey (epic #74) flagged the CQRS read path as ceremony outpacing the
domain: a read runs controller -> FindXQuery -> query bus -> FindXRunner -> repository,
and the runner only wraps `$repo->find()` with a ULID guard. The query bus carries no
transactional middleware (its `validation` middleware runs against constraint-free,
one-field messages), so it looked like a second, shallow seam on top of the Doctrine
repository, which is already the read seam. #79 grilled whether to collapse it; #80
covers the symmetric command side. CONTEXT.md recorded the pattern as under review
pending these conversations.

## Decision

Keep the command and query buses (Symfony Messenger). They are retained for developer
familiarity with the Messenger CQRS pattern, consistency across the maintainer's other
Symfony apps, and the value of an enforced architectural convention - not for any
hypothetical multi-user future (the app remains single-tenant per ADR-0004; the buses
merely do not foreclose that direction).

The buses define a boundary: **data access is confined to the handler layer.**

- Repositories and `EntityManagerInterface` may be reached only from `App\Message`
  (command handlers, query runners, and the scheduler handler).
- Every caller - controllers, console commands, services - reaches data only through
  the command and query buses.
- Entities reference their repository class as `#[ORM\Entity]` metadata only; they
  perform no data access.
- Tests are exempt: they may inject repositories and the EntityManager to set up state.

This boundary is enforced by a Pest architecture test (`toOnlyBeUsedIn`), so any new
caller namespace is covered automatically.

Read query messages carry domain identifier value objects (`Ulid`), not strings. The
HTTP boundary converts route parameters via Symfony's built-in UID value resolver, so a
malformed identifier is rejected at the boundary and an invalid query is unconstructable
by the type system - removing the per-runner `Ulid::isValid` guard. The runners stay
synchronous; nothing is serialized to a transport, so there is no reason to carry the
identifier as a string and rehydrate it in every handler.

## Considered options

- **Drop the query bus; controllers call repositories directly.** Rejected: it would
  break the symmetry and the enforced convention the buses exist to provide, for a
  saving the single repository seam does not need.
- **Introduce a dedicated read-model / finder service layer.** Rejected as
  over-engineering: there are no denormalized read models or query loads the entity
  repositories cannot serve.
- **Keep the buses (chosen).**

## Consequences

- Command handlers, query runners, and the scheduler handler are the only data-access
  points. The scheduler's handler reads its repository directly as a write-side read
  (loading aggregates to mutate them); it is not required to route that through the
  query bus.
- The query bus's `validation` middleware is a deliberate no-op kept for command/query
  parity; query messages carry no constraints because their `Ulid` fields cannot hold
  an invalid value.
- Read messages are typed with `Ulid`; the per-runner ULID guard is removed. A malformed
  path identifier returns 404 via the value resolver; a valid-but-absent identifier
  returns 404 via the controller's existing not-found handling - two distinct mechanisms,
  cleanly separated.
- The architecture test, the read-path refactor, and a CLAUDE.md note pointing here are
  tracked in #100. Enforcement lands after this ADR, which is expected: the ADR records
  the decision, the follow-up issue realizes it within epic #74.
- #80 narrows: the command bus is retained per this ADR, leaving its grill scoped to
  DTO/Command duplication and the `Subscription::update()` value object.
