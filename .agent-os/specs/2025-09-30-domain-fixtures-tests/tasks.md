# Spec Tasks

## Tasks

- [ ] 1. Enhance Foundry Factories with Defaults and State Methods
  - [ ] 1.1 Write tests for CategoryFactory defaults and state methods
  - [ ] 1.2 Implement CategoryFactory with sensible defaults (name generation)
  - [ ] 1.3 Write tests for PaymentFactory defaults and state methods
  - [ ] 1.4 Implement PaymentFactory with defaults and state methods (->regular(), ->priceChange())
  - [ ] 1.5 Write tests for SubscriptionEventFactory defaults and state methods
  - [ ] 1.6 Implement SubscriptionEventFactory with defaults and state methods (->update(), ->costChange(), ->archive(), ->unarchive())
  - [ ] 1.7 Write tests for SubscriptionFactory defaults and state methods
  - [ ] 1.8 Implement SubscriptionFactory with defaults and state methods (->archived(), ->withRecentPayment(), ->expensiveSubscription())
  - [ ] 1.9 Verify all factory tests pass

- [ ] 2. Unit Tests and State Validation for Category Entity
  - [ ] 2.1 Write tests for Category creation with valid state
  - [ ] 2.2 Add beberlei assertions to Category constructor for non-empty name
  - [ ] 2.3 Write tests for Category invalid state scenarios (empty name)
  - [ ] 2.4 Verify all Category tests pass and invalid states throw exceptions

- [ ] 3. Unit Tests and State Validation for Payment Entity
  - [ ] 3.1 Write tests for Payment creation with valid state
  - [ ] 3.2 Add beberlei assertions to Payment constructor (positive amount, required fields)
  - [ ] 3.3 Write tests for Payment invalid state scenarios (zero/negative amount)
  - [ ] 3.4 Verify all Payment tests pass and invalid states throw exceptions

- [ ] 4. Unit Tests and State Validation for SubscriptionEvent Entity
  - [ ] 4.1 Write tests for SubscriptionEvent creation with all event types
  - [ ] 4.2 Add beberlei assertions to SubscriptionEvent constructor (required fields, array context)
  - [ ] 4.3 Write tests for SubscriptionEvent invalid state scenarios
  - [ ] 4.4 Verify all SubscriptionEvent tests pass and invalid states throw exceptions

- [ ] 5. Unit Tests and State Validation for Subscription Entity
  - [ ] 5.1 Write tests for Subscription creation with valid state
  - [ ] 5.2 Add beberlei assertions to Subscription constructor (positive cost/periodCount, non-empty name)
  - [ ] 5.3 Write tests for Subscription update method (general update only, cost change only, both, no change)
  - [ ] 5.4 Write tests for Subscription recordPayment method
  - [ ] 5.5 Write tests for Subscription archive/unarchive methods
  - [ ] 5.6 Write tests for Subscription invalid state scenarios
  - [ ] 5.7 Verify all Subscription tests pass and invalid states throw exceptions

- [ ] 6. Development Fixtures with Realistic Test Data
  - [ ] 6.1 Update AppFixtures to use Foundry factories
  - [ ] 6.2 Create 5-10 categories with descriptive names
  - [ ] 6.3 Create 20-30 subscriptions with variety (payment periods, costs, some archived)
  - [ ] 6.4 Create realistic payments for subscriptions (2-5 each)
  - [ ] 6.5 Create subscription events reflecting history
  - [ ] 6.6 Test fixtures load successfully with `php bin/console doctrine:fixtures:load`
  - [ ] 6.7 Verify all unit tests still pass after fixtures implementation
