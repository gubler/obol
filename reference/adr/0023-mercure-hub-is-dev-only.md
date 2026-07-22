# ADR-0023: Mercure is dev-only

- Status: Accepted
- Date: 2026-07-22

## Context

The FrankenPHP/Caddy image ships a Mercure hub (a Server-Sent-Events broker) as a native Caddy
module. The stock symfony-docker configuration mounts it at `/.well-known/mercure` on the same server
that serves the app, with `anonymous` subscribers and the `subscriptions` (subscription-enumeration)
API enabled, guarded by a JWT whose committed default is a known weak value.

Two facts about how Obol actually uses Mercure framed this decision:

- **The application publishes nothing to it.** There is no `symfony/mercure-bundle`, no
  `config/packages/mercure.yaml`, no injected `HubInterface`, and no `turbo_stream` publishing
  anywhere in `src/`. `symfony/ux-turbo` *is* installed and Turbo Drive is active, but that is
  unrelated to the hub (see the transport distinction below). From the app's side the hub is dormant.
- **The hub's only real consumer is dev hot-reload.** FrankenPHP's `hot_reload` site directive
  (dev-only) pushes browser-reload events over Mercure and refuses to boot without a configured hub.
  Production runs no `hot_reload`, so in production the hub served no one - it was pure attack surface:
  a public subscription-enumeration endpoint behind a weak default key, live at exactly the point real
  users arrive.

The reason this is safe to remove, and the reason keeping it "in case we want Turbo Streams" is a
false economy, is the **two transports of Turbo Streams**:

- **As an HTTP response** (`text/vnd.turbo-stream.html`): a controller returns a stream body and Turbo
  applies it to the current page. This needs no hub - only `symfony/ux-turbo`, already installed. The
  large majority of snappy-UX wins (swap a row in place, a one-click action's result, toast a flash,
  inline-modal forms) ride this transport.
- **Pushed over Mercure SSE**: for state that changes when the viewer did *not* act - a genuine
  server-to-client push. This is the only transport that needs the hub.

So removing the hub costs the app nothing today and does not block Turbo Streams work; it only forgoes
server-initiated push, which nothing currently uses.

## Decision

**Mercure is a development-only dependency. The hub is not mounted in production.**

- The `mercure {}` block is removed from the shared `frankenphp/Caddyfile`. Development injects it
  through the existing `{$CADDY_SERVER_EXTRA_DIRECTIVES}` placeholder, set only in the dev compose
  override (alongside the dev-only JWT keys the hub reads and the `demo` debugger UI). An empty
  placeholder in production means no hub is ever adapted into the Caddy config.
- The app-facing Mercure environment (`MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET`) is
  dropped from the base compose - nothing consumes it without a Mercure bundle.
- `CADDY_MERCURE_JWT_SECRET` is no longer a required production secret. Its fail-fast guard in the prod
  overlay and its entries in the deployment docs are removed.

### Reintroducing a hub later

If a real-time feature is wanted, the hub comes back **deliberately**, not by relaxing this decision.
Whoever adds it must satisfy all of:

1. **A strong, unique signing key** sourced from the deploy environment (restore a fail-fast
   `${...:?}` guard for it), never the committed default.
2. **Per-user subscriber authorization, not `anonymous`.** Obol is multi-user with per-row ownership
   (ADR-0015), so a subscriber JWT must scope topics to the authenticated owner (e.g. a `user/{ownerUserId}`
   topic). Anonymous subscription across a shared hub would let any client read another user's stream.
   Drop the `subscriptions` enumeration API unless a concrete feature needs it.
3. **A concrete publishing feature**, added with `symfony/mercure-bundle` + `HubInterface`. Do not
   re-mount a hub as latent infrastructure. The one identified candidate is pushing
   background-generated payments / obligation changes live to an open dashboard; the seam already
   exists in the `SubscriptionsChanged` domain event and `SubscriptionChangeNotifier` (ADR-0010,
   ADR-0011), which fire whenever a user's obligation moves.

A hub-free Turbo Streams adoption (HTTP-response streams for in-request UX) needs none of the above and
is unaffected by this decision.

## Consequences

- Production presents no Mercure endpoint and requires no Mercure secret. The subscription-enumeration
  surface and the weak-default-key exposure are gone.
- Dev keeps FrankenPHP browser hot-reload unchanged; the hub is confined to the dev override.
- The trade-off recorded here is explicit: server-initiated real-time push is not available in
  production until a hub is reintroduced under the criteria above. This is a deliberate deferral, not
  an oversight - a future contributor who sees `symfony/ux-turbo` installed and wonders why there is no
  hub has this record, and one who wants to add real-time push has the conditions for doing it safely.
- Because reintroduction is gated on real per-user auth rather than the stock `anonymous` config,
  nothing reusable was lost by removing the current hub; the old configuration would have had to be
  replaced anyway.
