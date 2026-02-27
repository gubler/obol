# Forms & DTOs

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
