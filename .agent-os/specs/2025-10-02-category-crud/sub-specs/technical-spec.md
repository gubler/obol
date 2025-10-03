# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-02-category-crud/spec.md

## Technical Requirements

### Architecture Pattern

Use CQRS (Command Query Responsibility Segregation) with Symfony Messenger:
- **Commands** for state-changing operations (Create, Update, Delete) via `command.bus`
- **Queries** for read operations (Find) via `query.bus`
- **Handlers/Runners** contain business logic
- **Invokable Controllers** extend `AbstractBaseController`, dispatch commands/queries via wrapper methods, and handle HTTP concerns

### Controllers (Invokable)

Each action is a separate invokable controller in `src/Controller/Category/`.

All controllers should:
- Extend `AbstractBaseController`
- Be invokable (implement `__invoke()` method)
- Use method-level route attributes only (no class-level)
- Use `$this->dispatchCommand()` for commands
- Use `$this->dispatchQuery()` for queries
- Not contain business logic (delegate to handlers/runners)

**ListCategoriesController** - `src/Controller/Category/ListCategoriesController.php`
- Route: `GET /categories` → `category_index`
- Dispatches `FindAllCategoriesQuery` via `$this->dispatchQuery()`
- Renders `category/index.html.twig`

**ShowCategoryController** - `src/Controller/Category/ShowCategoryController.php`
- Route: `GET /categories/{id}` → `category_show`
- Dispatches `FindCategoryQuery` via `$this->dispatchQuery()`
- Renders `category/show.html.twig`

**CreateCategoryController** - `src/Controller/Category/CreateCategoryController.php`
- Routes:
  - `GET /categories/new` → `category_new` (show form)
  - `POST /categories/new` → `category_new` (process form)
- Uses `CreateCategoryDto` with form type
- On valid submission: dispatches `CreateCategoryCommand` via `$this->dispatchCommand()`
- Redirects to category index with flash message "Category created successfully"

**EditCategoryController** - `src/Controller/Category/EditCategoryController.php`
- Routes:
  - `GET /categories/{id}/edit` → `category_edit` (show form)
  - `POST /categories/{id}/edit` → `category_edit` (process form)
- Dispatches `FindCategoryQuery` to get current data for form
- Uses `UpdateCategoryDto` with form type
- On valid submission: dispatches `UpdateCategoryCommand` via `$this->dispatchCommand()`
- Redirects to category show with flash message "Category updated successfully"

**DeleteCategoryController** - `src/Controller/Category/DeleteCategoryController.php`
- Route: `POST /categories/{id}/delete` → `category_delete`
- Dispatches `DeleteCategoryCommand` via `$this->dispatchCommand()`
- Catches domain exceptions for validation errors (category has subscriptions)
- On success: redirects to category index with flash "Category deleted successfully"
- On error: redirects back with flash "Cannot delete category with subscriptions. Please reassign or delete subscriptions first."

### DTOs (Data Transfer Objects)

Location: `src/Dto/`

**CreateCategoryDto** - `src/Dto/CreateCategoryDto.php`
```php
final readonly class CreateCategoryDto
{
    public function __construct(
        public string $name = '',
    ) {}
}
```

**UpdateCategoryDto** - `src/Dto/UpdateCategoryDto.php`
```php
final readonly class UpdateCategoryDto
{
    public function __construct(
        public string $name = '',
    ) {}
}
```

### Forms

Location: `src/Form/`

**CreateCategoryType** - `src/Form/CreateCategoryType.php`
- Maps to `CreateCategoryDto` via `data_class` option
- Single field: `name` (TextType, required, max 255)
- Constraints: NotBlank, Length(max: 255)
- CSRF protection enabled

**UpdateCategoryType** - `src/Form/UpdateCategoryType.php`
- Maps to `UpdateCategoryDto` via `data_class` option
- Single field: `name` (TextType, required, max 255)
- Constraints: NotBlank, Length(max: 255)
- CSRF protection enabled

Forms must:
- Use `data_class` option pointing to DTO (never entity)
- NOT reference entity classes directly
- Follow Symfony form best practices

### Commands

Location: `src/Message/Command/`

**CreateCategoryCommand** - `src/Message/Command/CreateCategoryCommand.php`
```php
final readonly class CreateCategoryCommand
{
    public function __construct(
        public string $name,
    ) {}
}
```

**UpdateCategoryCommand** - `src/Message/Command/UpdateCategoryCommand.php`
```php
final readonly class UpdateCategoryCommand
{
    public function __construct(
        public string $categoryId, // ULID as string
        public string $name,
    ) {}
}
```

**DeleteCategoryCommand** - `src/Message/Command/DeleteCategoryCommand.php`
```php
final readonly class DeleteCategoryCommand
{
    public function __construct(
        public string $categoryId, // ULID as string
    ) {}
}
```

All commands should be:
- Final readonly classes
- Located in `src/Message/Command/`
- Named with `*Command` suffix

### Queries

Location: `src/Message/Query/`

**FindAllCategoriesQuery** - `src/Message/Query/FindAllCategoriesQuery.php`
```php
final readonly class FindAllCategoriesQuery
{
    // No parameters needed for finding all
}
```

**FindCategoryQuery** - `src/Message/Query/FindCategoryQuery.php`
```php
final readonly class FindCategoryQuery
{
    public function __construct(
        public string $categoryId, // ULID as string
    ) {}
}
```

All queries should be:
- Final readonly classes
- Located in `src/Message/Query/`
- Named with `Find*Query` pattern to match repository methods

### Command Handlers

Location: `src/Message/Command/`

**CreateCategoryHandler** - `src/Message/Command/CreateCategoryHandler.php`
- Validates name (trim, not empty - handled by DTO/Form already)
- Creates new Category entity via constructor
- Persists and flushes
- Returns void
- Attribute: `#[AsMessageHandler(bus: 'command.bus', handles: CreateCategoryCommand::class)]`

**UpdateCategoryHandler** - `src/Message/Command/UpdateCategoryHandler.php`
- Finds category by ID using repository (throw exception if not found)
- Updates category name using `setName()` method
- Flushes changes
- Returns void
- Attribute: `#[AsMessageHandler(bus: 'command.bus', handles: UpdateCategoryCommand::class)]`

**DeleteCategoryHandler** - `src/Message/Command/DeleteCategoryHandler.php`
- Finds category by ID using repository
- Checks if category has subscriptions (count > 0)
- If has subscriptions: throw domain exception (e.g., `CategoryHasSubscriptionsException`)
- If no subscriptions: removes category entity
- Flushes changes
- Returns void
- Attribute: `#[AsMessageHandler(bus: 'command.bus', handles: DeleteCategoryCommand::class)]`

All handlers should:
- Be located alongside their command in `src/Message/Command/`
- Be named `*Handler` (e.g., `CreateCategoryHandler`)
- Use `#[AsMessageHandler(bus: 'command.bus', handles: *Command::class)]`
- Inject CategoryRepository via constructor
- Inject EntityManagerInterface for persistence

### Query Runners

Location: `src/Message/Query/`

**FindAllCategoriesRunner** - `src/Message/Query/FindAllCategoriesRunner.php`
- Returns array of all categories with subscription counts
- Can use repository `findAll()` or custom query
- Consider eager loading subscription counts for efficiency
- Attribute: `#[AsMessageHandler(bus: 'query.bus', handles: FindAllCategoriesQuery::class)]`
- Return type: `array<Category>` or array of DTOs with counts

**FindCategoryRunner** - `src/Message/Query/FindCategoryRunner.php`
- Finds category by ID (throw exception if not found)
- Returns Category entity (can eager load subscriptions if needed)
- Attribute: `#[AsMessageHandler(bus: 'query.bus', handles: FindCategoryQuery::class)]`
- Return type: `Category`

All runners should:
- Be located alongside their query in `src/Message/Query/`
- Be named `*Runner` (e.g., `FindCategoryRunner`)
- Use `#[AsMessageHandler(bus: 'query.bus', handles: *Query::class)]`
- Inject CategoryRepository via constructor
- Return data (entities, DTOs, or arrays)

### Views

Location: `templates/category/`

**index.html.twig**
- Extends `base.html.twig`
- Shows table with columns: Name, Subscription Count, Actions (View, Edit, Delete)
- "New Category" button linking to create form
- Use Tailwind CSS for styling
- Delete action uses Turbo confirm

**new.html.twig**
- Extends `base.html.twig`
- Renders create form
- Cancel button returns to index
- Use Tailwind CSS for form styling

**show.html.twig**
- Extends `base.html.twig`
- Shows category name
- Lists all subscriptions in this category
- Actions: Edit, Delete, Back to List
- Use Tailwind CSS for styling

**edit.html.twig**
- Extends `base.html.twig`
- Renders edit form pre-filled with current name
- Cancel button returns to show page
- Use Tailwind CSS for form styling

**_delete_form.html.twig** (partial)
- Small form with just delete button
- CSRF token included
- POST to delete route
- Use Turbo confirm attribute

All templates should:
- Use Hotwired Turbo for seamless navigation
- Show flash messages at top (success/error)
- Use Tailwind CSS classes consistently
- Display validation errors inline on forms

### Business Rules

**Validation:**
- Category name: required (NotBlank), max 255 characters
- Name is trimmed before validation
- Cannot delete category with subscriptions

**Flash Messages:**
- Create success: "Category created successfully"
- Update success: "Category updated successfully"
- Delete success: "Category deleted successfully"
- Delete error: "Cannot delete category with subscriptions. Please reassign or delete subscriptions first."

**Error Handling:**
- Form validation errors: display inline
- Entity not found: let Symfony handle 404
- Domain exceptions: catch in controller, show flash message, redirect

### Testing Requirements

Location: `tests/Feature/Controller/Category/`

Create feature tests for each controller using Symfony's `WebTestCase`:

**ListCategoriesControllerTest.php**
- Test listing all categories
- Verify subscription counts are displayed

**ShowCategoryControllerTest.php**
- Test showing a category
- Test 404 for non-existent category

**CreateCategoryControllerTest.php**
- Test GET displays form
- Test POST with valid data creates category
- Test POST with invalid data (empty name) shows errors
- Verify redirect and flash message on success

**EditCategoryControllerTest.php**
- Test GET displays form with current data
- Test POST with valid data updates category
- Test POST with invalid data shows errors
- Verify redirect and flash message on success

**DeleteCategoryControllerTest.php**
- Test deleting category without subscriptions succeeds
- Test deleting category with subscriptions fails
- Verify flash messages for both cases

All tests should:
- Use WebTestCase for full HTTP request/response testing
- Use Foundry factories for test data
- Assert on HTTP status codes, redirects, flash messages
- Verify database state changes
