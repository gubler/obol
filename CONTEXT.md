# Obol - Context

Obol is a web app for tracking recurring subscriptions and their payments. It records
each subscription's cost and billing cadence, organizes subscriptions by category, keeps
a complete audit trail of every change, and helps budget for upcoming renewals.

It is multi-user: accounts exist and the app requires passwordless magic-link login
(ADR-0014), and each subscription and payment belongs to one **owner**, who sees only their
own (ADR-0015, superseding ADR-0004's single-tenant stance). Categories, payment sources,
obligation snapshots, and per-user settings are owned the same way, so a user sees only their
own data end to end.
Still deliberately out of scope: team or household sharing, a public API, and bank integration.
See `reference/out-of-scope/` for what is explicitly not being built, and `reference/adr/`
for the decisions behind how it is built.

## Domain glossary

Use these terms exactly. The **Avoid** notes call out synonyms that have drifted across
older docs.

- **Subscription** - the central entity: a recurring charge with a cost, a billing
  cadence, and a lifecycle. Read-only from outside; all state changes go through its
  domain methods (`update`, `archive`, `unarchive`, `recordPayment`).
- **Payment** - one recorded transaction against a subscription. Carries an **amount**, a
  **type** (`Verified` or `Generated`), a **paidDate** (when the charge happened), and a
  **createdAt** (when the row was written). Amendable via `Payment::amend()`, which corrects
  the amount/date and marks it `Verified` (used to validate, adjust, or fix a typo). See
  ADR-0008.
- **SubscriptionEvent** - an immutable audit entry recording one change to a subscription.
  Its `type` is `Update`, `CostChange`, `Archive`, or `Unarchive`. Its `context` is a
  map of `field -> {old, new}`. Invariant: `Archive`/`Unarchive` carry empty context;
  `Update`/`CostChange` carry non-empty context.
- **Category** - a named grouping of subscriptions. Optional: a subscription may have no
  category. A category cannot be deleted while it still holds subscriptions
  (`CategoryHasSubscriptionsException`).
- **Uncategorized** - a subscription with no category (a null `category`, not a real Category
  row). Modeled as null end to end; the homepage groups these under an "Uncategorized" bucket
  sorted last, the reports pie shows them as one "Uncategorized" slice, and their drill-down is
  the reserved `/reports/categories/uncategorized` route.
- **PaymentSource** - a named method of payment (e.g. "Amex 1234"), optionally attached to a
  subscription. Carries a **name**, an optional free-text **comment**, and a `TileColor`. A
  Category-shaped entity: optional nullable `ManyToOne` on `Subscription`, its own CRUD, and a
  report (the by-source pie + drill-down). Cannot be deleted while it still holds subscriptions
  (`PaymentSourceHasSubscriptionsException`); a "Move all to..." action on its page reassigns
  every subscription to another source. Named "source", not "method", to avoid colliding with
  **PaymentType**. See ADR-0013.
- **Unassigned** - a subscription with no payment source (a null `paymentSource`, not a real
  PaymentSource row). The payment-source analog of **Uncategorized**: modeled as null end to
  end, shown as one "Unassigned" slice on the by-source report, with the reserved
  `/reports/payment-sources/unassigned` drill-down.
- **User** - a passwordless account and the security identity behind the login wall. Carries a
  ULID, roles, and a denormalized primary **email** (the session identifier). Authentication is
  magic-link only; there is no password. See ADR-0014.
- **owner** - the User a subscription or payment belongs to, an immutable `owner` foreign key. A
  subscription is set to its owner at creation and never reassigned; a payment copies its owner from
  its subscription at creation (denormalized, so a user's payments can be queried without joining
  through Subscription). Repository finders are owner-scoped (`findForOwner`), so one user's id never
  resolves another's row - a cross-owner lookup returns null and the controller 404s. Categories,
  payment sources, and obligation snapshots carry their own immutable `owner` the same way. See ADR-0015.
- **step-up** - re-proving identity before an action that could take over the account: the admin
  surface, and adding, removing, promoting or re-verifying an email address or passkey. These demand
  **full authentication** (`IS_AUTHENTICATED_FULLY`), which a session restored from the remember-me
  cookie is not - so a replayed cookie can read the account but never change how it is signed into.
  Reading is deliberately outside it. A refused request is not signed out: it is sent to the login page
  and returns to where it was headed. _Avoid_ "re-auth" and "2FA" - there is no second factor, only the
  same credentials proved again. See ADR-0014.
- **UserEmail** - one address a User controls, independently verified, with at most one marked
  **primary**. A magic link resolves to its User via any *verified* UserEmail (so a second verified
  address is a recovery credential); the primary is the canonical identity. Unverified rows cannot
  log in. **Avoid** treating `User.email` as the only address - it is just the denormalized primary.
- **system setting** - a piece of app-global, operator-controlled configuration, held on the
  `SystemSettings` singleton (one row, id fixed at 1). Distinct from a per-user **preference**: a
  preference is owned by a user and affects only their view; a system setting has no owner and applies
  to the whole application (e.g. whether public sign-up is open). The operator changes them from the
  admin area; the app reads them through the query bus. See ADR-0020. _Avoid_ calling these
  "preferences" or "config".
- **PaymentPeriod** - the billing cadence enum. The only cases are **`Year`**, **`Month`**,
  and **`Week`**. (There is no `Day` case, despite what older docs claimed.)
- **paymentPeriodCount** - the multiplier on the period, e.g. `paymentPeriodCount: 3` with
  `PaymentPeriod::Month` means "every three months".
- **PaymentType** - how a payment arose: **`Verified`** (asserted by the user) or
  **`Generated`** (created automatically by the scheduler). A `Generated` payment becomes
  `Verified` when the user validates or adjusts it; the reverse never happens.
- **Money** - the value object every monetary figure is held in: an integer amount in a
  currency's minor units (e.g. cents) plus its `Currency`. Persisted as a Doctrine embeddable
  (`*_amount` + `*_currency` columns). Arithmetic is same-currency only; cross-currency
  conversion goes through the converter (#126).
- **cost** - a subscription's recurring charge, a `Money`. Its currency is chosen when the
  subscription is created and is fixed once the first payment is recorded (the payments are
  denominated in it). _Avoid_ calling this "amount"; **amount** refers specifically to the value
  recorded on a `Payment` (which defaults to the subscription's cost and inherits its currency).
- **monthly cost** - a subscription's `cost` normalized to a one-month equivalent
  (`Subscription::monthlyCost`), a `Money` rounded to the nearest whole minor unit.
  Yearly costs divide by twelve; weekly costs use 52 weeks per year. Used for the homepage
  category totals and the list view. _Avoid_ "cost per month" as a separate term.
- **record a payment** - append a `Payment` to a subscription. Under automated **payment
  generation** this advances `nextRenewal` by one billing interval (`Subscription::recordPayment`)
  and deleting the latest payment rolls the anchor back; under manual generation the anchor is left
  untouched. _Avoid_ "create a payment".
- **archive / unarchive** - reversibly retire a subscription. Archived subscriptions are
  hidden by default but keep their full history. _Avoid_ "soft-delete" / "delete".
- **calendar date** - a day on the wall (year/month/day), with no time and no offset, modeled by the
  `CalendarDate` value object. It is the type of every date field that means "a day" rather than "an
  instant": a subscription's `nextRenewal`, a payment's `paidDate`, an exchange rate's `asOf`, a
  snapshot's `recordedAt`. Crossing to or from a `\DateTimeImmutable` requires naming a timezone, so a
  calendar date can never be silently compared against a zoned instant. _Avoid_ "date" unqualified where
  the distinction from an instant matters. See ADR-0021.
- **renewal** - the point at which a subscription's next charge falls due, stored as the
  `nextRenewal` anchor the scheduler keys off (advanced one interval per payment, not by
  when the user actually paid). It is a **calendar date in the owner's timezone**, not a UTC
  instant: stored as a `DATE` and interpreted against the owner's *current* timezone at read time, so a
  timezone change or a DST transition re-reads the same date rather than shifting it. _Avoid_ "payment
  due date" as a separate term. See ADR-0016, refined by ADR-0021.
- **renewal day** - the canonical day of the month (1-31) a monthly or yearly subscription recurs on,
  stored as `Subscription.renewalDay` separately from `nextRenewal->day`. It is what a short month
  clamps against and then restores from: a 31st-of-the-month renewal shows as the 28th in February and
  returns to the 31st in March, rather than drifting down. When the shown renewal differs from the
  renewal day (the clamped case), the UI flags it. _Avoid_ conflating it with the renewal date itself.
  See ADR-0008 and ADR-0021.
- **payment generation** - whether Obol generates a subscription's payments automatically or the
  user manages them, stored as the `paymentGeneration` mode (`Automated` | `Manual`). Deleting a
  subscription's latest payment switches it to **manual**: the scheduler stops generating and the
  `nextRenewal` anchor is left entirely to the user. Resuming **automated** generation is an
  explicit user action requiring a future renewal date. _Avoid_ "paused" - the subscription is not
  dormant, only its generation is manual. See ADR-0008.
- **obligation** - what the owner is committed to paying, as opposed to what was actually spent
  (Obol tracks obligation; the payment log is incidental). The **total obligation** is the sum of
  every active subscription's period-normalized **monthly cost**; it consults neither payments nor
  `nextRenewal`. Archived subscriptions are excluded, and a **Generated** payment counts as paid.
  _Avoid_ "spend" / "expense" - those imply money actually left an account. See ADR-0010.
- **obligation snapshot** - a recording of one user's total monthly obligation at a point in time,
  stored as the `ObligationSnapshot` entity, owned by that user. Each row holds the **native
  per-currency** obligation as a JSON map (currency code to minor units, e.g. `{"USD":4000,"EUR":3000}`)
  plus the date recorded. Native, unconverted storage means a row survives subscription deletion and
  bakes in no FX rate; conversion to a display currency happens at read time using today's rate.
  Recorded **on change** - every subscription create/update/archive/delete announces a
  **SubscriptionsChanged** event carrying the owner, and a row is appended only when that owner's
  obligation differs from their latest. Because obligation moves only on an edit, this captures each
  user's series exactly. Feeds the obligations-over-time chart. See ADR-0010.
- **savings target** - the amount that should be set aside by now to cover upcoming renewals
  (`Subscription::savingsTarget`), in the currency's minor units. Models a monthly budget saved
  one month ahead: a **monthly cost** is allocated on the first of each calendar month, a renewal
  is fully funded by the first of the month before it falls due, and that `cost` is held until the
  renewal is recorded paid (which advances `nextRenewal`). A funded-but-unpaid renewal is therefore
  held in full while the next cycle's saving has already begun, so the target peaks at one `cost`
  plus a lead in the unpaid due month, and a monthly bill sits between one and two `cost`s. It is a
  forward-looking budgeting hint only - there is no stored "actual saved" balance. Summed per
  category on the homepage (`CategoryGroup::savingsTotal`) to reconcile against an external monthly
  budget. The lead and allocation cadence become per-user settings later (#121, #120); weekly bills
  are a placeholder (one payment) until by-week proration. See ADR-0009.

## Architecture decisions

Recorded under `reference/adr/`:

- ADR-0001 - ULID primary keys
- ADR-0002 - Event-sourced subscription audit trail
- ADR-0003 - Rich domain entities with asymmetric-visibility immutability
- ADR-0004 - No authentication (single-tenant) - superseded by ADR-0014 (auth) and ADR-0015 (ownership)
- ADR-0005 - PostgreSQL as the database of record
- ADR-0006 - CQRS command/query buses; data access confined to the handler layer
- ADR-0007 - Write-path message conventions (DTOs stay separate from Commands; Commands carry Ulid)
- ADR-0008 - Payment lifecycle and fixed-cadence renewal
- ADR-0009 - Savings target model (one-month lead, whole-months proration)
- ADR-0010 - Reporting and obligation-snapshot model (on-change native-per-currency series, convert-at-read)
- ADR-0011 - Reinstate the synchronous event bus for domain events
- ADR-0012 - Internationalization conventions
- ADR-0013 - Payment source tracking (Category-shaped entity; reassign action; by-source report)
- ADR-0014 - Authentication model (passwordless magic-link floor; multi-email backup credential)
- ADR-0015 - Multi-user via per-row ownership (immutable owner FK; owner-scoped finders; Payment denormalized)
- ADR-0016 - Renewal dates are timezone-naive, interpreted in the owner's zone at read time
- ADR-0017 - Per-user locale application
- ADR-0018 - One origin, path-prefixed URL surfaces (`/app` for the application)
- ADR-0019 - Admin authorization (ROLE_ADMIN, firewall rule plus IsGranted)
- ADR-0020 - System settings as an app-global singleton
- ADR-0021 - CalendarDate value object (encodes the naive/zoned frame in the type system; refines ADR-0016)
- ADR-0022 - CSRF protection follows the shape of the action (Form component vs. attribute)
- ADR-0023 - Mercure is dev-only (no hub in production; reintroduction criteria recorded)
- ADR-0024 - Obol is an installable PWA (manifest only, no offline)
- ADR-0025 - Money and number formatting resolve locale through LocaleSwitcher, not an ambient global
- ADR-0026 - Deploy-durable state lives in PostgreSQL (sessions and the application cache pool; no application volumes)
- ADR-0027 - Production logs go to the host journal (Monolog to stderr, `journald` driver; size bounds are host configuration)
- ADR-0028 - Session and remember-me horizons (the rolling cookie is the credential that keeps people signed in; the session horizon is not load-bearing)
- ADR-0029 - Releases are CalVer versions derived from git tags (`YYYY.M.PATCH`, patch counted within the month; the git tag is cut before the image is pushed)

ADR-0006 records the CQRS-via-Messenger decision (keep the command/query buses; data
access confined to the handler layer). ADR-0007 extends it with the write-path
conventions: form DTOs stay distinct from Commands, and Commands carry Ulid value
objects rather than entities or stringified ids.
