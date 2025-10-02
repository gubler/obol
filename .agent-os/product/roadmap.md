# Product Roadmap

## Phase 0: Already Completed ✓

The following foundation work has been implemented:

- [x] **Domain Model** - Core entities with strict type safety
  - Subscription entity with immutable properties (`public private(set)`)
  - Payment entity for transaction records
  - SubscriptionEvent for audit trail (Update, CostChange, Archive, Unarchive)
  - Category entity for organization
  - Backed enums: PaymentPeriod, PaymentType, SubscriptionEventType
  - Entities should only be able to be created/updated into a valid state
    - create their own ID
    - required fields passed in constructor
    - update methods that maintain a valid state

- [x] **Event Sourcing Architecture** - Complete change tracking
  - All subscription changes create audit events
  - Separate events for updates vs cost changes
  - Archive/unarchive tracking
  - Current state is still kept on the entity
    - We do not need to calculate current state from the tracked changes

- [x] **Repository Layer** - Doctrine repositories for all entities

- [x] **Code Quality Infrastructure**
  - PHPStan level 9 with 100% type coverage
  - PHP CS Fixer with strict rules
  - Rector for automated refactoring
  - Twig CS Fixer
  - Pest PHP testing framework

- [x] **Development Environment**
  - Symfony 7.3 application structure
  - Docker support (compose.yaml)
  - Mise task runner configuration

- [x] **Foundry Factories** - Test data creation with Zenstruck Foundry
  - Category, Payment, Subscription, and SubscriptionEvent factories
  - State methods for common scenarios (archived, with recent payment, etc.)
  - Factory tests ensuring valid entity creation

- [x] **Unit Tests for Domain Entities** - Comprehensive test coverage
  - Category entity tests with state validation
  - Payment entity tests with state validation
  - SubscriptionEvent entity tests with context validation
  - Subscription entity tests with business logic coverage
  - All entities use beberlei assertions for runtime validation

- [x] **Data Fixtures** - Realistic development fixtures with AppFixtures
  - 8 categories with descriptive names
  - 10 subscriptions with variety (different payment periods, costs, archived states)
  - Realistic payment history for subscriptions
  - Subscription events reflecting update history

## Phase 0: Existing Code cleanup (Current Development)

Currently no outstanding Phase 0 tasks.

## Phase 1: CRUD Operations

### Category Management
- [ ] Create category form and controller
- [ ] Edit category functionality
- [ ] List all categories
- [ ] Delete/archive categories (with validation if subscriptions exist)
- [ ] Basic category details page

### Subscription Management
- [ ] Create subscription form with all fields
  - Name, cost, payment period, payment period count
  - Last paid date
  - Description, link, logo
  - Category assignment
- [ ] Edit subscription functionality
  - Automatic event creation for changes
  - Track cost changes separately
- [ ] List all subscriptions (filterable by category, archive status)
- [ ] Subscription detail page showing:
  - Basic information
  - Payment history
  - Event history (audit trail)
- [ ] Archive/unarchive subscriptions
- [ ] Delete subscriptions (with confirmation)

### Payment Recording
- [ ] Manual payment recording interface
- [ ] Payment type selection (regular, price change, etc.)
- [ ] Payment history view per subscription

## Phase 2: Savings Tracking

### Individual Subscription Savings
- [ ] Calculate prorated savings target based on time elapsed
  - Example: $120/year subscription, 5 months in = $50 should be saved
- [ ] Display current savings progress on subscription detail
- [ ] Visual indicator (progress bar) for savings status
- [ ] Calculate monthly savings amount needed

### Category-Level Savings
- [ ] Aggregate savings progress for all subscriptions in category
- [ ] Category overview showing total savings needed
- [ ] Breakdown of per-subscription savings within category

### Renewal Month Budgeting
- [ ] Calculate target: full amount saved by month BEFORE renewal
  - Example: Aug 15 renewal = saved by July 31
  - August savings go to next payment period
- [ ] Display "must be saved by" date (end of month before renewal)
- [ ] Flag subscriptions approaching renewal month without full savings
- [ ] Monthly savings allocation view

## Phase 3: Dashboard & Reporting

### Main Dashboard
- [ ] Overview of all active subscriptions
- [ ] Total monthly cost across all subscriptions
- [ ] Total yearly cost projection
- [ ] Upcoming renewals (next 30/60/90 days)
- [ ] Subscriptions needing attention (approaching renewal without savings)
- [ ] Category breakdown visualization

### Savings Dashboard
- [ ] Total savings progress across all subscriptions
- [ ] Current month's required savings
- [ ] Next month's required savings
- [ ] "On track" vs "behind" indicators
- [ ] Category-level savings visualization

### Reports
- [ ] Spending by category over time
- [ ] Payment history report (all subscriptions)
- [ ] Cost change tracking (identify subscriptions with price increases)
- [ ] Archived subscriptions report
- [ ] Export capabilities (CSV/PDF)

### Analytics
- [ ] Average cost per subscription
- [ ] Most expensive subscriptions
- [ ] Subscription count by category
- [ ] Cost trends over time
- [ ] Savings efficiency metrics

## Phase 4: Automation & Enhancement

### Scheduled Tasks
- [ ] Automated payment reminders (via Symfony Scheduler)
- [ ] Savings progress notifications
- [ ] Monthly savings summary
- [ ] Upcoming renewal alerts

### Data Enhancement
- [ ] Logo upload/management
- [ ] Subscription notes/comments
- [ ] Tags for flexible organization
- [ ] Favorite/pin subscriptions

### Advanced Features
- [ ] Projected future costs
- [ ] "What if" scenarios (adding/removing subscriptions)
- [ ] Subscription comparison (e.g., monthly vs yearly cost comparison)
- [ ] Currency support (if needed for international services)

## Future Considerations

### Frontend Enhancement
- [ ] Set up Biome JS for linting/formatting
- [ ] Enhanced UI/UX with Stimulus controllers
- [ ] Responsive design optimization
- [ ] PWA capabilities for mobile access

### Data Management
- [ ] Import subscriptions (CSV)
- [ ] Export all data
- [ ] Backup/restore functionality

### Extended Tracking
- [ ] Free trial tracking (with conversion dates)
- [ ] Promotional pricing end dates
- [ ] Multi-currency support
- [ ] Shared subscriptions (split costs)

---

**Note**: API development is not planned at this time. Focus is on web interface for personal use.
