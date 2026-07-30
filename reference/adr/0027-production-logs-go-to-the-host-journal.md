# ADR-0027: Production logs go to the host journal

- Status: Accepted
- Date: 2026-07-30

## Context

ADR-0026 removed the production image's anonymous volume, so nothing in the production stack is
mounted but the database's volume. That was the right call for everything it was aimed at, and it
left one thing without a home: `var/log`. Monolog's production handlers still wrote rotating files
there, and those files now die with the container.

No compose file set container log limits either, so a deployed stack ran on the daemon's default
`json-file` driver with unbounded growth. The beta host is a 40 GB disk shared with the PostgreSQL
volume. A disk-full condition there is both an outage and an unpleasant one to diagnose: PostgreSQL
behaves badly with no space, and the presenting symptom looks nothing like "container logs got
large."

Those are two separate problems, and the obvious fix for one does not touch the other:

- **Bounding.** A stack left running must not fill the disk.
- **Durability.** Logs must survive a container recreate, which is the event you most want to read
  them after and precisely the event that discards them.

Capping the `json-file` or `local` driver answers bounding alone. Both store their files under the
container id, so they cap disk usage while still losing the history on a recreate. Shipping logs to
a hosted service answers durability alone: the collector reads local output, so the local buffer
still lands on the same disk and still needs a cap.

Underneath both sits a third fact: none of it matters while Monolog writes files. A log driver
bounds a container's *output*, and a collector ships that output. Neither can see a file the
application wrote inside the container.

## Decision

**Production hands its log output to the container, and the container's output goes to the host's
systemd journal.**

- **Monolog writes to `php://stderr` in production**, through `stream` handlers rather than
  `rotating_file`. The channel split and per-channel levels are unchanged, and the framework channel
  stays buffered behind `fingers_crossed` so a normal request logs nothing at all.
- **Every service in the production chain declares the `journald` log driver** - the application, the
  worker, the database and the tunnel connector. The journal lives on the host, so it survives a
  container recreate, and `docker logs` still answers from it.
- **The size bound is host configuration, not repository configuration.** journald has no
  per-container cap; `SystemMaxUse` and `SystemKeepFree` in `/etc/systemd/journald.conf` are what
  actually keep the disk safe. Host provisioning owns them.
- **The driver is declared in the production and tunnel overlays, not the base compose.** One rule
  for both environments would be tidier, but the driver needs systemd on the *daemon* host, and both
  Docker Desktop and OrbStack run the daemon inside a VM that has none. A development stack would
  refuse to start rather than merely log somewhere else. Development stays on the daemon default,
  where frequent image churn keeps logs from accumulating.
- **No logs are shipped off the host.** See below.

### Not shipping logs to a hosted service, for now

Better Stack and Grafana Cloud both have free tiers that would take these logs, and both are real
options. Neither is adopted yet, because on its own merits a hosted log service is hard to justify
here: there is one host, and error tracking with breadcrumbs already covers most of what actually
gets read after an incident. Centralized logging earns its keep at more than one host, when the box
cannot be reached, or when the window needed has already fallen off local disk.

What would justify one is consolidation rather than logging: production also needs an external
uptime check, a dead-man's-switch for the recurring scheduler, and a status page. If a single vendor
covers those, log ingest riding along is a genuine gain - one dashboard, one alert channel, one
account to keep working. So the question is which monitoring vendor to adopt, with log ingest as a
tiebreaker, and it is answered when that monitoring is set up rather than here.

This decision does not foreclose it. Both vendors' agents read the systemd journal (Better Stack's
is Vector-based, Grafana's is Alloy), so shipping logs later is additive: the collector reads what
this decision already produces, and nothing here is undone.

## Consequences

- Production logs survive a container recreate, which settles the open consequence ADR-0026 recorded:
  that `var/log` is ephemeral and error tracking is therefore the only record of what happened.
- The disk cannot be filled by container logs, but **the setting that guarantees that is not in this
  repository**. A host provisioned without `SystemMaxUse` inherits journald's default of 10% of the
  filesystem, which on the beta host is roughly 4 GB of journal competing with the database. The
  deployment documentation says so, and host provisioning carries it as an explicit step.
- The compose contract check can assert that every production service declares the driver, and does,
  counted against the rendered service list so a service added later without one is caught. It cannot
  assert the bound, for the reason above.
- **journald drops messages past its rate limit rather than queuing them.** The default is 10,000
  messages per 30 seconds per service, and the worker - which logs every scheduler tick and every
  message handled - is the service most likely to reach it. A dropped log is worse than a bounded
  one, because nothing at read time indicates the record is incomplete. Raising or disabling the
  limit is a host provisioning step.
- Development and production now bound logs by different mechanisms, and development not at all.
  That asymmetry is forced rather than chosen: bounding development would mean the `local` driver
  with `max-size`, which is a different mechanism from production's, so the two could not share one
  rule even if the daemon supported journald.
- Log output for a console command run through the deploy wrapper reaches the journal twice, once
  from the console handler and once from the stream handler, since both destinations are now the
  container's own output. The console handler emits warnings and above at normal verbosity, so the
  overlap is small and covers only lines worth seeing twice. It is kept because it is what makes a
  command run through the deploy wrapper print its own log output to the terminal running it.

## Alternatives considered

- **`json-file` or `local` with `max-size` and `max-file`.** The obvious reading of "bound container
  log output," declarable in the base compose so development is bounded too, and assertable in the
  repository. Rejected because it answers only half the problem: both drivers store files under the
  container id, so a recreate still discards the history. Bounding logs that a redeploy deletes
  optimizes the wrong thing.
- **A named volume for `var/log`.** Would make the existing rotating file handlers durable with no
  application change. Rejected because it reintroduces exactly what ADR-0026 removed: a volume to
  identify, stop, copy and verify on every host move, and one that `php` and `worker` would each
  need explicitly. It also leaves container output itself unbounded, since the driver question is
  untouched.
- **Shipping to a hosted log service now.** Covered above. Deferred to the monitoring vendor
  decision rather than rejected, and this decision is what such a collector would read from.
