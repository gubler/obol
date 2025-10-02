# Spec Tasks

## Tasks

- [x] 1. Enhance Foundry Factories with Defaults and State Methods
  - [x] 1.1 Write tests for CategoryFactory defaults and state methods
  - [x] 1.2 Implement CategoryFactory with sensible defaults (name generation)
  - [x] 1.3 Write tests for PaymentFactory defaults and state methods
  - [x] 1.4 Implement PaymentFactory with defaults and state methods (->regular(), ->generated())
  - [x] 1.5 Write tests for SubscriptionEventFactory defaults and state methods
  - [x] 1.6 Implement SubscriptionEventFactory with defaults and state methods (->update(), ->costChange(), ->archive(), ->unarchive())
  - [x] 1.7 Write tests for SubscriptionFactory defaults and state methods
  - [x] 1.8 Implement SubscriptionFactory with defaults and state methods (->archived(), ->withRecentPayment(), ->expensiveSubscription())
  - [x] 1.9 Verify all factory tests pass

- [x] 2. Unit Tests and State Validation for Category Entity
  - [x] 2.1 Write tests for Category creation with valid state
  - [x] 2.2 Add beberlei assertions to Category constructor for non-empty name
  - [x] 2.3 Write tests for Category invalid state scenarios (empty name)
  - [x] 2.4 Verify all Category tests pass and invalid states throw exceptions

- [x] 3. Unit Tests and State Validation for Payment Entity
  - [x] 3.1 Write tests for Payment creation with valid state
  - [x] 3.2 Add beberlei assertions to Payment constructor (positive amount, required fields)
  - [x] 3.3 Write tests for Payment invalid state scenarios (zero/negative amount)
  - [x] 3.4 Verify all Payment tests pass and invalid states throw exceptions

- [x] 4. Unit Tests and State Validation for SubscriptionEvent Entity
  - [x] 4.1 Write tests for SubscriptionEvent creation with all event types
  - [x] 4.2 Add beberlei assertions to SubscriptionEvent constructor (required fields, array context)
  - [x] 4.3 Write tests for SubscriptionEvent invalid state scenarios
  - [x] 4.4 Verify all SubscriptionEvent tests pass and invalid states throw exceptions

- [x] 5. Unit Tests and State Validation for Subscription Entity
  - [x] 5.1 Write tests for Subscription creation with valid state
  - [x] 5.2 Add beberlei assertions to Subscription constructor (positive cost/periodCount, non-empty name)
  - [x] 5.3 Write tests for Subscription update method (general update only, cost change only, both, no change)
  - [x] 5.4 Write tests for Subscription recordPayment method
  - [x] 5.5 Write tests for Subscription archive/unarchive methods
  - [x] 5.6 Write tests for Subscription invalid state scenarios
  - [x] 5.7 Verify all Subscription tests pass and invalid states throw exceptions

- [x] 6. Development Fixtures with Realistic Test Data
  - [x] 6.1 Update AppFixtures to use Foundry factories
  - [x] 6.2 Create 5-10 categories with descriptive names
  - [x] 6.3 Create 20-30 subscriptions with variety (payment periods, costs, some archived)
  - [x] 6.4 Create realistic payments for subscriptions (2-5 each)
  - [x] 6.5 Create subscription events reflecting history
  - [x] 6.6 Test fixtures load successfully with `php bin/console doctrine:fixtures:load`
  - [x] 6.7 Verify all unit tests still pass after fixtures implementation
