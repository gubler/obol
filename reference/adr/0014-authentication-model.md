# ADR-0014: Authentication model

- Status: Accepted
- Date: 2026-07-03

## Context

The multi-user effort turns Obol from a single-tenant app into a multi-user one. ADR-0004 recorded the
original "no authentication, single tenant" decision; this ADR supersedes its authentication half.
(The per-row data-ownership half of ADR-0004 is superseded separately, alongside the per-user
data-isolation work in a later slice - this one adds accounts and a login wall but no per-user data
isolation yet.)

The question is how people prove who they are. The constraints: it must be low-friction for a small,
invite-only tester group; it must not require Obol to store passwords; and it must leave room for the
later slices (passkeys, multi-email management, per-user settings) without a rewrite.

## Decision

### Passwordless magic-link is the authentication floor

A user requests a sign-in link by email and clicks it; there is no password, ever. Symfony's native
`login_link` authenticator does the work (15-minute signed links, `signature_properties: ['id', 'email']`).
We use the framework authenticator rather than a bundle: magic links are core Symfony, and keeping the
floor dependency-free means the one bundle we do add later (`web-auth/webauthn-symfony-bundle` for
passkeys) is additive, not load-bearing.

### Identity is a User; addresses are UserEmail rows

`User` is the security identity (ULID, roles, a denormalized primary `email` that is the session
identifier). Each address a user controls is a separate `UserEmail` row, independently verified, with
at most one marked primary. This exists from the first slice - even before there is UI to manage
secondary addresses - because it is what makes a second verified address a **recovery credential**: if
you lose access to your primary mailbox, a magic link to any verified address still signs you in.
`MultiEmailUserProvider` resolves any verified address to its `User`; the link always signs against the
primary (session identity stays stable) but is delivered to the address that was typed.

Two Postgres partial unique indexes enforce the invariants the application also checks:
`(user_id) WHERE is_primary` (exactly one primary per user) and `(email) WHERE verified_at IS NOT NULL`
(a verified address belongs to one user; unverified rows do not compete, so an address cannot be
squatted by leaving it unverified). Email columns are `citext`, so comparison and those indexes are
case-insensitive regardless of code path.

### The magic-link request runs off-request, for enumeration safety

Requesting a link dispatches `RequestLoginLinkCommand` to the **async** transport; the account lookup
and the send happen on the worker, and the transactional email itself goes out via the **mail**
transport. The in-request path is therefore identical for every address - registered or not, it returns
the same "if that account exists, check your email" page - so login cannot be used to enumerate
registered addresses. There is no timing to pad because there is no in-request lookup.

### The user provider is the one exemption to the ADR-0006 data-access boundary

ADR-0006 confines repository/`EntityManager` access to the handler layer; callers reach data only
through the buses. `MultiEmailUserProvider` is the deliberate, singular exception. It runs at the
**pre-firewall bootstrap seam** - `refreshUser()` rehydrates the session user on every request while
security is still initializing - so it cannot depend on the query bus: once owner-scoped query runners
read the current user, a provider that dispatched a query would risk re-entering the firewall and
looping. It is a data-access seam like a repository (Symfony's own `EntityUserProvider` holds the
`EntityManager` for the same reason), not a consumer bypassing the boundary. The exemption is scoped to
that one class by name in the architecture test.

### Session firewall with remember-me

Authentication is session-based (magic link today; passkeys join later). `always_remember_me` issues a
30-day cookie so testers are not re-prompted every visit; without a `tokensInvalidatedAt` column yet
(server-side force-logout is a later, SaaS-stage concern), the cookie signs on the primary `email`, so a
primary-email change invalidates outstanding cookies. The whole app is authenticated-by-default; only
the login, logout, and magic-link routes are public.

## Considered options

- **Passwords / password + magic-link** - rejected. Storing password hashes is exactly the liability a
  passwordless floor avoids, and a small tester group does not need it.
- **`web-auth` bundle as the primary authenticator** - rejected as the floor. Passkeys are a fast-path
  added on top later, not the always-available baseline; email is the credential everyone has.
- **Synchronous (in-request) link lookup with timing padding** - rejected. Off-request dispatch makes the
  request path timing-flat by construction, with no padding to calibrate or get wrong.
- **Routing the user provider through the query bus** - rejected for the circularity reason above; the
  scoped, documented exemption is safer than a bus dependency at the bootstrap seam.

## Consequences

- Accounts exist and the app requires login; a logged-in user still sees all data until per-user
  isolation lands in a later slice.
- `app:user:create <email>` seeds an account with a verified primary address (testers, fixtures, and the
  founder before the data-isolation migration).
- The seams for the parallel slices are in place: `UserEmail` for multi-email management, the
  `login_link` firewall for passkeys, and roles for the eventual admin surface.
- The prod mailer must be live and `app:mailer:smoke` must pass before the founder migration, or the
  first magic-link login locks the founder out.
