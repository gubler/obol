# ADR-0015: Multi-user via per-row ownership

- Status: Accepted
- Date: 2026-07-04

## Context

ADR-0004 recorded Obol as a single-tenant, no-authentication personal tool. The multi-user effort
(epic: Phase 1.1) reverses that. ADR-0014 already superseded ADR-0004's authentication half: accounts
now exist and the app requires passwordless magic-link login. This ADR supersedes the other half - the
data-ownership stance - and records how a logged-in user comes to see only their own data.

The constraint that shapes everything below: each user gets a private, isolated dataset. There is no
data sharing between users (households and sharing are explicitly out of scope; they can grow from the
`owner` seam later if a real need appears). The domain already routes all data access through the
command/query buses with the handler layer as the sole data-access boundary (ADR-0006), which gives
ownership a natural place to live.

## Decision

### Isolated tenancy via an explicit, immutable `owner` FK

Every owned entity carries one `owner` - a `User` - as an explicit foreign key. There is no tenant
table and no Doctrine SQL filter. Ownership is enforced where data access already lives: the
repository finders are owner-scoped (`findForOwner(Ulid $id, Ulid $ownerId)` returns `null`
cross-owner, so the controller 404s), and the handler layer passes the current user's id straight to
those finders. This keeps ownership visible in the code path a reader is already following (ADR-0006's
boundary) rather than hiding it in implicit global query state.

The finders scope by the owner's `Ulid`, not a loaded `User`: reads and writes-on-existing-rows only
need to match the foreign key, so threading the id through avoids a per-request user lookup and the
same resolve-or-throw boilerplate in every handler. The one place that needs the `User` object is
creation - `Subscription` sets its `owner` at construction - so `CreateSubscriptionHandler` resolves
the id to a `User`; every other handler and runner passes the `Ulid` on to the finder.

`owner` is immutable (`public private(set)`, set at construction, never reassigned). A subscription is
never moved between users. Immutability is what makes the `Payment` denormalization below safe and
what lets the ownership check be a simple equality rather than a moving target.

### Which entities own, and how

- **Direct `owner` FK:** `Subscription`, `Payment`, `Category`, `PaymentSource`, and
  `ObligationSnapshot`. `ExchangeRate` never gets an owner - FX rates are global reference data shared
  by everyone.
- **`Payment.owner` is denormalized, derived at construction.** A payment copies its owner from its
  subscription in its own constructor (`$this->owner = $subscription->owner`), so the invariant
  `Payment.owner == Payment.subscription.owner` cannot be violated by any caller: there is no owner
  parameter to pass wrong. This is copy-at-birth with no sync logic, safe precisely because `owner` is
  immutable and payments are never reparented. The denormalization exists so "all of a user's payments
  last month" is a single owner-scoped query rather than a join through `Subscription`.
- **`SubscriptionEvent` inherits its owner via its subscription** and gets no FK of its own. It is part
  of the subscription aggregate and is never queried in isolation, so a scoped finder on the parent is
  sufficient.

### No system user and no audit-actor column

Generated payments stay distinguished by `PaymentType::Generated`; there is no `System` user and no
`actor_user_id` on the event-sourced `SubscriptionEvent`. Actor attribution only becomes meaningful
with sharing or admin impersonation, and neither exists yet. It is a clean retrofit when it does
(nullable `actor_user_id`; historical rows are self-authored by definition), so it is deferred to
Stage 2 rather than paid for now.

## Considered options

- **Doctrine SQL filter (global `owner = :current_user` filter).** Rejected. It hides the tenancy
  boundary in implicit, request-global state that the handler layer cannot see, fights the ADR-0006
  boundary (which deliberately confines data access to visible finders), and is easy to forget to
  enable or to bypass with a native query. An explicit owner-scoped finder is the same amount of code
  and keeps the check where a reader already looks.
- **Shared / household tenancy (a `Tenant` many-to-many).** Rejected as out of scope. Isolated per-user
  data is the requirement; sharing can grow from the `owner` seam later without a rewrite.
- **A `System` user and an audit-actor column.** Rejected for now (see above); deferred to Stage 2 with
  impersonation.

## Consequences

- `Subscription` and `Payment` carry an immutable `owner`. Subscription/Payment repository finders are
  owner-scoped; cross-owner access returns `null` and 404s. Commands and queries for these entities
  carry an `ownerUserId` (`Ulid`, per ADR-0007); controllers supply it from a `currentUser()` helper,
  and handlers pass it to the owner-scoped finders (only creation resolves it to a `User`).
- The global nightly payment-generation sweep stays global - it iterates every user's due
  subscriptions - and each generated `Payment` takes its owner from its subscription for free via the
  constructor derivation, so the sweep needs no owner threading.
- An architecture test enforces the threading: every Subscription/Payment (and owner-scoped Report)
  message must carry `ownerUserId`, with the global sweep command as the one documented exemption
  (mirroring the data-access-boundary arch test from ADR-0006).
- The founder migration is ordered and irreversible: add the FK columns nullable, insert the founder
  `User` plus a primary verified `UserEmail`, backfill all existing rows to the founder, then flip the
  columns to `NOT NULL`. It must not run until the prod mailer is live and `app:mailer:smoke` passes,
  or the founder's first magic-link login locks them out.
- ADR-0004 is fully superseded: its authentication half by ADR-0014, its data-ownership half by this
  ADR.
