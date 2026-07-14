# ADR-0020: System settings as an app-global singleton

- Status: Accepted
- Date: 2026-07-14

## Context

Some configuration is global to the whole application rather than owned by a user: whether public
self-registration is open, and other operator switches that follow it. The operator flips these at
runtime from the admin area (ADR-0019), so the value must live somewhere it can change without a
redeploy, stay typed (the app enforces 100% type coverage and PHPStan level 9), and be read cheaply
from the paths that consult it.

This is a different shape from everything else in the domain. Per-user **preferences** are owner-scoped
(ADR-0015) and keyed by a ULID (ADR-0001); a system setting has no owner and is never referenced by a
foreign key or a URL. This ADR settles how that global configuration is modeled, stored, read, and
written. Which individual toggles exist, and the admin UI that edits them, are separate concerns.

## Decision

**A single-row `SystemSettings` entity - the app's one app-global, non-owner-scoped record - read
through the query bus and mutated through per-setting commands.**

- **Structural singleton.** The primary key is a `smallint` pinned to `1` by a `CHECK (id = 1)`
  constraint (raw SQL in the migration, like the `UserEmail` partial unique indexes Doctrine attributes
  cannot express). With the primary key, the table can hold at most one row, so "there is exactly one"
  is enforced by the schema, not a convention. The single row is **seeded by the creating migration**,
  so reads never have to create it.
- **Not owner-scoped, and not ULID-keyed.** A system setting belongs to the application, not a user, so
  ADR-0015's per-row ownership does not apply - that rule isolates users from each other, not the
  operator from global configuration. ADR-0001's ULID rationale (non-guessable URLs, cross-table
  references, cross-table sortability) does not reach a singleton that is never referenced or exposed,
  so a fixed `smallint` is the honest key.
- **Typed members, no key-value bag.** Each setting is a typed `public private(set)` property read
  directly (`$settings->publicSignupEnabled`), mutated by one intention-revealing typed method
  (`changePublicSignup(bool)`). There is no `get(string): mixed` / `set(string, mixed)` accessor: that
  would forfeit type coverage and column-level constraints, reintroduce stringly-typed keys, and be the
  generic setter the code guidelines forbid.
- **Read through one seam.** `GetSystemSettingsQuery` runs through the query bus into
  `SystemSettingsRepository::get()`, which owns the `find(1)` and returns the typed entity. `get()` is
  the only accessor callers use; the inherited Doctrine finders are not (an architecture test guards
  this). Writes go through per-setting commands (e.g. `SetPublicSignupCommand`) whose handler loads
  `get()`, calls the domain method, and flushes. Data access stays in the handler layer (ADR-0006/0007).
- **Reads stay pure; caching is deferred behind the seam.** The row is seeded, so a read never writes.
  Doctrine's identity map already collapses repeated reads within a request to a single query. If a
  setting ever becomes hot enough that the per-request read matters across requests, a cache decorates
  the single query seam (`get()` / the query runner) without touching call sites; it is not built now.

## Consequences

- Adding a setting is additive and type-safe: one typed column plus its migration, one typed mutator,
  and - only if it is edited in the UI - one form field and one command. Nothing shared grows, and every
  setting is PHPStan-checked and constraint-backed at the column.
- `SystemSettings` is the first app-global entity. The owner-scoping rule for user data is unchanged;
  this record is the documented exception, not a loosening of it.
- "Exactly one row" cannot be violated from the application or the database, and a fresh deploy is
  closed by default (the seeded `public_signup_enabled` is false).
- The read seam is a single choke point, so a future performance need (caching) or a future consumer
  (the public-signup flow) attaches in one place.

## Alternatives considered

- **A key-value settings table** (`key` string, `value` mixed/JSON). Rejected: it throws away type
  coverage and per-column constraints, reintroduces stringly-typed keys, and turns every read into a
  runtime-typed lookup - the opposite of the typed-members goal, for settings defined in code rather
  than by users at runtime.
- **A ULID primary key** to match every other entity. Rejected: ADR-0001's reasons do not apply to a
  never-referenced singleton, and a ULID invites "which row?" ambiguity that the fixed-id `CHECK`
  constraint forecloses.
- **An environment variable or config file.** Rejected: it is not flippable at runtime from the admin
  UI without a redeploy, which is the whole point of a runtime toggle.
- **An eagerly-loaded settings service or a cache, built now.** Rejected as premature: no path reads
  settings often enough to justify it, a single-row primary-key read is negligible, and the query seam
  lets a cache be added later exactly when a measurement calls for it.
