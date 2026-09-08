# Spec: login-live-validation

## Summary
Form validation runs as the user types — but debounced to "on blur OR 250ms idle" to avoid per-keystroke nag. Errors slide down with a transform + opacity transition. Success state shows a small checkmark that scales in. Implemented via the new `useFieldValidation` composable.

## Source-of-truth references
- Apple HIG: "validate on commit, not on change"
- The existing `LoginPage.vue` `validateField` + `validateForm` functions (submit-only today)
- The existing inline `<p class="field-error">` rendering pattern (NOT a toast)

## Scenarios

### V1 — Errors wait for blur OR 250ms idle
**Given** the user has just typed into the username field,
**When** 250ms have elapsed WITHOUT further input AND without the field being blurred,
**Then** the validation MUST run and the error MUST be rendered (if the rule fails).

**Given** the user is typing,
**When** the user blurs the field (Tab/click away) before the 250ms timer fires,
**Then** the validation MUST run immediately on blur.

**Given** the user is typing,
**When** the user resumes typing within the 250ms window,
**Then** the timer MUST reset (the previous timer MUST be cancelled and a new one scheduled).

### V2 — Success is immediate
**Given** a field's rules are all satisfied (e.g. password length ≥ 8),
**When** the user types the satisfying character,
**Then** the success indicator (small checkmark) MUST appear within the same render frame — no debounce on success.

### V3 — Error message slides down + fades
**Given** an error message is being rendered,
**When** it appears,
**Then** it MUST transition from `opacity: 0; transform: translateY(-4px)` to `opacity: 1; transform: translateY(0)` over `var(--motion-duration-normal)` (200ms) on the iOS curve.

### V4 — Input changes cancel and reschedule debounce
**Given** a 250ms debounce timer is pending for field `username`,
**When** the user types another character,
**Then** the previous timer MUST be cancelled via `clearTimeout`,
**And** a fresh 250ms timer MUST be scheduled.

### V5 — Composable exposes imperative methods
**Given** the composable is bound to a form with N fields,
**Then** it MUST expose:
- `validateField(field)` — runs the field's rules immediately and updates state.
- `validateAll()` — runs all fields' rules; returns a boolean indicating pass/fail.
- `errors` — reactive map of field → error message.
- `successes` — reactive map of field → boolean.

### V6 — Errors are inline + preserve aria-live
**Given** the auth failure path is the existing `<div class="auth-error" role="alert" aria-live="polite">`,
**Then** the live validation MUST render errors in the existing `<p class="field-error">` slot (NOT a toast),
**And** the auth-failure aria-live region MUST remain the single source of truth for the auth-failure announcement.

### V7 — Reduced-motion collapse
**Given** the user has `prefers-reduced-motion: reduce`,
**When** an error message appears or disappears,
**Then** the transform MUST be replaced by an opacity-only transition of `var(--motion-duration-normal)` (200ms).

## API contract (composable)

```js
// resources/js/composables/useFieldValidation.js
/**
 * @template T extends Record<string, any>
 * @param {import('vue').Ref<T>|T} form        reactive form state
 * @param {Record<keyof T, Array<(value: any, form: T) => string|null>>} rules
 * @param {object} [options]
 * @param {number} [options.idleMs=250]
 * @returns {{
 *   errors: import('vue').Ref<Record<string, string>>,
 *   successes: import('vue').Ref<Record<string, boolean>>,
 *   validateField: (field: keyof T) => void,
 *   validateAll: () => boolean,
 *   clearField: (field: keyof T) => void
 * }}
 */
export function useFieldValidation(form, rules, options = {})
```

## Acceptance criteria
- AC1: `useFieldValidation` is the public API; the existing `validateField`/`validateForm` functions in `LoginPage.vue` MUST be replaced by the composable's methods (no duplication).
- AC2: A `tests/Unit/Composables/FieldValidationTest.php` (pure state-machine + timing test) covers V1 (debounce), V2 (success immediate), V4 (timer reset).
- AC3: `tests/Unit/DesignSystem/LoginPageRenderTest.php` extends with `login_page_uses_field_validation_composable` (source-grep the import).
- AC4: No layout shift between empty/error/success states. The error `<p>` reserves space with `min-height` even when empty.
