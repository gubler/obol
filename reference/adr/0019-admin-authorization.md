# ADR-0019: Admin authorization (ROLE_ADMIN, firewall rule plus IsGranted)

- Status: Accepted
- Date: 2026-07-14

## Context

Obol has an operator surface: an admin area at `/app/admin` (per ADR-0018's path-prefix scheme) where
the operator flips system toggles and manages user accounts. It needs a privilege above the end-user
level: a regular signed-in user must not reach it, and its entry point must be invisible to them.

End-user authorization is a single level. Authentication is passwordless (ADR-0014) and the firewall is
deny-by-default: a short public carve-out set sits above a `^/` catch-all requiring `ROLE_USER`, so
every authenticated route is protected without per-route wiring. The operator privilege is the second
level, and this ADR settles how it is modeled and enforced. How individual admin capabilities (viewing
accounts, toggling settings) are built is out of scope; so is the `SystemSettings` model (a separate
decision).

## Decision

**A single `ROLE_ADMIN` role, carried on the existing `User.roles`, enforced at the firewall and
restated on the controllers.**

- **The role reuses `User.roles`.** `User` holds a `roles` JSON array and `getRoles()` merges in
  `ROLE_USER`. `ROLE_ADMIN` is just a value in that array - no schema change, no new entity.
- **The firewall guards the whole surface.** A single `access_control` rule, `^/app/admin` requiring
  `ROLE_ADMIN`, sits above the `^/` `ROLE_USER` catch-all (first match wins). A signed-in non-admin
  hitting any `/app/admin` route gets 403; an anonymous visitor is sent to login by the entry point.
  One rule covers every current and future admin route, matching the deny-by-default posture ADR-0018
  keeps for `/app` as a whole.
- **Controllers restate the requirement with `#[IsGranted('ROLE_ADMIN')]`.** Belt-and-suspenders over
  the firewall rule: the check travels with the action, so an admin action is still guarded if a future
  route ever escapes the `^/app/admin` prefix, and the requirement is legible at the controller. These
  admin controllers are the codebase's first use of the `IsGranted` / `is_granted()` pattern.
- **The nav entry is gated by `is_granted('ROLE_ADMIN')`.** The "Admin" link renders only for admins,
  in both the desktop and mobile navigation - the surface is invisible to regular users, not merely
  link-hidden-but-reachable (the firewall enforces the latter).
- **Granting the role is console-only.** The `app:user:admin` command grants or revokes `ROLE_ADMIN`
  (one of `--grant`/`--revoke` is required - there is no default, so a fumbled invocation cannot silently
  create an admin); the admin UI does not change roles. This bootstraps the first admin (the UI that
  would grant the role is itself behind the role) and keeps role changes off the web surface while there
  is a single operator.
- **At least one admin always remains.** Revoking `ROLE_ADMIN` from the last remaining admin is refused
  at the data layer (`UserRepository::assertNotLastAdmin`), so the operator surface can never be locked
  out. Enforcing it there rather than in the command means the invariant holds for any future caller -
  the web UI's role management included.

## Consequences

- The privilege boundary is enforced in two places that agree: the firewall rule (surface-wide,
  fail-closed) and the controller attribute (action-local, legible). Neither depends on the other being
  present, so removing one does not silently open the surface.
- Adding an admin route requires nothing for authorization: the `^/app/admin` rule already covers it.
  The `#[IsGranted]` attribute on each controller is a deliberate, cheap restatement, not load-bearing
  wiring.
- `ROLE_ADMIN` on `User.roles` means an admin is an ordinary account with an extra role, so all existing
  authentication (magic-link, passkeys, remember-me) works unchanged for admins.
- There is no finer-grained permission model: it is one flat operator role. If distinct operator
  capabilities ever need to diverge (read-only auditor vs. full operator), that is a later decision.
- Role changes are not audited and not reachable from the UI, so a second admin still requires console
  access. Acceptable while there is one operator; revisit if co-admins without shell access are needed.

## Alternatives considered

- **A security voter instead of a role check.** Rejected: there is no per-object or contextual
  admin decision to make - it is a flat "is this user an operator" gate, which a role expresses directly.
  A voter is the right tool once an admin decision depends on the target (e.g. "may edit *this* user"),
  not before.
- **Firewall rule only, no `#[IsGranted]`.** Rejected: the attribute costs one line, documents the
  requirement at the action, and fails closed if a route ever lands outside the guarded prefix.
- **`#[IsGranted]` only, no firewall rule.** Rejected: it would abandon the surface-wide, deny-by-default
  guard that protects a new route the author forgot to annotate - the same fail-open trap ADR-0018
  rejected for `/app`.
- **A separate permissions/ACL system or a dedicated admin sub-application.** Rejected as premature: a
  single role on the existing user meets the whole need (one operator, a handful of capabilities) with
  no new infrastructure.
- **Granting admin through the UI.** Rejected: it cannot bootstrap the first admin (the granting UI
  is behind the role), and it puts privilege escalation on the web surface for a benefit that does not
  exist with one operator. Console-only is simpler and safe.
