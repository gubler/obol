# Spec Requirements Document

> Spec: Domain Model Fixtures & Tests
> Created: 2025-09-30

## Overview

Establish comprehensive test coverage and reusable data fixtures for all domain entities (Category, Subscription, Payment, SubscriptionEvent) to ensure entities maintain valid state and business logic operates correctly. This foundation enables TDD for future features and provides development fixtures for local testing.

## User Stories

### Developer Testing Entities

As a developer, I want comprehensive unit tests for all domain entities, so that I can confidently modify business logic knowing tests will catch regressions.

**Workflow**: When implementing new features or refactoring existing code, run `mise run test` to verify all entity behavior remains correct. Tests document expected behavior and ensure entities can never be in invalid states through type safety and assertions.

### Developer Seeding Test Data

As a developer, I want Foundry factories for all entities, so that I can quickly create test data with specific states (archived, cost-changed, etc.) for both automated tests and local development.

**Workflow**: Use factory methods like `CategoryFactory::createOne()` or `SubscriptionFactory::new()->archived()->create()` to generate entities in specific states. Factories are reusable across unit tests and development fixtures, reducing boilerplate and ensuring consistency.

### Developer Verifying Valid States

As a developer, I want entities to enforce their own validity through type declarations and assertions, so that invalid states are impossible and I don't need to remember validation rules.

**Workflow**: When creating or updating entities, constructor parameters and update methods guarantee valid state. Type system and beberlei assertions prevent invalid data at compile/runtime, not just at form validation layer.

## Spec Scope

1. **Zenstruck Foundry Factories** - Create factory classes for Category, Subscription, Payment, and SubscriptionEvent with support for common states (archived subscriptions, different payment types, event types).

2. **Unit Tests for Category** - Test entity creation with valid state, name requirements, and relationship to subscriptions.

3. **Unit Tests for Subscription** - Test entity creation, update method scenarios (general update only, cost change only, both), payment recording, archive/unarchive state transitions, and event creation.

4. **Unit Tests for Payment** - Test entity creation with valid state, relationship to subscription, amount and date handling.

5. **Unit Tests for SubscriptionEvent** - Test event creation for different types (Update, CostChange, Archive, Unarchive), context storage, and immutability.

6. **Development Fixtures** - Create AppFixtures class that uses Foundry factories to seed realistic development data with variety of states.

7. **State Validation** - Ensure through tests that entities cannot be created or updated into invalid states via constructor requirements, type safety, and beberlei assertions.

## Out of Scope

- Integration tests for basic Doctrine operations (find, save, update) - trust that Doctrine works
- Repository custom query tests (will be added later when custom queries exist)
- Form validation (handled separately via Symfony Validator on DTOs)
- Frontend/controller testing
- Performance testing
- Database migration testing

## Expected Deliverable

1. All four entity factories (Category, Payment, Subscription, SubscriptionEvent) created using Zenstruck Foundry with state modifiers (e.g., `->archived()`, `->withCostChange()`).

2. Comprehensive unit test coverage for all entity methods and state transitions, runnable via `mise run test` or `php vendor/bin/pest`.

3. Development fixtures (AppFixtures) that create realistic test data using factories, loadable via `php bin/console doctrine:fixtures:load`.

4. All tests pass with green output, and entities demonstrably cannot be put into invalid states through type system and assertions.
