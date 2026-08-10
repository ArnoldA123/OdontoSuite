# Delta for Visual Flow — Slice 07

Resolves 28 visual-flow findings (UXV-015..042, UXF-001..014). Adds Escape-to-close and focus traps to all modals (WCAG 2.1.1), surfaces 401 errors in `PaymentModal`, replaces `useApi.del` no-body calls with resource-aware calls, and validates empty states across list views.

## ADDED Requirements

### Requirement: Modal Escape Closes Dialog

The system MUST register a global `Escape` keydown listener on every `*Modal.vue` component that closes the dialog when pressed. The listener MUST be removed on unmount.

Evidence: 14 modals lacked Escape handling.

#### Scenario: Escape closes

- WHEN a modal is open and the user presses `Escape`
- THEN the modal emits `close` and the parent unmounts it

Test obligation: Component test (Vitest) + manual accessibility pass.

---

### Requirement: Modal Focus Trap Active

The system MUST trap focus inside any open `*Modal.vue` component so `Tab` cycles within dialog controls and focus returns to the trigger element on close. The trap MUST be scoped to `role="dialog"` and MUST NOT interfere with the FullCalendar widget outside the modal.

Evidence: WCAG 2.1.1; FullCalendar event handlers outside the modal must remain active.

#### Scenario: Tab cycles within dialog

- WHEN focus is on the first control and the user presses `Tab`
- THEN focus moves to the next focusable control inside the dialog
- AND on the last control, focus wraps back to the first

#### Scenario: FullCalendar active outside modal

- WHEN a modal is open over a FullCalendar view
- THEN the calendar's keyboard navigation works outside the modal (no trap leakage)

Test obligation: Component test + axe-core scan.

---

### Requirement: PaymentModal Surfaces 401 Errors

The system MUST display the backend `message` and `meta.message` from a 401 response inside `PaymentModal.vue` (no silent catch).

Evidence: Current `PaymentModal.vue` swallows 401 errors and shows only a generic "Algo salió mal" toast.

#### Scenario: 401 surfaced

- WHEN the payment preference request returns 401
- THEN the modal renders the server message verbatim
- AND the close button remains enabled

Test obligation: Component test with mocked 401 + Vitest.

---

### Requirement: useApi.del Accepts Body

The system MUST allow `useApi().del(url, { data: payload })` so callers can send a request body with DELETE (e.g. for soft-delete metadata). The implementation MUST use Axios `data` option, not `params`.

Evidence: 6 callsites needed body but the wrapper threw on `del(url)` with payload.

#### Scenario: delete with body

- WHEN `useApi().del('/items/1', { data: { reason: 'archived' } })` runs
- THEN the request includes the body
- AND the server receives it

Test obligation: Unit test on `useApi` wrapper.

---

### Requirement: Empty States for List Views

Every list view that may render zero rows MUST display an `EmptyState` component with an icon, a short Spanish message, and an optional CTA. The system MUST NOT render a blank `<table>` or `<ul>` with zero children.

Evidence: 11 list views rendered empty tables.

#### Scenario: empty list shows message

- WHEN a list endpoint returns `{ data: [], meta: { total: 0 } }`
- THEN the view renders the `EmptyState` component
- AND no `<table>` or `<ul>` is empty

Test obligation: Component test snapshot + manual visual diff.

---

### Requirement: Loading Skeletons Standardized

The system MUST replace ad-hoc spinners with a `SkeletonList` component on list views during initial fetch. The skeleton MUST match the row layout.

#### Scenario: skeleton during fetch

- WHEN a list endpoint is in-flight
- THEN the view renders `SkeletonList`
- AND replaces it with rows on response

Test obligation: Component test.

---

### Requirement: Confirmation Dialog for Destructive Actions

Every destructive action (DELETE, force-close session, hard-reset) MUST open a `ConfirmDialog` with the resource name and an explicit "Confirmar" button before submitting.

#### Scenario: confirm required

- WHEN user clicks "Eliminar"
- THEN a `ConfirmDialog` opens with the resource name
- AND the action only fires after confirm

Test obligation: Component test.

---

## MODIFIED Requirements

### Requirement: Modal Composable Standardized

The system MUST use a shared `useModal()` composable for `open`/`close`/Escape/focus-trap state. Existing per-component ad-hoc logic MUST be replaced.

(Previously: each modal reimplemented open/close/focus.)

#### Scenario: shared composable in use

- WHEN any modal is read
- THEN it imports `useModal` from `composables/useModal.js`

Test obligation: Grep + lint.

---

## REMOVED Requirements

None for this slice.

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| Modal Escape Closes | Component | `tests/components/ModalEscape.spec.js` |
| Modal Focus Trap | Component + a11y | `tests/components/ModalFocusTrap.spec.js` |
| PaymentModal 401 Surfaced | Component | `tests/components/PaymentModal.spec.js` |
| useApi.del Accepts Body | Unit | `tests/composables/useApi.spec.js` |
| Empty States | Component | per-list snapshot |
| Loading Skeletons | Component | per-list snapshot |
| Confirmation Dialog | Component | `tests/components/ConfirmDialog.spec.js` |
| Modal Composable Standardized | Lint | eslint rule |
