# ADR-0022: CSRF protection follows the shape of the action (Form component vs. attribute)

- Status: Accepted
- Date: 2026-07-22

## Context

Every state-changing HTTP action needs CSRF protection. Obol runs Symfony's stateless, same-origin CSRF
(`config/packages/csrf.yaml`): a non-safe request is accepted when it carries a same-origin
`Origin`/`Sec-Fetch-Site`/`Referer` header or double-submits the `submit` token. There is a single token
id, `submit`, shared by every form.

Two shapes of state-changing action exist in the app, and they reach that protection differently:

- **Input-bearing actions** - create/edit a subscription, category, payment, payment source; log in; add
  an email. These bind user input to a DTO through a Symfony `FormType` (see the Forms & DTOs docs and
  ADR-0007). The Form component renders and validates the `submit` token as part of
  `handleRequest()`/`isValid()`, so CSRF is handled for free.
- **Intent-only one-click actions** - delete, archive, unarchive, validate a payment, promote/remove/
  resend an email, revoke a passkey, resend an admin login link. These carry no user input: the id is in
  the URL path, resolved to a `Ulid`, and the controller dispatches a command directly (ADR-0006/0007).
  They are bare `<form method="post">` markup with a single button, not routed through the Form
  component, so they render and validate no token unless they opt in.

The one-click forms once relied only on the session cookie's SameSite behaviour, which was merely
Symfony's default. Hardening them raised the question this ADR settles: should the one-click actions be
reshaped into (empty) Form-component forms for uniformity, or protected another way?

## Decision

**CSRF protection follows the shape of the action. Input-bearing actions go through the Form component;
intent-only one-click actions use `#[IsCsrfTokenValid(id: 'submit')]` on the controller plus a rendered
`_token` field. An architecture test enforces that every non-safe route uses one of the two.**

- **Input-bearing -> Form component + DTO.** If the action takes fields the user fills or selects, it is
  a `FormType` bound to a DTO, and CSRF rides along with form handling. This is already the pattern for
  create/edit/login/onboarding/email-add, and it keeps a single, coherent input path (validation and
  token in one place).
- **Intent-only -> the attribute.** A one-click action carries no input, so wrapping it in a FormType
  would mean an empty DTO and a zero-field form whose only job is the token - ceremony that dilutes the
  invariant that a DTO *is* validated user input. `#[IsCsrfTokenValid]` is the framework's purpose-built
  tool for validating a token on a request that is not a full form. The template renders
  `<input type="hidden" name="_token" value="{{ csrf_token('submit') }}">`; the controller carries
  `#[IsCsrfTokenValid(id: 'submit')]`. Both halves are required.
- **The security is identical either way.** Both mechanisms validate the same `submit` token against the
  same `SameOriginCsrfTokenManager`. The choice is about which tool fits the shape of the action, not
  about a difference in protection strength. A forged (cross-origin) request is rejected before the
  controller body runs; because the resulting `InvalidCsrfTokenException` is an authentication failure,
  the firewall bounces it to the login entry point rather than emitting a bare 403 - the action is
  refused either way.
- **An architecture test makes the rule structural.** `tests/Arch/ArchTest.php` asserts that every
  controller action mapped to a non-safe method (POST/PUT/PATCH/DELETE, or an unrestricted route that
  would accept them) is either Form-component-backed (its controller calls `createForm`/`handleRequest`)
  or carries `#[IsCsrfTokenValid]`. A new state-changing action that forgets both fails the build, so the
  rule cannot silently rot as forms are added. A behavioral test (`CsrfProtectionTest`) complements it by
  exercising the actual rejection.
- **One exemption, justified in place.** The magic-link check endpoint (`LoginCheckController`) accepts a
  POST that is neither a form nor an `#[IsCsrfTokenValid]` action: it is the login-link redemption,
  intercepted by the `login_link` authenticator and authenticated by the signed hash in the URL, not a
  CSRF token (ADR-0014). It is named in the arch test with that justification.

## Consequences

- The dividing line is legible and mechanical: to decide how a new action gets CSRF, ask whether it
  receives user input. If yes, it is a form; if no, it is a one-click action with the attribute.
- The two-file coupling of the one-click path (token in the template, attribute on the controller) is the
  one weak spot - either half can be forgotten. The arch test closes the structural half (an action with
  neither guard fails), and the behavioral test closes the runtime half. A template that renders no token
  while the controller demands one surfaces immediately as a broken form in that action's feature test.
- Adding an input-bearing action requires nothing for CSRF: the Form component covers it. Adding a
  one-click action requires both the `_token` field and the attribute, and the arch test refuses the
  change until they are present.
- The rule is a convention with a single carve-out, not a framework mechanism, so a genuinely new kind of
  endpoint (another authenticator-backed POST like the magic-link check) must be added to the exemption
  list with its own justification, the same way data-access and owner-scoping exemptions are handled in
  the arch tests.

## Alternatives considered

- **Route every one-click action through the Form component too, for uniformity.** Rejected: it would
  introduce empty DTOs and zero-field forms whose only purpose is emitting a token, making the Form
  component do work it is not for and muddying the "a DTO is validated input" invariant. The uniformity is
  cosmetic; the token check is identical either way.
- **Use `#[IsCsrfTokenValid]` everywhere, including on the input-bearing forms.** Rejected: the Form
  component already validates the token as part of `isValid()`, so adding the attribute would be a
  redundant second check, and it would split token handling away from the rest of form validation.
- **Rely on the SameSite cookie alone (no per-form token).** Rejected: SameSite is a single, coarse
  backstop tied to a framework default, not defense in depth. The app already runs stateless CSRF for its
  real forms; leaving the one-click actions outside it was an inconsistent gap.
- **Document the rule without enforcing it.** Rejected: the one-click path's two-file coupling makes a
  silent regression easy (a new form that forgets the attribute is unprotected but looks fine). A
  structural test is what turns the convention into a guarantee.
