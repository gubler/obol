---
title: "Domain Model"
---

All entities live in `src/Entity/` and share these design patterns:

- **ULID primary keys** via `symfony/uid`, generated in the constructor
- **`public private(set)` properties** (PHP 8.4+) for external readability without external mutation
- **`beberlei/assert`** for constructor invariant validation
- **No setters** — state changes go through named domain methods

## Subscription

The core entity. Tracks a recurring payment with its cost, billing period, and metadata.

**Properties:**

| Property | Type | Notes |
|----------|------|-------|
| `id` | `Ulid` | Generated in constructor |
| `archived` | `bool` | Default `false` |
| `createdAt` | `DateTimeImmutable` | Set in constructor |
| `category` | `?Category` | Optional, ManyToOne (null = uncategorized) |
| `paymentSource` | `?PaymentSource` | Optional, ManyToOne (null = unassigned) |
| `name` | `string` | Non-empty (validated) |
| `lastPaidDate` | `DateTimeImmutable` | When last payment occurred |
| `paymentPeriod` | `PaymentPeriod` | Year, Month, or Week |
| `paymentPeriodCount` | `int` | Must be > 0 |
| `cost` | `int` | In cents, must be > 0 |
| `description` | `string` | Optional |
| `link` | `string` | Optional |
| `logo` | `string` | File path, optional |

**Collections:**

- `payments` — `OneToMany` to `Payment`, cascade persist+remove, orphanRemoval
- `subscriptionEvents` — `OneToMany` to `SubscriptionEvent`, cascade persist+remove, orphanRemoval

**Domain Methods:**

### `update(...)`

Accepts all mutable fields. Uses two `ChangeContextGenerator` instances to diff:

1. **General fields** (category, paymentSource, name, lastPaidDate, description, link, logo) — emits a `SubscriptionEventType::Update` event if any changed. `reassignPaymentSource()` records the same kind of event for just the payment-source change (used by the bulk "move all" action).
2. **Cost fields** (paymentPeriod, paymentPeriodCount, cost) — emits a `SubscriptionEventType::CostChange` event if any changed

Events are only created when values actually differ from the current state.

### `archive()` / `unarchive()`

Toggle the `archived` flag and emit an `Archive` or `Unarchive` event with empty context.

### `recordPayment(...)`

Creates a new `Payment` child entity and updates `lastPaidDate`. Accepts an optional `amount` parameter; defaults to the subscription's `cost` if not provided.

## Payment

Records a single payment transaction linked to a subscription.

| Property | Type | Notes |
|----------|------|-------|
| `id` | `Ulid` | Generated in constructor |
| `subscription` | `Subscription` | Required, ManyToOne |
| `type` | `PaymentType` | `Verified` or `Generated` |
| `amount` | `int` | In cents, must be > 0 |
| `createdAt` | `DateTimeImmutable` | When the payment occurred |

`Verified` payments are entered manually by the user. `Generated` payments are created automatically by the scheduler when a payment is due.

## Category

Groups subscriptions. Carries a name, a color, and an icon, plus its collection of subscriptions.

| Property | Type | Notes |
|----------|------|-------|
| `id` | `Ulid` | Generated in constructor |
| `name` | `string` | Non-empty (validated) |
| `color` | `TileColor` | Defaults to a random swatch; rendered flat (`baseColorClass()`), not the tile gradient |
| `icon` | `CategoryIcon` | Curated closed Lucide set; defaults to the neutral `Tag` |
| `subscriptions` | `Collection<Subscription>` | OneToMany, read-only |

`update()` is the single mutator: it takes a nullable name, color, and icon, changes only the fields that are provided, and asserts at least one was. Categories cannot be deleted if they have subscriptions (`CategoryHasSubscriptionsException`). The "Uncategorized" pseudo-group (a null category) renders with a reserved neutral Charcoal swatch and a dashed icon.

## PaymentSource

A method of payment (e.g. "Amex 1234") optionally attached to a subscription. A Category-shaped entity (see ADR-0013).

| Property | Type | Notes |
|----------|------|-------|
| `id` | `Ulid` | Generated in constructor |
| `name` | `string` | Non-empty (validated) |
| `comment` | `string` | Optional free-text note |
| `color` | `TileColor` | Defaults to a random swatch |
| `subscriptions` | `Collection<Subscription>` | OneToMany, read-only |

`update()` is the single mutator: it takes a nullable name, comment, and color, changes only the fields that are provided, and asserts at least one was. A source cannot be deleted while it holds subscriptions (`PaymentSourceHasSubscriptionsException`); the "Move all to..." action on its page reassigns every subscription to another source (each move records its own audit event via `Subscription::reassignPaymentSource()`). The "Unassigned" pseudo-group (a null payment source) renders with the reserved neutral Charcoal swatch.

## SubscriptionEvent

Audit log entry for subscription state changes.

| Property | Type | Notes |
|----------|------|-------|
| `id` | `Ulid` | Generated in constructor |
| `subscription` | `Subscription` | Required, ManyToOne |
| `type` | `SubscriptionEventType` | The kind of change |
| `context` | `array` | JSON — field diffs for Update/CostChange, empty for Archive/Unarchive |
| `createdAt` | `DateTimeImmutable` | When the event occurred |

**Validation rules in the constructor:**

- `Archive` and `Unarchive` events **must** have empty context
- `Update` and `CostChange` events **must** have non-empty context

**Context format** (for Update and CostChange events):

```json
{
  "fieldName": {
    "old": "previous value",
    "new": "current value"
  }
}
```

## Enums

Three backed string enums in `src/Enum/`:

**`PaymentPeriod`** — `year`, `month`, `week`

**`PaymentType`** — `verified` (manual), `generated` (scheduler)

**`SubscriptionEventType`** — `costChange`, `update`, `archive`, `unarchive`

## ChangeContextGenerator

A utility in `src/Lib/ChangeContextGenerator/` that compares old and new values to produce diff arrays.

- `Change` — value object with `field`, `current`, and `new` properties
- `ChangeContextGenerator` — accepts an array of `Change` objects, returns only the fields where values differ

Used by `Subscription::update()` to build event context without manual comparison logic.

## Entity Relationships

```mermaid
erDiagram
    Category |o--o{ Subscription : "has many"
    PaymentSource |o--o{ Subscription : "has many"
    Subscription ||--o{ Payment : "has many"
    Subscription ||--o{ SubscriptionEvent : "has many"
```
