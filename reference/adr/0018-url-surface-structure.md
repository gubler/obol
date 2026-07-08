# ADR-0018: One origin, path-prefixed URL surfaces (`/app` for the application)

- Status: Accepted
- Date: 2026-07-08

## Context

Obol is growing from a single authenticated application into several distinct surfaces served
from one product:

1. Public marketing/landing pages (anonymous, conversion-oriented).
2. The authenticated application (the existing dashboard and everything behind it).
3. A static end-user help manual (an Astro Starlight site, separate from the developer docs).
4. An eventual token-authenticated API for native clients (out of scope here; see ADR-0014, which
   keeps the domain behind the command/query buses so an API is a later adapter, not a rewrite).

Today the application owns the root: `/` is the dashboard. The public landing was bolted on by
branching `/` on authentication state (an anonymous request is forwarded to the landing controller).
That forces `/` to fork on auth, and it means a signed-in user can never view the public site - there
is no URL at which the marketing pages exist for them.

Authentication is session-cookie based: passwordless magic-link is the floor, WebAuthn passkeys are an
opt-in fast path, plus remember-me (ADR-0014). Passkeys are the binding constraint on how surfaces may
be split: a credential is scoped to a Relying Party ID (a domain), and the RP ID cannot be changed once
users hold credentials without invalidating them.

This ADR settles how the surfaces are addressed.

## Decision

**One origin. Surfaces are separated by URL path prefix, not by subdomain.**

- **`/` and other root paths - public marketing/landing.** `/` is always the landing, for anonymous
  and authenticated visitors alike; it no longer forks on auth. Future marketing pages (`/pricing`,
  `/about`, ...) live at the root.
- **`/login`, `/logout`, the magic-link and passkey login endpoints - public auth, at the root.** They
  stay memorable, are linked from emails and marketing CTAs, and redirect into the application on
  success.
- **`/app/...` - the authenticated application.** The dashboard, subscriptions, categories, payment
  sources, reports, payments, account, and onboarding all move under `/app`. The firewall collapses to
  a single rule: `^/app` requires `ROLE_USER`; everything else is public by default, with a small,
  explicit set of public root endpoints. Route *names* do not change, so generated links keep working;
  only the paths gain the prefix.
- **`/help/...` - the static help manual.** Served as files by the web server (Caddy/FrankenPHP) from
  `public/help` before a request ever reaches PHP. Symfony carries no route for it. The site is built
  into the production image so it ships in lock-step with the application, with no separate publish step.
- **`/api/...` - reserved for the future native-client API.** Token-authenticated, so it carries no
  cookie or passkey entanglement. Not built here; the prefix is reserved so it can land without
  disturbing the other surfaces.

**Email-clicked public endpoints stay outside `/app`.** Links opened from a mailbox while logged out -
the signed secondary-email verification link and the magic-link check - must not sit behind the `^/app`
wall. They keep root-level public paths, so the `^/app` boundary needs no holes punched in it.

**The WebAuthn Relying Party ID is pinned to the registrable (apex) domain, not a hostname.** An apex
RP ID (e.g. the registrable domain, not `app.` or `help.`) keeps passkeys valid across the apex and any
future subdomain; a host-scoped RP ID would lock credentials to one hostname forever, since it cannot be
changed after registration. This is fixed now, while every surface is same-origin, precisely because it
is the one part of this decision that is expensive to reverse once testers hold passkeys.

## Consequences

- The firewall reduces from "authenticated-by-default, then carve out public exceptions" to one
  `^/app` rule plus a short, explicit public-root set (landing, login/magic-link, email-verify,
  the updates form, health). Simpler to read and to reason about.
- Moving the application under `/app` is broad but mechanical: every application route path gains the
  prefix; `path()`/`redirectToRoute()` calls regenerate automatically because route names are unchanged;
  redirect targets, the onboarding gate's allowlist, and passkey/login success redirects update; and
  controller tests that assert literal URLs move under `/app`.
- The public landing stops branching `/` on auth - `/` becomes a plain public route - so a signed-in
  user can visit the marketing site, and the auth-fork workaround is deleted.
- The help manual cannot drift from the application: it is built into the same image, at the same time,
  and has no runtime coupling to Symfony (Caddy serves the static files directly).
- The marketing root can grow without colliding with application routes, since those now live under
  `/app`.
- Same-origin keeps cookies, CSRF, and passkeys trivial: one RP ID, one allowed origin, one cookie
  scope. A later move of a surface onto its own subdomain remains possible (the apex RP ID already
  allows it) but is not required.

## Alternatives considered

- **A subdomain per surface (`app.`, `help.`, `api.`).** Rejected as the default. Cross-subdomain
  cookie/CSRF scoping and careful passkey RP-ID handling buy complexity that a solo, single-server
  closed test does not need. The only genuinely separate build artifact - the static manual - is
  handled by baking it into the image, not by standing up a second origin. Subdomains stay available
  later without re-registering passkeys, thanks to the apex RP ID.
- **Keep the application at the root; let the landing keep branching `/` on auth.** Rejected: `/`
  forks on auth permanently, a signed-in user can never see the public site, and the marketing root
  stays cramped against application routes.
- **Serve `/help` through a Symfony controller/route.** Rejected: the manual is static output. Letting
  the web server serve `public/help` means those requests never enter PHP, and Symfony holds no route it
  must guard, version, or keep from colliding with real application paths.
- **Prefix marketing instead (`/marketing`, app stays at `/`).** Rejected: marketing owns the root for
  SEO and memorability; the application is the surface with a natural namespace to move into.
