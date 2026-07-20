# ADR-0016: Renewal dates are timezone-naive, interpreted in the owner's zone at read time

- Status: Accepted
- Date: 2026-07-04
- Refined by: ADR-0021

> **Refined by ADR-0021.** This decision stands, but its enforcement changed. The naive frame was
> encoded only by convention (a `\DateTimeImmutable` in a `timestamp` column), and the very hazard this
> ADR warned about - comparing the naive value against a zoned instant - shipped in four places.
> ADR-0021 makes the frame a type (`CalendarDate`): `User::toLocal()` becomes `User::localDateFor()`
> returning a `CalendarDate`, and the naive/zoned boundary is now un-writable without naming a zone.
> `nextRenewal` and `paid_date` are now `DATE` columns (they were `timestamp`); `recordedAt` and `asOf`
> were already `DATE`.

## Context

Users are per-user timezone-aware (`User.timezone`, from the per-user-settings slice), but nothing
consumed the field: the container's PHP `date.timezone` is UTC, so every "today" and calendar-boundary
computation ran in UTC. For a user behind UTC, the once-daily payment-generation sweep crossed UTC
midnight while they were still on the previous local day, so a due payment appeared a day early; users
ahead of UTC saw the opposite. Relative renewal labels ("Today / in N days"), report period boundaries,
and the "renewal must be in the future" validation were all UTC-anchored too.

A `nextRenewal` is a date the user picks ("the bill renews on the 1st"). It is stored in a
`timestamp without time zone` column, always at midnight (the date-picker form submits a bare date). The
question this ADR settles: what does that stored value *mean* across timezones, and where is the
timezone applied?

## Decision

**`nextRenewal` is a timezone-naive calendar date whose meaning is the owner's local date, resolved
against the owner's *current* timezone at read time.** The stored value is never an absolute UTC instant
and never carries an offset. Every place that needs "the user's today" converts the application clock's
current instant into the owner's zone and compares dates there:

- **The conversion lives on `User`.** `User::toLocal(\DateTimeImmutable): \DateTimeImmutable` re-expresses
  an instant in the user's zone (a thin wrapper over `setTimezone`). `User` owns the `timezone` field, so
  the conversion knowledge lives with it rather than scattered across call sites.
- **Read side (PHP).** The report runners and the `renewal_label` Twig filter resolve "now" via
  `$owner->toLocal($clock->now())` and feed that to `PeriodBoundaries` / the label diff. The subscription
  entity's `automatePayments`/`suggestedResumeRenewal` take the current instant as a parameter (the caller
  passes the injected clock) and apply `$this->owner->toLocal()` themselves, so the "future renewal"
  invariant stays enforced in the entity, in the owner's local today, while staying deterministically
  testable. The entity compares in a single naive frame - the owner's local date reduced to a naive
  midnight, matching how `nextRenewal` is stored - rather than one naive and one zoned value, which would
  be off by the owner's offset near midnight.
- **Generation (SQL).** The generation sweep filters at the query level with the owner's zone applied in
  Postgres: `next_renewal <= (now AT TIME ZONE user.timezone)`, via a registered DQL `AT_TIME_ZONE`
  function joining `user`. "Now" is bound from the injected application clock, not the DB clock, so
  generation keys off application time and is deterministically testable.

The clock instant always originates from the injected `ClockInterface` and is passed in (to runners, to
the entity methods) rather than read from a global inside domain code; only its *interpretation* becomes
per-user. Stored timestamps (`nextRenewal`, `ObligationSnapshot.recordedAt`) are never rewritten to local
time.

## Considered options

- **Store `nextRenewal` as a UTC instant computed from the user's zone at write time.** Rejected. It bakes
  the offset into the row, so a later timezone change or a DST transition would silently shift the stored
  due date; it also needs the write path to know the zone and re-derivation on every zone change. Naive
  storage plus interpret-at-read means the same date simply re-reads correctly after any such change, with
  nothing to migrate.
- **Keep everything UTC (the status quo).** Rejected: the sweep and every label are wrong by up to a day
  for any non-UTC user.
- **Interpret against a fixed offset captured at write time rather than the named zone.** Rejected: a fixed
  offset does not survive DST. A named zone with `AT TIME ZONE` / `setTimezone` handles DST transitions for
  free.

## Consequences

- A timezone change or DST transition re-interprets existing `nextRenewal` values; no stored data is
  migrated or rewritten. This is the main payoff of naive storage.
- Payment generation runs hourly (not daily), so each zone's local-midnight rollover is caught within the
  hour, and the generation query filters in SQL instead of loading every subscription and filtering in PHP.
- `AT_TIME_ZONE` is the repo's first custom DQL function; it is a thin, reusable primitive over a standard
  Postgres operator, available to future reports that need to filter by a user's local date in SQL.
- Generation stays a single synchronous sweep on the scheduler consumer (no async transport, no locking):
  idempotency comes from re-selecting live and `recordPayment` advancing `nextRenewal`, which is sufficient
  under the single sequential worker. A future concurrent worker would need a claim/lock; that is
  deliberately deferred.
