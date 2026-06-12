# ADR-0011: Reinstate the synchronous event bus for domain events

- Status: Accepted
- Date: 2026-06-12

## Context

ADR-0006 kept the command and query buses and confined data access to the handler layer. It did
not mention an event bus because there wasn't one: a Messenger event bus and its async transports
had been removed in #76 - not as unwanted, but as then-unused (nothing dispatched domain events).

The obligation-snapshot recorder (ADR-0010, #142) is the first real consumer. When a subscription
is created, updated, archived, unarchived, or deleted, the per-currency obligation must be
recomputed and a snapshot appended if it changed. The write that triggers this lives in the command
handlers; the reaction (recompute + record) is a separate concern that should not be wired into
each handler's body. That is exactly what a domain event decouples.

The open question was *how* to carry the event. Two channels were possible: Symfony's framework
`EventDispatcher`, or a Messenger event bus. And two timings: synchronous, or async on a transport
drained by the scheduler worker.

## Decision

**Reinstate a Messenger `event.bus`, handled synchronously, in-process.** This reverses the
event-bus removal recorded against #76; the rest of ADR-0006 (command/query buses, the data-access
boundary) stands unchanged.

- **Messenger, not `EventDispatcher`.** Keeping domain events on a Messenger bus means one mental
  model - "everything is a message on a bus" - rather than splitting the "something happened ->
  react" flow across two unrelated mechanisms.
- **Synchronous, not async.** The reaction is cheap (one `SUM`-shaped query plus at most one
  insert), so there is no latency to hide on a worker. More importantly, async would *lose
  accuracy*: a worker that recomputes "current obligation" at drain time collapses two rapid edits
  into one row, dropping the intermediate level, whereas synchronous handling records each change
  in order. An async transport can be added later if a genuine need appears; nothing here forecloses
  it.
- **Deferred to after commit.** Command handlers announce the event with a
  `DispatchAfterCurrentBusStamp` (the command bus already carries `dispatch_after_current_bus`
  middleware), so the subscriber runs only once the command's `doctrine_transaction` has committed
  and reads committed state. `event.bus` carries its own `doctrine_transaction`, so the snapshot
  write commits in a **separate** transaction - a subscriber failure cannot roll back the edit that
  triggered it.

`event.bus` middleware: `dispatch_after_current_bus`, `doctrine_transaction`. It deliberately omits
the `validation` middleware the command/query buses carry for parity - domain events carry no
user-supplied constraints. Handlers on `event.bus` are within `App\Message` and so honor ADR-0006's
data-access boundary; they do not flush (the middleware owns the transaction - see
`docs/development/standards.md`).

## Considered options

- **Synchronous `EventDispatcher`** - rejected. It works and stays in-process, but it bifurcates the
  data flow across Messenger and the framework dispatcher; the single-channel consistency of a
  Messenger bus is worth more than avoiding the bus reinstatement.
- **Async event bus drained by the scheduler worker** - rejected for now. No latency to hide, and
  drain-time recomputation collapses rapid sequential changes and loses intermediate levels. The
  failure-isolation it would buy is already obtained synchronously via the separate post-commit
  transaction.
- **Strict handler requirement vs `allow_no_handlers`** - kept strict. The one event today has a
  handler; strictness still catches a misrouted message. Revisit if events with optional subscribers
  appear.

## Consequences

- A third bus exists again. Strictly one event (`SubscriptionsChanged`) and one handler today; the
  bus is the seam for future domain events.
- The deferral + transaction isolation is centralized in `SubscriptionChangeNotifier`, so the
  easy-to-forget `DispatchAfterCurrentBusStamp` lives in exactly one place.
- ADR-0006's event-bus removal is superseded on that one point; its core decisions are unaffected.
