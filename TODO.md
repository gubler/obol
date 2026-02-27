# Project TODO - Ordered by Priority and Dependencies

This file tracks the implementation order for Gitea issues, taking into account dependencies between tasks.

## Legend
- **[In Progress]**: Currently being worked on
- **[Blocked]**: Waiting on other issues to be completed
- **[Ready]**: Can be started immediately
- **[Won't Do]**: Decided not to implement

---

## Current Focus: Subscription CRUD Core (Milestone 2)

### Ready to Work On
- **#13**: Create subscription message handlers **[In Progress]**
  - Status: Handlers created but need unit tests and verification
  - Files modified: CreateSubscriptionCommand/Handler, UpdateSubscriptionCommand/Handler, FindAllSubscriptionsRunner

- **#31**: Create archive/unarchive subscription handlers **[Ready]**
  - Depends on: None (can start immediately)
  - Will create: ArchiveSubscriptionCommand/Handler, UnarchiveSubscriptionCommand/Handler
  - Includes: Unit tests for both handlers

### Blocked - Waiting on Current Work
- **#14**: Update subscription controllers to use message handlers **[Blocked by #13, #31]**
  - Depends on: #13 and #31 must be complete
  - Controllers need to pass all DTO fields to commands
  - Files: CreateSubscriptionController, EditSubscriptionController

- **#16**: Add missing subscription form fields **[Blocked by #14]**
  - Depends on: #14 must be complete
  - Missing field: lastPaidDate (DateType)
  - Files: CreateSubscriptionFormType, EditSubscriptionFormType

- **#15**: Add subscription integration tests **[Blocked by #14, #16]**
  - Depends on: #14 and #16 must be complete
  - Tests full workflow through HTTP layer
  - ~8 test files for all subscription controllers

### Other Subscription Issues (Lower Priority)
- **#4**: Subscription Display
- **#5**: Subscription Creation
- **#6**: Subscription editing
- **#7**: Subscription Archiving
- **#8**: Subscription Deletion

### Won't Do
- **#29**: Upgrade to PHP 8.5 **[Won't Do]**
  - Reason: Pest doesn't support PHP 8.5, and we're staying with PHPUnit
  - Already on PHP 8.5 system-wide, but keeping composer.json at >=8.4

- **#30**: Install and configure Pest PHP **[Won't Do]**
  - Reason: Pest 4.x doesn't support Symfony 8 or PHP 8.5
  - Staying with PHPUnit

---

## Milestone 3: Payment Management

### Logical Order
1. **#17**: Create payment DTOs and form types **[Ready after Subscription CRUD]**
2. **#18**: Create payment message handlers **[Blocked by #17]**
3. **#19**: Create payment controllers **[Blocked by #18]**
4. **#20**: Add payment templates **[Blocked by #19]**
5. **#21**: Add payment integration tests **[Blocked by #19, #20]**

---

## Milestone 4: Scheduled Tasks

### Logical Order
1. **#22**: Implement payment generation scheduler **[Blocked by Payment Management]**
2. **#23**: Add scheduler tests **[Blocked by #22]**
3. **#24**: Document scheduler setup and usage **[Blocked by #22, #23]**

---

## Milestone 5: Homepage & Reports

### Logical Order
1. **#25**: Homepage subscription listing with grouping **[Blocked by Subscription CRUD]**
2. **#26**: Add cost and savings calculations **[Blocked by #25]**
3. **#27**: Add sorting and filtering options **[Blocked by #25]**
4. **#28**: Design and implement reports **[Blocked by #25, #26, #27]**

---

## Milestone 1: Docker Infrastructure (Optional/Low Priority)

These can be done anytime, independent of other work:

1. **#9**: Create Dockerfile for PHP application **[Ready]**
2. **#10**: Update compose.yaml with application services **[Blocked by #9]**
3. **#11**: Add Docker environment configuration **[Blocked by #10]**
4. **#12**: Document Docker setup and usage **[Blocked by #9, #10, #11]**

---

## Next Steps (Immediate)

1. Complete #13: Add unit tests for existing subscription handlers
2. Complete #31: Create archive/unarchive handlers with tests
3. Run full test suite and static analysis
4. Commit and close #13 and #31
5. Start #14: Update controllers to use all handler fields
6. Start #16: Add missing form fields
7. Complete #15: Add integration tests

---

## Notes

- All subscription CRUD work (Milestone 2) should be completed before starting Payment Management
- Docker work can be done independently at any time
- Homepage features require completed subscription CRUD
- Scheduled tasks require completed payment management
