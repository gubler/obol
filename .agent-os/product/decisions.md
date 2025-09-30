# Architectural Decisions

## Core Architecture

### 1. Test-Driven Development (TDD)
**Decision**: All code must have tests written BEFORE implementation.

**Rationale**:
- Tests define expected behavior and contracts
- Catches issues early in development
- Ensures code is testable by design
- Provides living documentation
- Reduces regression bugs

**Implications**:
- No feature implementation without tests first
- All PRs must include tests
- Tests run in CI before merge

### 2. Event Sourcing for Audit Trail
**Decision**: Use event sourcing pattern for subscription changes via `SubscriptionEvent` entity.

**Rationale**:
- Complete audit history of all changes
- Can track cost changes separately from general updates
- Enables future reporting on subscription lifecycle
- Immutable history for compliance/debugging

**Implementation**:
- `SubscriptionEvent` entity with type enum (Update, CostChange, Archive, Unarchive)
- Events created automatically in domain methods
- Context stored as JSON for flexibility

### 3. Immutable Properties with Asymmetric Visibility
**Decision**: Use PHP 8.4's `public private(set)` for entity properties.

**Rationale**:
- Properties readable from outside, writable only within class
- Enforces state changes through domain methods
- Better encapsulation than traditional getters/setters
- Cleaner, more modern syntax

**Example**:
```php
#[ORM\Column]
public private(set) bool $archived = false;

public function archive(): void {
    $this->archived = true; // Only writable internally
}
```

### 4. SQLite Database
**Decision**: Use SQLite for data persistence.

**Rationale**:
- Personal use application - no need for heavy database server
- File-based, zero configuration
- Fully supported by Doctrine
- Easy backup (just copy the file)
- Sufficient performance for single-user workload

**Trade-offs**:
- Limited concurrency (not an issue for single user)
- Less robust than PostgreSQL/MySQL (acceptable for personal use)

### 5. ULID Primary Keys
**Decision**: Use ULIDs instead of auto-incrementing integers for entity IDs.

**Rationale**:
- Time-ordered (better than UUIDs for sorting)
- Globally unique (no coordination needed)
- URL-safe
- More secure (IDs not guessable)

**Implementation**:
```php
#[ORM\Id]
#[ORM\Column(type: UlidType::NAME, unique: true)]
public private(set) Ulid $id;
```

### 6. No Authentication/Authorization
**Decision**: No user accounts, authentication, or authorization layer.

**Rationale**:
- Personal use only - single user
- Simplifies architecture significantly
- Reduces attack surface
- Deployment can be local-only or behind firewall/VPN

**Security Considerations**:
- Application should be deployed in trusted environment
- No sensitive PII stored (just subscription names/costs)
- Can add HTTP basic auth at web server level if needed

### 7. Symfony AssetMapper (No Build Step)
**Decision**: Use Symfony AssetMapper with importmaps instead of Webpack/Vite.

**Rationale**:
- No build process complexity
- Native ES modules work in modern browsers
- Faster development iteration
- Simpler deployment
- Adequate for modest JavaScript needs (Stimulus)

**Planned Addition**:
- Biome JS for linting/formatting when frontend development begins

### 8. Strict Type Safety & Code Quality
**Decision**: Enforce 100% type coverage and PHPStan level 9.

**Rationale**:
- Catch bugs at static analysis time
- Self-documenting code
- Better IDE support
- Forces explicit contracts
- Long-term maintainability

**Enforced Rules**:
- All parameters, returns, properties must be typed
- No forbidden functions (dump, var_dump, extract, etc.)
- No error suppression
- Symfony & Doctrine best practices required

### 9. Backed Enums for Fixed Value Sets
**Decision**: Use PHP 8.1 backed enums for `PaymentPeriod`, `PaymentType`, `SubscriptionEventType`.

**Rationale**:
- Type-safe value objects
- No magic strings
- Better IDE autocomplete
- Explicit value set in code
- Database-friendly (stores string value)

**Example**:
```php
enum PaymentPeriod: string {
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
```

### 10. Domain Logic in Entities
**Decision**: Keep domain logic in entities (Rich Domain Model), not in services.

**Rationale**:
- Business rules co-located with data
- Single source of truth for behavior
- Event creation happens automatically
- Easier to understand and maintain

**Examples**:
- `Subscription::update()` - handles change tracking
- `Subscription::recordPayment()` - creates payment records
- `Subscription::archive()` - updates state and creates event

### 11. Symfony Scheduler for Recurring Tasks
**Decision**: Use Symfony Scheduler component for future automation.

**Rationale**:
- Native Symfony component
- Stateful execution (tracks missed runs)
- Configured in PHP (type-safe)
- No external cron dependency

**Future Use**:
- Payment reminders
- Savings progress notifications
- Monthly summaries

### 12. Repository Pattern with Service Injection
**Decision**: Use Doctrine repositories, injected as services.

**Rationale**:
- Encapsulates data access logic
- Testable (can mock repositories)
- No `$entityManager->getRepository()` calls in controllers
- Follows Symfony best practices

## Testing Decisions

### 1. Pest PHP Framework
**Decision**: Use Pest PHP instead of traditional PHPUnit syntax.

**Rationale**:
- More expressive, readable tests
- Less boilerplate
- Better error messages
- Modern PHP testing experience

### 2. Zenstruck Foundry for Fixtures
**Decision**: Use Foundry for test data creation.

**Rationale**:
- Type-safe factories
- Fluent API
- Works with Doctrine
- Better than manual fixture classes

### 3. Separate Unit and Feature Tests
**Decision**: Maintain distinct Unit and Feature test suites.

**Rationale**:
- Unit tests: Fast, isolated, no database
- Feature tests: Integration tests with full stack
- Can run suites independently

## Future Decisions Needed

### Savings Calculation Strategy
**Open Question**: How to handle pro-rata savings calculation?
- Should it account for actual days or calendar months?
- How to handle leap years?
- What happens when payment date changes?

**To be decided**: During Phase 2 implementation (with tests first!)

### Frontend State Management
**Open Question**: Will Stimulus alone be sufficient, or need additional state management?

**Decision deferred**: Until CRUD operations are complete and complexity is clearer

### Report Export Formats
**Open Question**: What export formats are needed? CSV? PDF? JSON?

**Decision deferred**: Until reporting phase (Phase 3)