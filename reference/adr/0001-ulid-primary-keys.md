# ADR-0001: ULID primary keys

- Status: Accepted
- Date: 2026-06-02

## Context

Every entity needs a primary key. The default Doctrine choice is an auto-incrementing
integer, but those keys are sequential (guessable in URLs), require a database round-trip
to learn the value, and are not sortable across tables.

## Decision

All entities (`Subscription`, `Payment`, `Category`, `SubscriptionEvent`) use a Symfony
ULID as their primary key. The ULID is generated in the entity constructor (`new Ulid()`)
and mapped with `UlidType`. Doctrine's `identity_generation_preferences` is set so
PostgreSQL still uses `identity` generation for its own internal sequences where relevant.

## Consequences

- IDs are known before persistence (generated in the constructor), and are
  lexicographically sortable by creation time.
- IDs are non-sequential and safe to expose in URLs.
- Lookups by ID must validate the string is a well-formed ULID before querying
  (`Ulid::isValid` / `Ulid::fromString`); invalid input is treated as "not found".
- ULIDs are stored in their compact binary form via `UlidType`; the RFC 4122 string form
  is used at the boundary (e.g. route parameters).
