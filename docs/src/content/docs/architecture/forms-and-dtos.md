---
title: "Forms & DTOs"
---

Obol separates form input handling from domain entities using DTOs (Data Transfer Objects) and Symfony Form types.

## The Flow

```
HTTP Request → Controller → FormType (bound to DTO) → Validate DTO → Command → Handler → Entity
```

1. **Controller** creates a DTO and passes it to a Symfony `FormType`
2. **FormType** maps HTTP input to the DTO's properties
3. **Symfony Validator** validates constraints declared on the DTO
4. **Controller** extracts validated data from the DTO and dispatches a Command
5. **Handler** receives the command and creates or modifies the entity

DTOs carry the validation constraints — entities enforce their own invariants separately via `beberlei/assert`. This means validation happens at two layers: user input validation (DTO) and domain invariant enforcement (entity constructor).

## DTOs

Located in `src/Dto/`, organized by entity subdirectory:

| DTO | Purpose |
|-----|---------|
| `Subscription\CreateSubscriptionDto` | New subscription form data |
| `Subscription\UpdateSubscriptionDto` | Edit subscription form data |
| `Category\CreateCategoryDto` | New category form data |
| `Category\UpdateCategoryDto` | Edit category form data |
| `Payment\CreatePaymentDto` | New payment form data |

DTOs use Symfony Validator constraint attributes directly on properties:

```php
final class CreateCategoryDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';
}
```

## Form Types

Located in `src/Form/`, organized by entity subdirectory. All form type classes must end with `Type` (enforced by PHPStan).

| Form Type | DTO |
|-----------|-----|
| `Subscription\CreateSubscriptionFormType` | `CreateSubscriptionDto` |
| `Subscription\EditSubscriptionFormType` | `UpdateSubscriptionDto` |
| `Category\CreateCategoryFormType` | `CreateCategoryDto` |
| `Category\EditCategoryFormType` | `UpdateCategoryDto` |
| `Payment\CreatePaymentFormType` | `CreatePaymentDto` |

## Why DTOs Instead of Entities?

- **Entities use `public private(set)`** — forms cannot write to entity properties directly
- **Validation concerns differ** — form validation (e.g., "field is required") is distinct from domain invariants (e.g., "cost must be positive")
- **Decoupling** — the form contract is independent of the entity's internal structure
- **File uploads** — DTOs can hold `UploadedFile` objects, which entities should not

## CSRF protection

CSRF protection is stateless (same-origin, `config/packages/csrf.yaml`): a POST is accepted when it
carries a same-origin `Origin`/`Sec-Fetch-Site`/`Referer` header or double-submits the token. The
token id is `submit` for every form.

- **Form-component forms** get it automatically — `FormType` renders and validates the `submit` token
  as part of `handleRequest()`/`isValid()`. Nothing extra to do.
- **Hand-built one-click forms** (a bare `<form method="post">` with just a button — delete, archive,
  validate, the per-email actions) are not routed through the Form component, so each end must opt in:

  ```twig
  <form method="post" action="{{ path('subscription_delete', {id: subscription.id}) }}">
      <input type="hidden" name="_token" value="{{ csrf_token('submit') }}">
      <button type="submit">{{ 'common.action.delete'|trans }}</button>
  </form>
  ```

  ```php
  #[IsCsrfTokenValid(id: 'submit')]
  #[Route(path: '/app/subscriptions/{id}/delete', name: 'subscription_delete', methods: ['POST'])]
  public function __invoke(Ulid $id): RedirectResponse
  ```

  The attribute is enforced before the controller body, so a forged (cross-origin) request never runs
  the action; the `InvalidCsrfTokenException` it raises is an authentication failure, so the user is
  bounced to the login entry point. Any new hand-built POST form must carry both halves.

In tests, a crawler-submitted form (`$client->submit($form)`) is same-origin automatically; a direct
`$client->request('POST', ...)` is not, so `App\Tests\Support\SameOriginPostTrait::postSameOrigin()`
adds the token and Sec-Fetch-Site header to reach a protected controller body.
