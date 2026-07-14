---
title: "Controllers"
---

All controllers follow the invokable single-action pattern: one controller class per route action, with a single `__invoke()` method.

## AbstractBaseController

Every controller extends `AbstractBaseController` (`src/Controller/AbstractBaseController.php`), which provides:

- **`CommandBus`** and **`QueryBus`** — injected via `#[Required]` setter injection
- **`LoggerInterface`** — the `appLogger` for structured logging
- **Flash message constants** — `FLASH_SUCCESS`, `FLASH_WARNING`, `FLASH_ERROR`, `FLASH_NOTICE`
- **`logFormErrors()`** — logs form validation failures as structured JSON
- **`currentUser()`** — the authenticated `User`. The app is authenticated-by-default (ADR-0014), so any action that runs has one; owner-scoped commands and queries take `currentUser()->id` as their `ownerUserId` (see [CQRS](./cqrs.md#owner-scoping) and ADR-0015)

### Why `#[Required]` instead of constructor injection?

PHPStan enforces a rule that `AbstractController` subclasses must not define a constructor (to avoid overriding Symfony's container-aware constructor). The `#[Required]` attribute on `autowireBaseController()` tells Symfony's DI to call the method after construction, achieving the same effect.

## Routing Conventions

- **Method-level `#[Route]` attributes only** — no class-level route prefixes (enforced by PHPStan)
- **Named routes required** — every route must have a `name` parameter for URL generation
- **No trailing slashes** — routes like `/app/subscriptions/` are forbidden
- **HTTP method restrictions** — every route specifies `methods: ['GET']`, `methods: ['POST']`, or `methods: ['GET', 'POST']`
- **URL surfaces (ADR-0018)** — authenticated application routes live under `/app` (protected by the `^/` deny-by-default firewall). Public routes stay at the root: the landing (`/`), login/magic-link, and the signed email-verification link (`/account/emails/{id}/verify`), which sits deliberately outside `/app` so it works from a logged-out mailbox.
- **Admin authorization (ADR-0019)** — the operator surface at `/app/admin/*` requires `ROLE_ADMIN`. It is guarded by an `access_control` rule (`^/app/admin`, above the `^/` `ROLE_USER` catch-all) and restated with `#[IsGranted('ROLE_ADMIN')]` on the admin controllers; the "Admin" nav link is gated by `is_granted('ROLE_ADMIN')`. `ROLE_ADMIN` is a value on `User.roles` (no schema change); it is granted from the console (`app:user:promote`), not the UI.

## Controller Inventory

### Category (`src/Controller/Category/`)

| Controller | Route | Methods | Action |
|-----------|-------|---------|--------|
| `ListCategoriesController` | `/app/categories` (`category_index`) | GET | List all categories |
| `ShowCategoryController` | `/app/categories/{id}` (`category_show`) | GET | Show one category |
| `CreateCategoryController` | `/app/categories/new` (`category_new`) | GET, POST | Create form + submit |
| `EditCategoryController` | `/app/categories/{id}/edit` (`category_edit`) | GET, POST | Edit form + submit |
| `DeleteCategoryController` | `/app/categories/{id}/delete` (`category_delete`) | POST | Delete |

### Subscription (`src/Controller/Subscription/`)

| Controller | Route | Methods | Action |
|-----------|-------|---------|--------|
| `ListSubscriptionsController` | `/app` (`subscription_index`) | GET | Dashboard (the app home) |
| `ShowSubscriptionController` | `/app/subscriptions/{id}` (`subscription_show`) | GET | Show one |
| `CreateSubscriptionController` | `/app/subscriptions/new` (`subscription_new`) | GET, POST | Create form + submit |
| `EditSubscriptionController` | `/app/subscriptions/{id}/edit` (`subscription_edit`) | GET, POST | Edit form + submit |
| `DeleteSubscriptionController` | `/app/subscriptions/{id}/delete` (`subscription_delete`) | POST | Delete |
| `ArchiveSubscriptionController` | `/app/subscriptions/{id}/archive` (`subscription_archive`) | POST | Archive |
| `UnarchiveSubscriptionController` | `/app/subscriptions/{id}/unarchive` (`subscription_unarchive`) | POST | Unarchive |

### Payment (`src/Controller/Payment/`)

| Controller | Route | Methods | Action |
|-----------|-------|---------|--------|
| `CreatePaymentController` | `/app/subscriptions/{id}/payments/new` (`payment_new`) | GET, POST | Record payment |
| `DeletePaymentController` | `/app/payments/{id}/delete` (`payment_delete`) | POST | Delete payment |

### Landing (`src/Controller/Landing/`)

The public front door, at the root and outside `/app` (ADR-0018). A single invokable owns both routes: `GET /` renders the landing (the same page for anonymous and signed-in visitors), and `POST /updates` captures the "sign up for updates" email.

| Controller | Route | Methods | Action |
|-----------|-------|---------|--------|
| `LandingController` | `/` (`landing`), `/updates` (`updates_subscribe`) | GET, POST | Render the landing; capture an updates-signup email |

The updates-signup email is dispatched as `SubscribeToUpdatesCommand`, whose handler currently only logs the interest - the seam for a future mailing-list integration.

## Typical Controller Pattern

```php
final class CreateSubscriptionController extends AbstractBaseController
{
    #[Route(path: '/app/subscriptions/new', name: 'subscription_new', methods: ['GET', 'POST'])]
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

## The account hub (sidebar sections)

The account settings hub (`/app/account/*`) is a two-column shell: a left sidebar lists the
sections, and the selected section renders on the right. The shell lives in
`templates/account/_hub.html.twig`; each section template extends it and fills
`{% block section %}`.

The sidebar is **data-driven**. Adding a section is three small steps, not a layout change:

1. Add a controller + route for the section (e.g. `account_billing`), rendering a template
   that extends `account/_hub.html.twig`.
2. Add one entry to the `sections` list in `_hub.html.twig` - its label, its route, and the
   route-name prefixes (`match`) that light the item up as active.
3. Add the sidebar label to the `account.hub.nav.*` translation keys.

The existing sections are **Preferences** (display name, currency, language, date & time
format, timezone) and **Access** (email addresses + passkeys). Flash messages for every
section render once in the shell, so section templates do not repeat the flash loop.

**Preferences is view-then-edit.** The section shows the settings read-only (so nothing
changes by accident) with an **Edit** link to `account_preferences_edit`. Both the view and the
edit page render a matching `<turbo-frame id="account-preferences">`, so with JS the edit form
swaps in place and on save the read-only view swaps back; without JS the link is a plain
navigation and the form posts and redirects normally. The edit form saves the display name and
the formatting settings in one `ChangePreferencesCommand`.

## The admin hub (operator surface)

The admin area (`/app/admin/*`) is the operator surface, behind `ROLE_ADMIN` (see ADR-0019). It
reuses the same data-driven two-column hub as the account settings: the shell lives in
`templates/admin/_hub.html.twig`, sections extend it and fill `{% block section %}`, and adding a
section is the same three steps (controller + route, one `sections` entry, an `admin.hub.nav.*`
label). The landing section is **Overview**; **System Toggles** (runtime system settings) and
**User management** (list, detail, invite, resend login link) are added as later sections.
