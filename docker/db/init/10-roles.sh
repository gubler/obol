#!/bin/sh
# ABOUTME: Provisions the two application database roles - an owner that owns the schema and runs
# ABOUTME: migrations, and a runtime role with data rights only - plus the grants that keep it true.
#
# Four callers, one artifact:
#
#   - the postgres image, from /docker-entrypoint-initdb.d, on the first boot of an empty cluster
#   - `mise run db:roles`, against a cluster that already exists
#   - the CI workflow, against its postgres service (no initdb hook available there)
#   - the restore procedure, by hand, after loading a dump
#
# So it is idempotent throughout: roles are created only when absent, and every attribute, password
# and grant is re-applied on each run. Re-running it is the supported way to converge a cluster, and
# the way a rotated password takes effect.
#
# Connection details come from the standard PG* environment variables, which is what lets one script
# serve both contexts: under initdb there is no PGHOST, so psql uses the local socket, and CI sets
# PGHOST/PGPASSWORD to reach the service over TCP.
#
# What is deliberately NOT here: REASSIGN OWNED. Objects restored from a dump belong to whichever
# role ran the restore, and only a human knows which one that was - so ownership transfer lives in
# the documented restore procedure (docs/src/content/docs/deployment.md), not in a script that would
# have to guess. See reference/adr/0030.

set -eu

DATABASE="${POSTGRES_DB:-app}"
OWNER="${OBOL_DB_OWNER:-obol_owner}"
OWNER_PASSWORD="${OBOL_DB_OWNER_PASSWORD:-!ChangeMe!}"
RUNTIME="${OBOL_DB_RUNTIME:-obol_app}"
RUNTIME_PASSWORD="${OBOL_DB_RUNTIME_PASSWORD:-!ChangeMe!}"

# Role names arrive as :"identifiers" and passwords as :'literals', so psql does the quoting rather
# than this script interpolating either into SQL text.
psql_on() {
    psql \
        --username "${POSTGRES_USER:-postgres}" \
        --dbname "$1" \
        --no-psqlrc \
        --quiet \
        --set ON_ERROR_STOP=1 \
        --set owner="$OWNER" \
        --set owner_password="$OWNER_PASSWORD" \
        --set runtime="$RUNTIME" \
        --set runtime_password="$RUNTIME_PASSWORD" \
        --set database="$DATABASE"
}

echo "Provisioning database roles '$OWNER' (owner) and '$RUNTIME' (runtime) on '$DATABASE'..."

# Roles are cluster-wide, so which database this runs against does not matter. CREATE is guarded and
# ALTER is not: the ALTER is what converges a role created by an earlier run, or by an earlier
# version of this script, onto the attributes below.
#
# The owner is NOSUPERUSER on purpose. Its credential sits in the application containers' environment
# so that a migration can be run by hand, and a superuser credential there would turn code execution
# in PHP into code execution in the database container by way of COPY TO PROGRAM. CREATEDB is granted
# for one reason only: the test suite drops and recreates its own database on every run.
psql_on "$DATABASE" <<'EOSQL'
SELECT format('CREATE ROLE %I LOGIN', :'owner')
 WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'owner')
\gexec
ALTER ROLE :"owner" WITH LOGIN NOSUPERUSER NOCREATEROLE CREATEDB PASSWORD :'owner_password';

SELECT format('CREATE ROLE %I LOGIN', :'runtime')
 WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'runtime')
\gexec
ALTER ROLE :"runtime" WITH LOGIN NOSUPERUSER NOCREATEROLE NOCREATEDB PASSWORD :'runtime_password';

ALTER DATABASE :"database" OWNER TO :"owner";
EOSQL

# The per-database half, applied to the live database and to template1.
#
# template1 is not decoration. Default privileges live in pg_default_acl, which is per-database, and
# CREATE DATABASE is a physical copy of template1 - so without this, every database created later
# starts with no rule at all. The test suite drops and recreates its database on every single run,
# which would leave the runtime role with rights on nothing, every time. Setting it in the template
# means a created database arrives with the rule already in place, no re-grant step anywhere.
#
# Production never exercises that path (only one database is ever created there, and it is handled
# above by name), so nothing about the live deployment depends on this - it is what keeps development
# and CI honest.
for target in "$DATABASE" template1; do
    # ALTER DEFAULT PRIVILEGES covers tables a future migration creates; the GRANT ... ON ALL covers
    # the ones already there, which is what a cluster being retrofitted or restored needs. Sequences
    # are included even though every identifier here is a ULID or an identity column, so that the
    # first serial column added does not fail at runtime in a way that reads as an application bug.
    #
    # REVOKE CREATE is a no-op on PostgreSQL 15 and later, where the public schema no longer grants
    # it to PUBLIC. Stated anyway: it is the one privilege that would let the runtime role create the
    # very tables this arrangement exists to keep it away from.
    psql_on "$target" <<'EOSQL'
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO :"runtime";

-- Hand anything already in the schema to the owner role. On a cluster this script initialized there
-- is nothing to do, because every table arrived through a migration the owner ran. It matters for the
-- two cases where that is not true: a checkout predating the split, and a restore - pg_restore assigns
-- everything to whichever role ran it. Without this the owner cannot so much as read
-- doctrine_migration_versions, so the next migration reports the whole history as unapplied.
--
-- Scoped to `public` and driven off the catalog, so it converges whatever the previous owner was and
-- never reaches a system catalog. This is why the restore procedure does not have to name the role
-- that ran the restore. Ownership before the grants below, since the grants follow from it.
SELECT format('ALTER TABLE public.%I OWNER TO %I', tablename, :'owner')
  FROM pg_tables WHERE schemaname = 'public' AND tableowner <> :'owner'
\gexec
SELECT format('ALTER SEQUENCE public.%I OWNER TO %I', sequencename, :'owner')
  FROM pg_sequences WHERE schemaname = 'public' AND sequenceowner <> :'owner'
\gexec

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"runtime";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO :"runtime";

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"runtime";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"runtime";
EOSQL
done

echo "Database roles provisioned."
