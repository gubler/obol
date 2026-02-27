# CQRS & Message Bus

Obol uses Symfony Messenger with three dedicated buses to enforce a strict separation between commands (writes), queries (reads), and events.

## Bus Configuration

Defined in `config/packages/messenger.yaml`:

| Bus | Service ID | Middleware | Purpose |
|-----|-----------|------------|---------|
| Command | `command.bus` (default) | dispatch_after_current_bus, validation, doctrine_transaction | State mutations |
| Query | `query.bus` | dispatch_after_current_bus, validation | Read operations |
| Event | `event.bus` | dispatch_after_current_bus, validation, doctrine_transaction | Domain events (allow_no_handlers) |

The command bus wraps handlers in a Doctrine transaction. The query bus does not, since reads should not modify state. The event bus allows dispatching events even when no handler is registered.

## Bus Wrapper Services

Three wrapper classes in `src/Lib/Bus/` provide a typed interface over the raw `MessageBusInterface`:

### CommandBus

```php
$result = $this->commandBus->dispatch(new CreateSubscriptionCommand(...));
```

Dispatches to `command.bus`, extracts the handler result from `HandledStamp`. Returns `null` if no stamp is present.

### QueryBus

```php
$subscriptions = $this->queryBus->query(new FindAllSubscriptionsQuery());
```

Dispatches to `query.bus`, extracts the result from `HandledStamp`. Throws `NoResultException` if no result is returned.

### EventBus

```php
$this->eventBus->dispatch(new SomeEvent());
```

Dispatches to `event.bus`. Fire-and-forget — no return value.

## Naming Conventions

Messages and their handlers follow strict naming patterns:

### Commands

| Pattern | Example |
|---------|---------|
| `{Action}{Entity}Command` | `CreateSubscriptionCommand` |
| `{Action}{Entity}Handler` | `CreateSubscriptionHandler` |

Commands are `final readonly` classes with public constructor-promoted properties. Handlers are tagged as `#[AsMessageHandler(bus: 'command.bus')]`.

### Queries

| Pattern | Example |
|---------|---------|
| `Find{All?}{Entity}Query` | `FindAllSubscriptionsQuery`, `FindSubscriptionQuery` |
| `Find{All?}{Entity}Runner` | `FindAllSubscriptionsRunner`, `FindSubscriptionRunner` |

Query handlers are called "Runners" (not "Handlers") to distinguish them from command handlers. Tagged as `#[AsMessageHandler(bus: 'query.bus')]`.

## Message Directory Structure

```
src/Message/
├── AsyncMessageInterface.php    # Marker for async routing
├── Command/
│   ├── Category/
│   │   ├── CreateCategoryCommand.php
│   │   ├── CreateCategoryHandler.php
│   │   ├── UpdateCategoryCommand.php
│   │   ├── UpdateCategoryHandler.php
│   │   ├── DeleteCategoryCommand.php
│   │   └── DeleteCategoryHandler.php
│   ├── Subscription/
│   │   ├── CreateSubscriptionCommand.php
│   │   ├── CreateSubscriptionHandler.php
│   │   ├── UpdateSubscriptionCommand.php
│   │   ├── UpdateSubscriptionHandler.php
│   │   ├── ArchiveSubscriptionCommand.php
│   │   ├── ArchiveSubscriptionHandler.php
│   │   ├── UnarchiveSubscriptionCommand.php
│   │   ├── UnarchiveSubscriptionHandler.php
│   │   ├── DeleteSubscriptionCommand.php
│   │   └── DeleteSubscriptionHandler.php
│   └── Payment/
│       ├── CreatePaymentCommand.php
│       ├── CreatePaymentHandler.php
│       ├── DeletePaymentCommand.php
│       └── DeletePaymentHandler.php
├── Query/
│   ├── Category/
│   │   ├── FindAllCategoriesQuery.php
│   │   ├── FindAllCategoriesRunner.php
│   │   ├── FindCategoryQuery.php
│   │   └── FindCategoryRunner.php
│   └── Subscription/
│       ├── FindAllSubscriptionsQuery.php
│       ├── FindAllSubscriptionsRunner.php
│       ├── FindSubscriptionQuery.php
│       └── FindSubscriptionRunner.php
└── Scheduler/
    ├── GeneratePaymentsMessage.php
    └── GeneratePaymentsHandler.php
```

## Async Transport

Any message class implementing `AsyncMessageInterface` is routed to the `async` Doctrine transport (stored in the `messenger_messages` table). This is currently used for email sending via `SendEmailMessage`.

## The Full Request Flow

A typical write operation:

1. **Controller** receives HTTP request, binds form to DTO, validates
2. **Controller** dispatches a Command via `$this->commandBus->dispatch(...)`
3. **CommandBus** wrapper sends the message to `command.bus`
4. **Messenger** runs middleware: dispatch_after_current_bus → validation → doctrine_transaction
5. **Handler** receives the command, loads entities from repositories, calls domain methods
6. **Entity** enforces invariants and emits events (e.g., `SubscriptionEvent`)
7. **Doctrine** flushes within the transaction middleware

A typical read operation:

1. **Controller** dispatches a Query via `$this->queryBus->query(...)`
2. **QueryBus** wrapper sends the message to `query.bus`
3. **Runner** executes the query using repository methods
4. **Runner** returns the result (entity or collection)
