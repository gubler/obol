# ADR-0028: Session and remember-me horizons

- Status: Accepted
- Date: 2026-07-30

## Context

ADR-0026 moved sessions off the container filesystem and into a `sessions` table. That removed the
thing which had been quietly ending sessions all along - containers being recreated on every deploy -
and left the configured idle horizon as the only mechanism that ends one. The horizon at that point
was `gc_maxlifetime: 1440`, PHP's own default, carried forward rather than chosen.

Two questions came out of it. What should the horizon be, and what should its relationship to the
30-day remember-me cookie be, given that falling back to the cookie silently downgrades whatever
authentication produced the session: a passkey assertion becomes a bearer cookie.

Neither could be answered from usage data. Obol is low-frequency by nature, and the plausible cadence
spans weekly, per paycheck at two to two and a half weeks, and monthly. Nothing in a closed beta that
has not started yet narrows it.

Three mechanics, established by reading the Symfony handlers and probing PHP directly, turned out to
decide the question between them.

**The remember-me cookie is rolling.** `AbstractRememberMeHandler::consumeRememberMeCookie()` calls
`processRememberMe()`, and `SignatureRememberMeHandler` implements that by issuing a fresh cookie. Every
restore resets the clock, so the lifetime is an idle horizon - time since the user was last here - not
an absolute one.

**A session cookie's lifetime is absolute.** PHP emits `Set-Cookie` only when it creates the session
id. Replaying an existing session cookie produces no re-issue, however many requests are made and
however long the session lives. `cookie_lifetime` can therefore only express "N days since login", a
fixed cliff that fires no matter how active the user has been. It cannot express an idle horizon at
all.

**The session cookie has no lifetime today.** `session.cookie_lifetime` is `0`, so it is a browser
cookie and dies on browser close. The fallback to remember-me is not mainly driven by the idle
horizon; it is driven by every browser restart.

## Decision

**The remember-me cookie is the credential that keeps people signed in. The session horizon is not
load-bearing and is left at PHP's default.**

Concretely:

- `framework.session.gc_maxlifetime` stays at `1440`.
- `framework.session.cookie_lifetime` stays unset.
- The remember-me lifetime is **45 days**.

### Why the session horizon is inert

It cannot change how often anyone signs in, because the session cookie dies on browser close and the
rolling remember-me cookie is what carries a returning user back in. Raising `gc_maxlifetime` alone
does not survive a browser restart, and adding `cookie_lifetime` to make it survive buys a fixed cliff
that would sign out active users on a schedule - strictly worse than the status quo for the people who
use Obol most.

It also cannot preserve the strong authentication that produced the session. Under
`always_remember_me: true` the downgrade to a cookie is inevitable at every horizon; a longer one
postpones it and never prevents it.

Recording that it is inert is the decision. The alternative - picking a larger number that reads as
considered while changing nothing - would be worse than leaving it, because a future reader would take
the number for a constraint and reason from it.

### Why 45 days

It is the one value here that responds to anything. Because the cookie is rolling, the shape of the
usage distribution does not matter; only the longest gap between two visits does. 45 days clears a
five-week absence with room to spare, which covers every cadence in the plausible range including a
missed month.

90 days was considered and rejected as longer than any of those cadences justifies. The window in
which a stolen cookie stays live is the cost, and there is no cadence in range that 90 days rescues
and 45 does not.

## Consequences

- A user is signed out only after 45 days of complete silence. In practice that means testers will
  rarely, if ever, see the login page again after the first time.
- The credential doing that work is a signature cookie, which Obol cannot revoke. There is no
  `tokensInvalidatedAt` column (ADR-0014 defers server-side force-logout to a SaaS-stage concern), so
  the only lever is a primary-email change, which invalidates outstanding cookies as a side effect.
  Lengthening the cookie widens the window in which that gap matters.
- The downgrade this ADR could not solve is addressed instead by requiring `IS_AUTHENTICATED_FULLY`
  for credential management and the admin surface, so a cookie-restored session cannot add a passkey,
  change a primary address, or reach the operator surface without a fresh proof. That is what makes 45
  days comfortable rather than merely tolerable.
- Every value here is a one-line change, and beta will supply what could not be known in advance: the
  spread of `sessions.sess_time`, magic-link request volume, and whether anyone reports being signed
  out. The horizons are expected to be revisited against that.

## Alternatives considered

- **Long session, short remember-me.** Appealing because the session row is server-side and therefore
  revocable, which is the capability Obol lacks. Rejected because `cookie_lifetime` is absolute: the
  "long session" it buys is a cliff that logs out daily users on a fixed schedule, and the revocation
  benefit does not survive that trade.
- **Long session, long remember-me.** Rejected as redundant. If the session already lasted weeks, the
  cookie would fire only after session-store loss, so this carries the risk surface of both credentials
  to buy convenience one already provides.
- **Leaving remember-me at 30 days.** Defensible, and covers weekly and per-paycheck usage. Rejected
  because it leaves a monthly user close enough to the boundary that an ordinary missed month signs
  them out, and the cost of the extra 15 days is small once credential management demands a fresh
  proof.
- **Setting `cookie_lifetime` to match `gc_maxlifetime`.** Rejected on the mechanic above. It reads
  like it makes the two horizons agree, and instead introduces a cliff the idle horizon never had.
