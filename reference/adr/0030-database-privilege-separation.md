# ADR-0030: The application connects as a database role that cannot change the schema

- Status: Accepted
- Date: 2026-08-08

## Context

The application connected to PostgreSQL as the database owner and ran DDL migrations on every
container start. On a private, single-user instance that is a reasonable trade. On a public host it
means a SQL injection, or code execution in the PHP process, reaches the entire database rather than
the rows a request legitimately touches: tables can be dropped, the schema altered, anything read or
rewritten.

Two things made this the moment to fix it. Obol is moving to an external host, and the production
schema does not exist yet - retrofitting privilege separation onto a database whose tables are
already owned by the wrong role, with live data in them, is materially harder than establishing it on
a schema that has not been created.

ADR-0026 also moved sessions and the application cache pool into PostgreSQL, and both adapters
(`PdoSessionHandler`, `DoctrineDbalAdapter`) attempt a `CREATE TABLE` of their own when they meet a
missing table. Neither exposes a setting to turn that off. So "the tables are owned by a migration"
was, on its own, a convention rather than a guarantee.

## Decision

**Three roles, and the application gets the weakest one.**

| Role | Attributes | Used by |
|---|---|---|
| `${POSTGRES_USER}` | superuser, created by `initdb` | the provisioning script, break-glass, restore |
| `obol_owner` | `LOGIN NOSUPERUSER CREATEDB`, owns the database | the `migrations` DBAL connection |
| `obol_app` | `LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE` | everything else - Doctrine, sessions, the cache pool |

The owner is deliberately **not** a superuser. Its credential sits in the application containers'
environment so that a migration can be run by hand, and a superuser credential there would turn code
execution in PHP into code execution in the database container by way of `COPY TO PROGRAM`. The
bootstrap role stays a superuser because `initdb` creates exactly one, and demoting it would leave
the cluster with none - which is unrecoverable rather than merely inconvenient.

`CREATEDB` on the owner exists for one reason: the test suite drops and recreates its own database on
every run. Creating a database cannot reach data in another one.

On PostgreSQL 15 and later the `public` schema is owned by `pg_database_owner` and `CREATE` on it is
already revoked from `PUBLIC`, so `ALTER DATABASE ... OWNER TO obol_owner` is what grants the owner
role `CREATE` while leaving the runtime role with `USAGE` alone.

### Two declared connections, not an environment swap

`doctrine.dbal.connections` declares `default` (runtime) and `migrations` (owner), and
`doctrine_migrations.connection` selects the latter. The alternative - having the container entrypoint
swap `DATABASE_URL` around the migrate command - is fewer lines but breaks the case that matters
most: a human running `doctrine:migrations:migrate` by hand during an incident gets the runtime role
and a confusing permission error. A declared connection makes the correct role automatic wherever the
command runs, which is why `MIGRATION_DATABASE_URL` reaches the worker container as well as the web
one.

### Generating a migration runs entirely as the owner

Two things follow from the split, and `doctrine:migrations:diff` needs both.

Selecting a connection rather than an entity manager costs the migrations dependency factory its ORM
schema provider, and `diff` compares the entity mapping against the database. The bundle's `services`
key hands one back (`App\Doctrine\Migrations\EntitySchemaProvider`), built on the application's own
entity manager - which is also what keeps `diff` quiet about `sessions`, `cache_items` and
`messenger_messages`, since the listeners that contribute those tables to the mapping-derived schema
are registered against it.

Those same listeners settle "do these two connections point at the same database?" by **creating a
probe table** on the connection their adapter uses - the application's. Building the mapping-derived
schema therefore needs DDL rights that the runtime role does not have, and no amount of wiring on our
side changes which connection the probe runs on. So `mise run migration:diff` overrides `DATABASE_URL`
with the owner's for the length of the command.

That is a task rather than a documented flag because the failure is opaque: it names
`schema_subscriber_check_`, a table nobody has heard of, and points at the application connection
rather than at anything to do with migrations. It is also the right shape conceptually - generating a
migration is schema work, so it runs as the role that owns the schema.

Only `diff` is affected. `doctrine:migrations:migrate`, `up-to-date` and `status` take the owner
connection from configuration and need no override, and `doctrine:schema:validate --skip-sync` (which
CI runs) never builds the schema, so it passes as the runtime role.

### Default privileges, set in `template1` as well as in the live database

PostgreSQL grants a role nothing on tables created *after* it. Without `ALTER DEFAULT PRIVILEGES`,
every future migration that adds a table breaks the application at runtime until someone grants it by
hand - and the failure presents as an application bug rather than a permissions problem. This is not
polish; it is what makes the arrangement survive ordinary development.

Those defaults live in `pg_default_acl`, which is **per-database**, and `CREATE DATABASE` is a
physical copy of `template1`. The provisioning script therefore applies them to `template1` as well,
so every database created afterwards inherits them. The test suite drops and recreates its database
on every single run; without this it would start each time with a runtime role that has rights on
nothing. Production never exercises that path - only one database is ever created there, and it is
handled by name - so nothing about the deployment depends on the template. It is what keeps
development and CI honest.

### One provisioning script, four callers

`docker/db/init/10-roles.sh` is run by the postgres image from `/docker-entrypoint-initdb.d` on a
fresh cluster, by `mise run db:roles` on a cluster that already exists, by CI against its service
container (which cannot mount an initdb hook), and by hand as part of a restore. It is idempotent:
roles are created only when absent, and every attribute, password and grant is re-applied on each
run, so re-running it is the supported way to converge a cluster and the way a rotated password takes
effect.

It also takes ownership of everything already in schema `public` for the owner role. On a cluster it
initialized there is nothing to do, since every table arrived through a migration the owner ran; it
matters for the two cases where that is not true. A checkout predating the split has tables owned by
the old role, and `pg_restore` assigns everything to whichever role ran the restore - and in both, the
owner cannot so much as read `doctrine_migration_versions`, so the next migration reports the entire
history as unapplied and tries to replay it. The sweep is driven off the catalog and scoped to
`public`, so it converges whatever the previous owner was, never reaches a system catalog, and does
not require the restore procedure to name the role that ran the restore.

### Nothing creates tables at runtime

Audited rather than assumed. The Messenger transports carry `auto_setup: false`. The session handler
and the cache adapter have no such setting - a role without DDL rights is the only off switch
available, and it is now in place. `doctrine_migration_versions` is created on the owner connection.
The lock component is not configured. `doctrine:fixtures:load` purges with `DELETE`, not `TRUNCATE`.

**Any Doctrine-backed transport or adapter added later must set `auto_setup: false` and get a
migration.** That is now a house rule rather than a per-transport choice, because the alternative
fails at runtime in production rather than at configuration time - and on PostgreSQL the adapter's
own fallback cannot rescue it anyway, since the statement that hit the missing table has already
aborted the surrounding transaction.

## Consequences

- A SQL injection reaches the rows a request could already touch, and not the schema. That is a
  smaller blast radius, not a solved problem: the runtime role can still read and write application
  data, which is what an application role is for.
- The runtime role has **no `TRUNCATE`**. The application never used it; two browser tests did, and
  they now delete rows in foreign-key order instead (`tests/Support/DatabaseCleaner.php`). Granting a
  privilege production does not need, to suit a test convenience, would have widened exactly what
  this decision narrows.
- The deploy environment carries two more secrets, and `POSTGRES_PASSWORD` changes meaning: it is the
  break-glass credential now, not the application's.
- CI runs the suite as the runtime role, so a migration that quietly needs DDL at runtime fails there
  rather than on the deploy. This is the thing that keeps the arrangement from decaying.
- **A development checkout predating this needs its cluster provisioned**, since initialization
  scripts only run against an empty data directory. `mise run db:roles` does it; wiping
  `docker/db/data` is cleaner, because a retrofitted cluster keeps its existing tables owned by the
  old role and only tables created afterwards get the new ownership.
- A restore is no longer just `pg_restore`: the provisioning script has to be run afterwards to
  re-establish ownership and grants. It is one documented command, not a judgement call.
- `doctrine:migrations:diff` is `mise run migration:diff` now. Everything else about migrations is
  unchanged.

## Alternatives considered

- **Two roles, with `POSTGRES_USER` as the owner.** One fewer secret and a simpler script. Rejected
  because `initdb` makes that role a superuser and it cannot safely be demoted - it is the cluster's
  only one - so the migration credential in the application containers would be a full-cluster
  credential.
- **Swapping `DATABASE_URL` around the migrate command in the entrypoint.** Fewer moving parts, and
  no second connection to configure. Rejected for the by-hand case above: the failure mode is a
  confusing permission error at the worst possible moment.
- **Re-granting in the test bootstrap** instead of setting default privileges in `template1`.
  Explicit and local, but duplicates the SQL the provisioning script already carries, and the two
  would drift.
- **Granting `TRUNCATE` to the runtime role.** Would have left the two browser tests untouched.
  Rejected: the application never truncates, so it is a privilege granted purely for a test.
