# ADR-0007: Write-path message conventions - DTOs stay separate from Commands; Commands carry Ulid

- Status: Accepted
- Date: 2026-06-06

## Context

Follow-on from ADR-0006, which kept the CQRS command/query buses and confined data
access to the handler layer. The #80 grill (survey candidates 3 and 4) examined the
write path, which the 2026-06-02 survey flagged as ceremony: a write runs form -> DTO
-> Command -> Handler -> Entity, with the DTO and Command hand-copied in the controller,
and `Subscription::__construct` / `update()` carry nine parameters each.

Reading the code corrected the premise. The DTO and Command are not near-identical
carriers - they sit on opposite sides of two real transformations (a `Category` object
becomes a `categoryId`, an `UploadedFile` becomes an uploaded path) plus a mutability
gap (the DTO is form-mutable and carries validation constraints; the Command is
`readonly`). Only the scalar pass-through fields are copied verbatim.

## Decision

**Form DTOs stay separate from Commands.** The DTO is the form boundary: mutable,
constraint-bearing, holding framework/HTTP shapes (the `Category` entity from an
`EntityType`, the `UploadedFile`). The Command is the handler message: immutable,
carrying scalars and identifiers. Binding forms directly to Commands was tried in the
past and repeatedly produced edge cases - it fights the Command's `readonly`-ness (the
form data mapper writes into properties), it leaves the file-upload side effect homeless
(the Command holds a path, the form holds an `UploadedFile`), and it forces the Command
to hold an entity instead of an id. The apparent duplication is the price of a genuine
boundary, and it keeps each object focused on the one job it actually does.

**Commands carry `Ulid` value objects, never Doctrine entities.** This extends ADR-0006's
"messages carry `Ulid`, not stringified ids" from reads to writes, so it is one
convention across both buses. The controller passes `$entity->id` (already a `Ulid`)
directly; the handler resolves it to the aggregate via its repository. The
`->toRfc4122()` / `Ulid::fromString()` encode-decode dance is removed. Entities are kept
off the bus deliberately: a serialized entity can drift between dispatch and handling
(the standard Symfony Messenger caution) - mostly an async hazard, but worth defending
against by default - and resolving the id in the handler keeps data access in the handler
layer per ADR-0006.

**No value object for the entity attributes (candidate 4).** The nine-parameter
`__construct` / `update()` signatures stay. Every call site already uses named arguments,
so there is no positional-ordering fragility for a value object to fix, and a
`SubscriptionAttributes` object cannot span the command boundary anyway (the command
carries a `Ulid`, the entity method needs a resolved `Category`), so it would only clean
the handler-to-entity hop while adding a class and a mapping step. The one real
duplication - the invariant block (trim name, assert name non-empty, cost and period
count positive) copied between `__construct` and `update()` - is removed with a private
method on the entity, not a new type.

## Considered options

- **Bind forms directly to Commands.** Rejected: recurring edge cases (readonly mapping,
  homeless file upload, entity-vs-id shape).
- **Carry the resolved `Category` on the Command.** Rejected: entity staleness between
  dispatch and handling, breaks read/write symmetry (queries carry `Ulid`, not
  entities), forecloses ever queueing the command, and blurs the data-access boundary.
- **`SubscriptionAttributes` value object for `__construct` / `update()`.** Rejected as
  over-engineering for the present duplication; a private invariant method covers it.

## Consequences

- All create/update commands carry `Ulid` ids (`categoryId`, `subscriptionId`,
  `paymentId`). Controllers drop `->toRfc4122()`; handlers drop `Ulid::fromString()` and
  fetch with `$repo->find($command->id)`. This is implemented together with the read-path
  `Ulid` typing in #100, so "messages carry `Ulid`" lands as one atomic change across
  reads and writes rather than two issues editing the same controllers.
- The DTO layer is retained on purpose; it is not redundancy to be removed later.
- The `Subscription` invariant block is deduped into a private method, no value object,
  tracked in #102.
- The command bus and its `doctrine_transaction` middleware are unchanged (retained per
  ADR-0006).

## Note (2026-06-11, #128)

"No value object for the entity attributes" above rejects a *composite* `SubscriptionAttributes`
object that would bundle the whole nine-parameter signature. It does not bar a value object as the
type of an individual attribute. When `cost` and `amount` became `Money` (#128, part of the
multi-currency foundation #126), the `__construct` / `update` / `recordPayment` signatures kept
their arity - `Money` simply replaced `int` for one field - so the candidate-4 rejection still
holds. The commands continue to carry a scalar `int` cost/amount (no currency input exists until
the picker lands, #129); the handler wraps it in `Money` before calling the entity, keeping the VO
off the bus per the `Ulid`-only convention above.
