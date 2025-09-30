# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-09-30-domain-fixtures-tests/spec.md

## Technical Requirements

### Factory Structure

- **Location**: `src/Factory/` directory (created via `bin/console make:factory`)
- **Naming**: `{Entity}Factory.php` (e.g., `CategoryFactory.php`)
- **Base Class**: Extend `Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory`
- **Current State**: Stub factories already exist, need to be enhanced with:
  - Sensible defaults for all required fields
  - State modifier methods for common scenarios
  - Proper relationship handling
- **State Methods**: Each factory should include fluent state modifiers:
  - `SubscriptionFactory`: `->archived()`, `->withRecentPayment()`, `->expensiveSubscription()`
  - `SubscriptionEventFactory`: `->update()`, `->costChange()`, `->archive()`, `->unarchive()`
  - `PaymentFactory`: `->regular()`, `->priceChange()`
- **Relationships**: Factories should handle related entities automatically (e.g., Subscription needs Category)

### Unit Test Structure

- **Location**: `tests/Unit/Entity/` directory
- **Naming**: `{Entity}Test.php` (e.g., `SubscriptionTest.php`)
- **Framework**: Pest PHP with `uses(TestCase::class)` for Symfony integration
- **Test Organization**: Group related tests using `describe()` blocks:
  ```php
  describe('creation', function() { ... });
  describe('update', function() { ... });
  describe('archive/unarchive', function() { ... });
  ```

### Category Entity Tests

- **Creation Tests**:
  - Can create with valid name
  - ID is automatically generated (ULID)
  - CreatedAt timestamp is set automatically
  - Name is required (test constructor type enforcement)

- **State Validation**:
  - Name must be non-empty string (enforce via constructor)
  - Test that empty string throws assertion error (beberlei)

### Subscription Entity Tests

- **Creation Tests**:
  - Can create with all required fields
  - ID and createdAt auto-generated
  - Archived defaults to false
  - Collections (payments, subscriptionEvents) initialized empty
  - Category relationship required

- **Update Method Tests**:
  - **General update only**: Change name/description/link/logo → creates Update event only
  - **Cost change only**: Change cost/paymentPeriod/paymentPeriodCount → creates CostChange event only
  - **Both**: Change both types of fields → creates both Update and CostChange events
  - **No change**: Update with same values → no events created
  - Event context correctly captures old/new values
  - Properties are updated after event creation

- **Payment Recording Tests**:
  - `recordPayment()` creates Payment entity
  - Updates lastPaidDate
  - Payment added to payments collection
  - Custom amount overrides subscription cost
  - Null amount uses subscription cost

- **Archive/Unarchive Tests**:
  - `archive()` sets archived=true and creates Archive event
  - `unarchive()` sets archived=false and creates Unarchive event
  - Events are added to subscriptionEvents collection

- **State Validation**:
  - Cost must be positive integer
  - PaymentPeriodCount must be positive integer
  - Name must be non-empty string
  - LastPaidDate cannot be in future (if this rule exists)
  - Category cannot be null

### Payment Entity Tests

- **Creation Tests**:
  - Can create with subscription, type, amount, createdAt
  - ID auto-generated
  - Subscription relationship required
  - Type is PaymentType enum
  - Amount must be positive integer

- **State Validation**:
  - Amount cannot be zero or negative
  - CreatedAt cannot be null
  - Type must be valid PaymentType enum value

### SubscriptionEvent Entity Tests

- **Creation Tests**:
  - Can create with subscription, type, and context
  - ID and createdAt auto-generated
  - Subscription relationship required
  - Type is SubscriptionEventType enum
  - Context stored as array

- **Event Type Tests**:
  - Update event with proper context structure
  - CostChange event with proper context structure
  - Archive event with empty context
  - Unarchive event with empty context

- **State Validation**:
  - Subscription cannot be null
  - Type must be valid SubscriptionEventType enum value
  - Context must be array (even if empty)

### Development Fixtures

- **Location**: `src/DataFixtures/AppFixtures.php`
- **Requirements**:
  - Use Foundry factories exclusively (no manual entity creation)
  - Create 5-10 categories with descriptive names (Entertainment, Software, Utilities, etc.)
  - Create 20-30 subscriptions across categories with variety:
    - Mix of payment periods (monthly, yearly, quarterly)
    - Mix of costs (cheap, moderate, expensive)
    - Some archived subscriptions
    - Various lastPaidDate values (recent, due soon, overdue)
  - Create realistic payments for subscriptions (2-5 payments each)
  - Subscriptions should have associated events reflecting their history
  - Use factory state methods for variety (e.g., `->archived()`)

### Test Execution

- **Command**: `mise run test` or `php vendor/bin/pest`
- **Test Suite**: Unit tests run in `tests/Unit/` directory
- **Output**: Must pass with green output
- **Coverage**: All public entity methods must be tested
- **Database**: Use in-memory SQLite for fast test execution

### Assertions to Use

- **Pest Assertions**:
  - `expect($value)->toBe($expected)` - exact equality
  - `expect($value)->toBeInstanceOf(Class::class)` - type checking
  - `expect($value)->toBeTrue()` / `toBeFalse()` - boolean checks
  - `expect($collection)->toHaveCount($n)` - collection size
  - `expect($value)->toBeEmpty()` - empty check
  - `expect()->throws()` - exception testing for invalid states

- **Beberlei Assertions**: Used within entity constructors/methods
  - `Assertion::notEmpty($value, 'message')` - non-empty strings
  - `Assertion::greaterThan($value, 0, 'message')` - positive numbers
  - `Assertion::choice($value, $choices, 'message')` - enum validation (if needed)

### Valid State Definition (per entity discussion with user)

- **Category**:
  - Name must be non-empty string
  - ID and createdAt auto-generated

- **Subscription**:
  - All required fields must be provided in constructor
  - Cost and paymentPeriodCount must be positive
  - Name must be non-empty
  - Category must exist
  - Collections auto-initialized

- **Payment**:
  - Amount must be positive
  - Subscription must exist
  - Type must be valid enum
  - CreatedAt required

- **SubscriptionEvent**:
  - Subscription must exist
  - Type must be valid enum
  - Context must be array
  - CreatedAt auto-generated

## Test-First Development Process

1. **Enhance factory** - Add sensible defaults and state methods to stub factories
2. **Write failing test** - Test describes expected behavior
3. **Run test** - Verify it fails (red)
4. **Add assertions to entity** - Enforce valid state
5. **Run test** - Verify it passes (green)
6. **Refactor if needed** - Clean up code
7. **Repeat** for next test case

## Dependencies

No new external dependencies required. All necessary packages already installed:
- `pestphp/pest` (4.0)
- `zenstruck/foundry` (2.6+)
- `phpunit/phpunit` (12.3+)
