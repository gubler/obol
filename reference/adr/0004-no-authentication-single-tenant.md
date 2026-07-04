# ADR-0004: No authentication (single-tenant)

- Status: Superseded - authentication by ADR-0014, per-row data ownership by ADR-0015
- Date: 2026-06-02

## Context

Obol is a personal tool for one person. Building user accounts, login, sessions, and
authorization would add significant surface area for no functional gain at this scale.

## Decision

The application has no authentication or authorization. There is no `User` entity, no
login, and no access-control rules; the `security.yaml` firewall is effectively open (an
empty in-memory provider, all `access_control` entries commented out). Access is controlled
at deployment time: Obol is expected to run on a trusted network (homelab) or behind a
reverse proxy that handles access, never exposed directly to the public internet.

## Consequences

- Anyone who can reach the app can read and modify all data. This is acceptable only
  because of the deployment constraint above - it must be honored wherever Obol is hosted
  (see #50, homelab deployment).
- The data is low-sensitivity (subscription names and costs), which bounds the risk.
- Adding multi-user support later would mean introducing a `User` entity, a real
  authenticator, and `IsGranted` checks - a deliberate, non-trivial change, and currently
  out of scope (see `reference/out-of-scope/`).
