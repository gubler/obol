# ADR-0005: PostgreSQL as the database of record

- Status: Accepted
- Date: 2026-06-02

## Context

An earlier plan (recorded in the now-retired `.agent-os` scaffold) called for SQLite
everywhere, on the reasoning that a personal app needs no database server. In practice the
production stack was built on PostgreSQL 16 (`compose.yaml` provisions a `postgres:16-alpine`
service and points `DATABASE_URL` at it), and `doctrine.yaml` carries PostgreSQL-specific
configuration (`identity_generation_preferences` for `PostgreSQLPlatform`). That reversal
was never written down, leaving the docs contradicting the deployment.

## Decision

PostgreSQL is the database of record. Production runs PostgreSQL 16. This ADR records the
reversal of the original SQLite-everywhere plan and supersedes it.

## Consequences

- Production already runs on PostgreSQL.
- The committed `.env` default `DATABASE_URL` is still SQLite, so local dev and the test
  suite currently run on a different engine than production - a real dialect-drift risk.
  Closing that gap (dev and test on PostgreSQL, CI spinning up a Postgres service) is
  tracked in #57 and is the natural follow-up to this ADR.
- Migrations and queries should be written for and verified against PostgreSQL.
