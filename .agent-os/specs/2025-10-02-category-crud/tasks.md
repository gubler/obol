# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-02-category-crud/spec.md

> Created: 2025-10-02
> Status: Ready for Implementation

## Tasks

- [x] 1. Foundation: CQRS Commands, Queries, and Handlers
  - [x] 1.1 Write unit tests for CreateCategoryCommand, UpdateCategoryCommand, DeleteCategoryCommand
  - [x] 1.2 Implement CreateCategoryCommand with CreateCategoryDTO
  - [x] 1.3 Implement UpdateCategoryCommand with UpdateCategoryDTO
  - [x] 1.4 Implement DeleteCategoryCommand
  - [x] 1.5 Write unit tests for FindCategoryQuery, FindAllCategoriesQuery
  - [x] 1.6 Implement FindCategoryQuery and FindAllCategoriesQuery
  - [x] 1.7 Implement CommandHandler/Runner for create, update, delete operations
  - [x] 1.8 Implement QueryHandler/Runner for get and list operations
  - [x] 1.9 Verify all foundation tests pass

- [ ] 2. Controllers: List, Show, and Create Operations
  - [ ] 2.1 Write feature tests for ListCategoryController (index page rendering, empty state, category list display)
  - [ ] 2.2 Implement ListCategoryController as invokable extending AbstractBaseController
  - [ ] 2.3 Write feature tests for ShowCategoryController (category detail display, 404 handling)
  - [ ] 2.4 Implement ShowCategoryController as invokable extending AbstractBaseController
  - [ ] 2.5 Write feature tests for CreateCategoryController (form rendering, validation, successful creation)
  - [ ] 2.6 Implement CreateCategoryForm FormType
  - [ ] 2.7 Implement CreateCategoryController as invokable extending AbstractBaseController
  - [ ] 2.8 Verify all list, show, and create controller tests pass

- [ ] 3. Controllers: Edit and Delete Operations
  - [ ] 3.1 Write feature tests for EditCategoryController (form pre-population, validation, successful update, 404 handling)
  - [ ] 3.2 Implement EditCategoryForm FormType
  - [ ] 3.3 Implement EditCategoryController as invokable extending AbstractBaseController
  - [ ] 3.4 Write feature tests for DeleteCategoryController (successful deletion, 404 handling, redirect behavior)
  - [ ] 3.5 Implement DeleteCategoryController as invokable extending AbstractBaseController
  - [ ] 3.6 Verify all edit and delete controller tests pass

- [ ] 4. Views and Templates with Tailwind CSS
  - [ ] 4.1 Write feature tests verifying rendered HTML structure and Tailwind classes for list view
  - [ ] 4.2 Create category/list.html.twig template with Tailwind CSS styling
  - [ ] 4.3 Write feature tests verifying rendered HTML structure for show view
  - [ ] 4.4 Create category/show.html.twig template with Tailwind CSS styling
  - [ ] 4.5 Write feature tests verifying form rendering for create view
  - [ ] 4.6 Create category/create.html.twig template with form and Tailwind CSS styling
  - [ ] 4.7 Write feature tests verifying form rendering for edit view
  - [ ] 4.8 Create category/edit.html.twig template with form and Tailwind CSS styling
  - [ ] 4.9 Verify all view rendering tests pass

- [ ] 5. Integration and End-to-End Testing
  - [ ] 5.1 Write integration tests for complete CRUD workflows (create → edit → delete)
  - [ ] 5.2 Write integration tests for validation edge cases (duplicate names, empty values)
  - [ ] 5.3 Write integration tests for CQRS message bus routing (command.bus and query.bus)
  - [ ] 5.4 Write integration tests for Category relationships with Subscriptions
  - [ ] 5.5 Verify all integration tests pass
  - [ ] 5.6 Run full test suite to ensure no regressions
  - [ ] 5.7 Verify PHPStan level 9 compliance with mise run sa
  - [ ] 5.8 Verify code style compliance with mise run cs