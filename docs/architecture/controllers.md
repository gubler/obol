# Controllers

All controllers follow the invokable single-action pattern: one controller class per route action, with a single `__invoke()` method.

## AbstractBaseController

Every controller extends `AbstractBaseController` (`src/Controller/AbstractBaseController.php`), which provides:

- **`CommandBus`** and **`QueryBus`** — injected via `#[Required]` setter injection
- **`LoggerInterface`** — the `appLogger` for structured logging
- **Flash message constants** — `FLASH_SUCCESS`, `FLASH_WARNING`, `FLASH_ERROR`, `FLASH_NOTICE`
- **`logFormErrors()`** — logs form validation failures as structured JSON

### Why `#[Required]` instead of constructor injection?

PHPStan enforces a rule that `AbstractController` subclasses must not define a constructor (to avoid overriding Symfony's container-aware constructor). The `#[Required]` attribute on `autowireBaseController()` tells Symfony's DI to call the method after construction, achieving the same effect.

## Routing Conventions

- **Method-level `#[Route]` attributes only** — no class-level route prefixes (enforced by PHPStan)
- **Named routes required** — every route must have a `name` parameter for URL generation
- **No trailing slashes** — routes like `/subscriptions/` are forbidden
- **HTTP method restrictions** — every route specifies `methods: ['GET']`, `methods: ['POST']`, or `methods: ['GET', 'POST']`

## Controller Inventory

### Category (`src/Controller/Category/`)

| Controller | Route | Methods | Action |
|-----------|-------|---------|--------|
| `ListCategoriesController` | `/categories` (`category_index`) | GET | List all categories |
| `ShowCategoryController` | `/categories/{id}` (`category_show`) | GET | Show one category |
| `CreateCategoryController` | `/categories/new` (`category_new`) | GET, POST | Create form + submit |
| `EditCategoryController` | `/categories/{id}/edit` (`category_edit`) | GET, POST | Edit form + submit |
| `DeleteCategoryController` | `/categories/{id}/delete` (`category_delete`) | POST | Delete |

### Subscription (`src/Controller/Subscription/`)

| Controller | Route | Methods | Action |
|-----------|-------|---------|--------|
| `ListSubscriptionsController` | `/` (`subscription_index`) | GET | List all (homepage) |
| `ShowSubscriptionController` | `/subscriptions/{id}` (`subscription_show`) | GET | Show one |
| `CreateSubscriptionController` | `/subscriptions/new` (`subscription_new`) | GET, POST | Create form + submit |
| `EditSubscriptionController` | `/subscriptions/{id}/edit` (`subscription_edit`) | GET, POST | Edit form + submit |
| `DeleteSubscriptionController` | `/subscriptions/{id}/delete` (`subscription_delete`) | POST | Delete |
| `ArchiveSubscriptionController` | `/subscriptions/{id}/archive` (`subscription_archive`) | POST | Archive |
| `UnarchiveSubscriptionController` | `/subscriptions/{id}/unarchive` (`subscription_unarchive`) | POST | Unarchive |

### Payment (`src/Controller/Payment/`)

| Controller | Route | Methods | Action |
|-----------|-------|---------|--------|
| `CreatePaymentController` | `/subscriptions/{id}/payments/new` (`payment_new`) | GET, POST | Record payment |
| `DeletePaymentController` | `/subscriptions/{subscriptionId}/payments/{id}/delete` (`payment_delete`) | POST | Delete payment |

## Typical Controller Pattern

```php
final class CreateSubscriptionController extends AbstractBaseController
{
    #[Route(path: '/subscriptions/new', name: 'subscription_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, FileUploader $fileUploader): Response
    {
        $dto = new CreateSubscriptionDto();
        $form = $this->createForm(type: CreateSubscriptionFormType::class, data: $dto);
        $form->handleRequest(request: $request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->commandBus->dispatch(command: new CreateSubscriptionCommand(...));
            $this->addFlash(type: self::FLASH_SUCCESS, message: 'Subscription created successfully');
            return $this->redirectToRoute(route: 'subscription_index');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors(form: $form);
        }

        return $this->render(view: 'subscription/new.html.twig', parameters: ['form' => $form]);
    }
}
```

Key points:

1. Create a DTO, bind it to a form
2. On valid submission: dispatch a command, flash a message, redirect
3. On invalid submission: log errors (form re-renders with validation messages automatically)
4. On GET: render the form template
