# ADR-0026: Deploy-durable state lives in PostgreSQL

- Status: Accepted
- Date: 2026-07-29

## Context

The production image declared an anonymous Docker volume at `/app/var`. No compose file names a
volume at that path, and Compose *reuses* anonymous volumes when it recreates a container. The
consequences compounded in a way that had no correct setting:

- After pulling a new image, the previous release's compiled cache and built CSS shadowed the ones
  baked into the new image, so the upgrade silently did not take effect.
- Renewing the volume to fix that discarded the scheduler's missed-run state and the logs instead.
- `php` and `worker` each got their own anonymous volume, so anything one wrote the other could not
  see. `messenger:stop-workers`, which signals through a cache pool, was inert for exactly this
  reason.

Sessions landed in PHP's default save path inside that same volume, so a redeploy signed every user
out. The 30-day remember-me cookie masked it rather than fixing it, and doing so silently downgraded
whatever authentication had produced the session - a passkey assertion became a cookie.

The magic-link replay guard (`used_link_cache`, ADR-0014) sat on the filesystem-backed application
cache pool. A cache clear wiped it, re-arming every already-redeemed single-use link for the
remainder of its lifetime.

Underneath all of this sits a hosting constraint. Obol is moving to an external host, and will move
again from beta hardware to rented infrastructure. Every volume in the stack is something that has to
be identified, stopped, copied, and verified on each of those moves. A database is something a dump
and a restore already handle - and that procedure is being built and restore-tested regardless,
because it is the backup story.

## Decision

**Anything that must outlive a container lives in PostgreSQL. Everything else is ephemeral and comes
from the image.**

Concretely:

- **Sessions** are stored by Symfony's `PdoSessionHandler` in a `sessions` table, configured by
  putting the database DSN directly in `framework.session.handler_id`. The handler opens its *own*
  lazy PDO connection rather than sharing the ORM's: its default transactional locking holds a row
  lock for the life of the request, and sharing that with the request's business transaction would
  make each block the other.
- **The application cache pool** is `cache.adapter.doctrine_dbal` on the default Doctrine
  connection, in a `cache_items` table. This is the pool that holds the scheduler's missed-run
  checkpoint and the magic-link replay guard. `cache.login_link` and the production result-cache pool
  inherit the backend without further wiring.
- **The system cache pool stays on the filesystem**, keyed by the container build id. It caches what
  the image already contains - compiled metadata, the asset map, the ORM query cache - so discarding
  it with the container is correct rather than a loss. Moving it would add a round trip to data the
  image ships and would put container boot behind database availability.
- **Both tables are created by a migration**, not by the adapters' own auto-create. The runtime
  database role should not need DDL rights, and on PostgreSQL the auto-create fallback cannot work
  anyway: the statement that hit the missing table has already aborted the surrounding transaction,
  so the `CREATE TABLE` attempted from the exception handler fails too.
- **The production image declares no volume**, and the production stack mounts nothing but the
  database's. The three named volumes the stack used to carry (Caddy's state and config, uploads)
  moved into the development-only compose overlay.
- **`prefix_seed` is pinned.** Left unset, Symfony derives the cache namespace from the project
  directory and the generated container class, which would tie now-durable rows to a filesystem path.
  An explicit seed is also the only lever for discarding the pool wholesale, which matters once a
  payload serialized by one release survives into the next.

This extends ADR-0005, which established PostgreSQL as the database of record for domain data, to
cover operational state as well.

### Reintroducing a dedicated cache or session store

If the database ever becomes the wrong home for this, the replacement is Redis or Valkey - but it
comes back against evidence rather than intuition. The trigger is one of:

1. Connection saturation - the session handler opens a second connection per request, and the ceiling
   is set by FrankenPHP's thread pool rather than by traffic, so this shows up as connection refusals
   under a thread count that has actually been raised.
2. Session write contention visible in `pg_stat_activity`, rather than suspected.

The change itself is small - `handler_id` takes a DSN, and `framework.cache.app` takes an adapter
name - and needs no data migration, because sessions are disposable by nature and the cache is a
cache. What it costs is the thing this decision buys: a second stateful service to move, back up, and
monitor. Weigh that, not the config diff.

## Consequences

- The stack has exactly one stateful component. A host move is a dump and a restore, using the same
  procedure as the backups, rather than a hand-copied set of volumes.
- Pulling a new image and recreating containers actually picks up the new compiled assets.
- A signed-in session survives a redeploy, so users are not silently downgraded onto the remember-me
  cookie, and a redeemed magic link stays redeemed across a cache clear.
- `messenger:stop-workers` reaches the worker container for the first time, because the pool it
  signals through is now shared rather than per-container.
- **`var/log` is ephemeral.** Logs written inside the container die with it. Production therefore
  does not write them: Monolog hands its output to the container, and the container's output goes to
  the host's systemd journal, which outlives a recreate. ADR-0027 records that decision and the
  reasoning behind it.
- **Uploaded files have no durable home.** Uploads are disabled for launch, and the volume that used
  to back them is development-only now. Re-enabling them requires object storage or a database
  column - not a volume, which would reintroduce exactly what this decision removes.
- Two tables grow without bound until something prunes them. The application pool's adapter is
  pruneable and the session handler garbage-collects, but neither runs on its own schedule here.
- A failed migration is more serious than it was. The entrypoint runs migrations before starting the
  server, so ordering is correct by construction, but a failure that used to degrade gracefully now
  means every request touching a session throws.

## Alternatives considered

- **Redis or Valkey.** Purpose-built, with native expiry that would remove the pruning question
  entirely. Rejected because it re-creates the problem this decision exists to remove: a second
  stateful service to move, back up, and monitor. Without persistence a restart signs everyone out;
  with persistence it is a volume again. A misconfigured eviction policy would silently evict live
  sessions. See the reintroduction criteria above.
- **A named volume for `/app/var`.** Fixes the stale-cache bug, since a named volume can be recreated
  deliberately, but leaves a volume to identify and copy on every host move and keeps `php` and
  `worker` needing to share it explicitly.
- **Cookie-borne sessions.** No server-side state at all. Rejected: Symfony ships no handler for it,
  a serialized security token does not reliably fit in a cookie, there would be no server-side
  invalidation, and the passkey registration flow keeps state in the session across requests.
