# Design: CITAS Category Delta — `ui-rollout-all-modules-2026-08`

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | CITAS (Calendario + ConsultationWizard + NewAppointmentModal + Tipos de cita) |
| Date | 2026-08-12 |
| SDD phase | `design` (4 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/citas/design`) |
| Delivery strategy | `auto-chain` (inherited from global) |
| Review budget | 400 authored lines / PR (per global proposal §7.15) |
| Strict TDD | `true` (forward to apply/verify) |
| Parent design | `openspec/changes/ui-rollout-all-modules-2026-08/design.md` (PR0 + global primitives) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Sibling spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/citas/spec.md` |
| Sibling design | `openspec/changes/archive/2026-08-12-ui-pagos/categories/pagos/design.md` |
| CITAS PRs | `pr-citas-01..05` (5 chained sub-PRs — see §4) |

### What this document IS and IS NOT

**IS**: a CITAS-only delta on top of the global design. It maps the 3 citas routes + 3 components (`CalendarPage.vue`, `NewAppointmentModal.vue`, `ConsultationWizard.vue`, `AppointmentTypesPage.vue`, `AppointmentTypeDetailPage.vue`) onto the primitives, tokens, motion durations, focus-ring composition, hairline, canvas/surface separation, and `tabular-nums` decisions already locked in `design.md` §2 (StatusBadge API), §3 (canvasRoutes), §4 (PHPUnit invariants), §5 (cross-cutting rules). It enumerates the 5 CITAS sub-PRs, their dependency graph, the per-PR changed-line budget, and the per-module test strategy.

**IS NOT**: a re-derivation of the global design. Token names, primitive prop contracts, motion durations (`var(--motion-duration-fast) var(--motion-easing-ios)` for modal open + tab transitions; `var(--motion-duration-normal) var(--motion-easing-ios)` for card press), focus-ring composition (`var(--focus-ring-default)`), hairline (`rgba(60, 60, 67, 0.12)`), canvas/surface (`#F2F2F7` canvas, `#ffffff` systemBackground), and the `ModuleAppShellTestCase` rule set are ALL inherited from the global design — referenced here by path + section, never restated.

### Preflight snapshot

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

### Standing guard rails (inherited verbatim — see global design §5 + proposal §11.2)

1. `tokens.js` is frozen — no new tokens.
2. systemBackground `#ffffff` pinned; canvas `#F2F2F7` pinned.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)` for numerics.
7. `<script>` blocks of every CITAS module are NEVER edited in any PR.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only.
10. Code in English; conversation in Spanish (Peru).

---

## 1. Architectural intent

CITAS is the operational pivot of every dental appointment: receptionists schedule, doctors check in, billing waits for completed appointments, and patients see their next visit on `/calendar`. The global design (PR0) shipped the canvas surface for all 21 routes (2 of them CITAS: `/calendar`, `/appointment-types`) and the generic `<UiStatusBadge>` primitive. CITAS consumes these primitives and applies the global design rules mechanically to every `.vue` file under `modules/appointments/**` and `modules/appointment-types/**`. **No new tokens, no new primitives, no backend changes, no `<script>` edits.** All work is template-level class-string replacement against the global design §4.1 mapping table (PR0) and the per-module token usage in `design.md` §6.

The 5 CITAS-only deltas the global design does NOT enumerate (and that CITAS adds):

1. **ConsultationWizard tab strip migration**: inline `@click="currentStep = step.id"` raw `<button>` step strip → `<UiTabs v-model="currentStep">` with `var(--motion-duration-fast) var(--motion-easing-ios)` step transitions; ~50 raw `<input>` / `<textarea>` / `<select>` controls → `<UiInput>` / `<UiSelect>` + hairline; raw checkboxes → `<UiCheckbox>` (or `<UiStatusBadge>` indicator on required).
2. **CalendarPage status legend with 7 enum values**: the current 5-value legend is missing `no_show` and `rescheduled` (load-bearing bug per `database/migrations/2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php`). CITAS adds the 2 missing values via `<UiStatusBadge variant="neutral">` for `no_show` and `<UiStatusBadge variant="warning">` for `rescheduled`.
3. **NewAppointmentModal chrome**: hand-built `<Teleport>` modal with `bg-black bg-opacity-50` backdrop → `<UiModal>`; raw `<select>` / `<input>` borders → `<UiSelect>` / `<UiInput>` + hairline; duplicate-key 422 from `AppointmentService::createAppointment` rendered as a friendly "another desk booked this slot" message via template-level error mapping.
4. **AppointmentTypesPage filter bar**: inline raw `<select>` filter bar → `<UiSelect>`; `price` field uses `formatCurrency` from the canonical `useFormatters.js` location (depends on PAGOS PR-pagos-05 landing first).
5. **Existing-contract preservation**: `useConsultation` composable, `useEcho` `appointments` channel subscription, `AppointmentRepository::findConflicts` 3-axis overlap, `AppointmentService::createAppointment` timezone contract, and `ConfirmationToken` redaction stay byte-for-byte unchanged. CITAS does NOT fork any of these.

---

## 2. CITAS surface map

The global design §6 enumerates what every category MUST consume. The table below maps each CITAS route and component to the specific primitive set, tokens, and motion duration that apply.

| Surface (file path) | Primary primitive(s) | Token set | Motion duration | Touch scope (from proposal) |
|---|---|---|---|---|
| `resources/js/modules/appointments/CalendarPage.vue` | `<UiStatusBadge>` (status pills + legend for all 7 enum values), `<UiCard clickable>` (REPLACES `hover-lift` on appointment blocks), `<UiCard variant="elevated">` (REPLACES `bg-primary-50` today highlight), `<UiButton>` (view-toggle) | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast) var(--motion-easing-ios)` (legend dot hover only) | large |
| `resources/js/modules/appointments/ConsultationWizard.vue` | `<UiTabs v-model="currentStep">` (REPLACES raw `<button>` step strip + inline `@click`), `<UiInput>` / `<UiSelect>` / `<UiCheckbox>` (REPLACES ~50 raw form controls), `<UiButton>` (wizard submit), `<UiStatusBadge>` (mode/required indicators) | canvas, hairline, focus-ring on errors | `var(--motion-duration-fast) var(--motion-easing-ios)` (step transition: opacity + translateY ≤8px) | large |
| `resources/js/components/appointments/NewAppointmentModal.vue` | `<UiModal>` (REPLACES hand-built `<Teleport>` with `bg-black bg-opacity-50`), `<UiInput>` / `<UiSelect>` (REPLACES raw borders), `<UiButton>` (submit), `<UiLoadingSpinner>` (REPLACES `disabled:opacity-30`), `<UiStatusBadge>` (duplicate-key error mapping) | canvas, hairline, focus-ring on errors | `var(--motion-duration-fast)` (modal open) | medium |
| `resources/js/modules/appointment-types/AppointmentTypesPage.vue` | `<UiCard>` (filter card), `<UiSelect>` (REPLACES raw `<select>` filter bar), `<UiStatusBadge>` (active/inactive), `<UiButton>`, `tabular-nums` on `price` column, `formatCurrency` from `useFormatters.js` | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` (filter chip press) | medium |
| `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` | `<UiCard>`, `<UiInput>` / `<UiSelect>`, `<UiStatusBadge>` (active/inactive), `<UiButton>`, `formatCurrency` from `useFormatters.js` | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` | medium |

**Negative space (CITAS MUST NOT introduce)**:

- No raw `<button class="step">` for wizard step strip (use `<UiTabs>`).
- No `bg-black bg-opacity-50` overlay (use `<UiModal>` overlay token).
- No raw `<input class="border-theme">` or raw `<select class="border-theme">` (use `<UiInput>` / `<UiSelect>`).
- No `bg-primary-50` blocks (use `<UiCard>` or tokenised surface).
- No `hover-lift` on appointment blocks (use `<UiCard clickable>`).
- No hardcoded `bg-green-500 / bg-yellow-500 / bg-red-500` legend dots (use `<UiStatusBadge>` ramps).
- No `Intl.NumberFormat` calls outside `useFormatters.js` (use `formatCurrency`).
- No `S/ ${n.toFixed(2)}` patterns (use `formatCurrency`).
- No `disabled:opacity-30` affordance on submit (use `<UiLoadingSpinner>` + `disabled`).
- No `text-red-500` literal for required asterisks (use `<UiInput required>` indicator).
- No `.toISOString()` on `datetime-local` values (server interprets naive local time per `app.timezone`).
- No `bg-accent bg-opacity-5` selected state (use `<UiTabs>` active state).
- No FullCalendar internal overrides (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`, `.fc-toolbar`).
- No `ConfirmationToken` raw exposure on non-admin views.

---

## 3. CITAS-specific component decisions

### 3.1 Decision: ConsultationWizard tab strip migration

**Choice**: replace the raw `<button>` step strip and inline `@click="currentStep = step.id"` step navigation with `<UiTabs v-model="currentStep">`. The wizard's 5 steps (mode / SOAP evolution / procedures / materials / odontogram + attachments + summary) map onto `<UiTabs>` items. Step transition uses `var(--motion-duration-fast) var(--motion-easing-ios)` with minimal motion (single opacity fade + translateY ≤8px). The `currentStep` ref and the wizard's back/forward navigation contract (the "Atrás" / "Siguiente" buttons at the footer) are preserved byte-for-byte. The hardcoded `text-red-500` required asterisks become `<UiInput required>` indicator (the `<UiInput>` primitive already emits a tokenised required marker).

**Alternatives considered**:

- Keep raw `<button>` step strip and add transitions only — REJECTED. The global design §6.1 declares `<UiTabs>` the canonical primitive for tab strips; carrying raw `<button>` step strips breaks the canvas/surface separation rule (`DLR-R-001`).
- Full slide-in step transition (translateX per step) — REJECTED. Per the archive-report lesson at lines 47–57, over-animated transitions on step navigation feel sluggish for clinicians; minimal motion (opacity + translateY ≤8px) preserves the snappy iOS feel.

**Rationale**: `<UiTabs>` adoption is the minimal change that satisfies `CITAS-WIZ-001`. The wizard's back/forward footer buttons stay unchanged; only the step strip and the `v-model` binding migrate. `<script>` block of `ConsultationWizard.vue` is NEVER touched (per `CITAS-CON-001`). The rule is asserted by `CalendarAppShellTest::test_consultation_wizard_uses_ui_tabs` (new test method, ships with PR-citas-01).

### 3.2 Decision: CalendarPage 7-value status legend

**Choice**: add the 2 missing enum values (`no_show`, `rescheduled`) to the legend template, bringing it from 5 to 7. Variant mapping:

| Status enum (DB) | Localised label | `<UiStatusBadge variant>` |
|---|---|---|
| `scheduled` | Programada | `info` |
| `confirmed` | Confirmada | `success` |
| `in_progress` | En consulta | `warning` |
| `completed` | Completada | `neutral` |
| `cancelled` | Cancelada | `error` |
| `no_show` | No se presentó | `neutral` (distinct from `completed` via dot presence) |
| `rescheduled` | Reprogramada | `warning` (distinct from `in_progress` via dot absence + label) |

`no_show` and `rescheduled` map to `neutral` and `warning` respectively to avoid the perceptual collision that the proposal §8 risk #8 warned about (`success / error / info` are already taken; `warning` for `rescheduled` distinguishes it from `in_progress` because `in_progress` always shows the dot).

**Alternatives considered**:

- Keep 5-value legend and ignore `no_show` / `rescheduled` rendering — REJECTED. The legend IS the rule per `CITAS-CAL-001`; a doctor marking `no_show` must see a legend entry. Missing tokens = clinical safety bug.
- Add `no_show` / `rescheduled` as raw `<span>` outside `<UiStatusBadge>` — REJECTED. Token alignment requires the ramp; a raw `<span>` breaks `DLR-R-009`.

**Rationale**: adding the 2 missing values with distinct variants is the minimal change that satisfies `CITAS-CAL-001`. The rule is asserted by `ConsultationWizardStatusEnumTest::test_legend_references_all_7_enum_values` (new test method, ships with PR-citas-02). Visual verification confirms perceptual distinctness across the 7 ramps.

### 3.3 Decision: NewAppointmentModal chrome and duplicate-key error mapping

**Choice**: replace the hand-built `<Teleport to="body">` modal (uses `bg-black bg-opacity-50`, raw `<input>` / `<select>` borders, `disabled:opacity-30` affordance) with `<UiModal>`. The wrapper markup swap is mechanical:

- `<Teleport to="body">` + `<div class="fixed inset-0 bg-black bg-opacity-50">` → `<UiModal :open="showModal" @close="showModal = false" @confirm="submitAppointment" />`.
- `:disabled="!canSubmit"` + `class="disabled:opacity-30"` → `:disabled="!canSubmit || loading"` + inside-button `<UiLoadingSpinner v-if="loading" />`.
- The `open` / `close` / `confirm` emits are preserved byte-for-byte (per `CITAS-CON-001`).
- The duplicate-key 422 from `AppointmentService::createAppointment` (the DB unique constraint `unique_user_time_slot` / `unique_chair_time_slot` fires on the second commit) is mapped via template-level error rendering: `<UiStatusBadge variant="error" label="Otra mesa ya reservó este horario" />` shown when `error.code === 'duplicate_key'`. The mapping is template-only (no service-level change).

**Alternatives considered**:

- Wrap the existing `<Teleport>` block in a `<UiCard>` (keep the teleport) — REJECTED. `<UiModal>` is the canonical primitive; mixing teleport + UiCard breaks the canvas/surface separation rule (`DLR-R-001`).
- Map duplicate-key at the service level (catch `QueryException` in `AppointmentService`) — REJECTED. The service is out of scope (proposal §3.1); the mapping is template-level only.

**Rationale**: this is the lowest-risk migration because the `useApi()` wrapper owns the 401 redirect path, not the modal. The duplicate-key template-level mapping preserves the service untouched while rendering the friendly error. The rule is asserted by `CalendarAppShellTest::test_new_appointment_modal_uses_ui_modal` (new test method, ships with PR-citas-03) and visual verification at 1440×900 with `recep@test.com`.

### 3.4 Decision: AppointmentTypesPage filter bar and price formatter

**Choice**: replace the inline raw `<select>` filter bar with `<UiSelect>`. The `price` field uses `formatCurrency` from the canonical `useFormatters.js` location (renamed from `formatPENLabel` in PAGOS PR-pagos-05). The `tabular-nums` token is applied on the price column. The active/inactive status pill migrates to `<UiStatusBadge variant="success">` / `<UiStatusBadge variant="neutral">`.

**Alternatives considered**:

- Use raw `<select>` filter bar with tokenised chrome — REJECTED. `<UiSelect>` is the canonical primitive; carrying raw `<select>` breaks `DLR-R-009` (legacy alias ban).
- Local `Intl.NumberFormat` in `AppointmentTypesPage.vue` — REJECTED. The canonical formatter is `useFormatters.formatCurrency` (per PAGOS PR-pagos-05); a local reimplementation forks the surface and breaks `DLR-XCUT-007` analogue (formatCurrency consolidation, inherited as `CITAS-AT-001`).

**Rationale**: filter bar + price formatter migration is the minimal change that satisfies `CITAS-AT-001`. The dependency on PAGOS PR-pagos-05 is gating: if PAGOS slips, CITAS PR-citas-04 is held back. Fallback (per proposal §8 risk #6): import `formatCurrency` from a TEMPORARY local helper matching the canonical signature `(amount, options) => string`; PAGOS PR-pagos-05 deletes the temporary helper on landing. The rule is asserted by `AppointmentTypesAppShellTest::test_filter_bar_uses_ui_select` + `AppointmentPriceFormatterTest::test_format_currency_imported_from_canonical_location` (new test methods, ship with PR-citas-04).

### 3.5 Decision: Timezone contract preservation on `datetime-local` inputs

**Choice**: zero JS-side `.toISOString()` calls on any `datetime-local` input value in `NewAppointmentModal.vue` or `ConsultationWizard.vue`. The server interprets naive local time as `app.timezone` per `AppointmentService::createAppointment` (`Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'))`). The migration `2026_06_02_173228_fix_appointments_timezone_offset` exists precisely because this was once wrong — visual change MUST NOT regress.

**Alternatives considered**:

- Wrap `datetime-local` values in a `useTimezone()` composable — REJECTED. The composable surface is out of scope (proposal §2.4); the apply phase MUST NOT introduce new composables. The rule is "send the raw local string; let the server normalize."
- Apply UTC normalization in `useConsultation` — REJECTED. `<script>` blocks of CITAS modules are NEVER edited per `CITAS-CON-001`.

**Rationale**: this is a NEGATIVE-space decision (don't introduce `.toISOString()`). The grep-verified rule is enforced by `CalendarAppShellTest::test_no_js_side_to_iso_string_on_datetime_local` (new test method, ships with PR-citas-01 + PR-citas-03). Visual verification at 1440×900 confirms a receptionist booking at 14:30 local does not drift.

### 3.6 Decision: Conflict detection round-trip

**Choice**: zero client-side "no conflict" claims without round-tripping through `POST /api/appointments` (the `AppointmentRepository::findConflicts` 3-axis overlap check). The in-page block-count heuristic is stale by definition. UI feedback MAY render a count after the round-trip, but the source of truth is the server. The `NewAppointmentModal` does NOT introduce a local block-count heuristic; the conflict message (if any) reflects the server response.

**Alternatives considered**:

- Client-side block-count heuristic for instant feedback — REJECTED. Per `AppointmentRepository::findConflicts` 3-axis overlap (start-within / end-within / contains over user_id AND dental_chair_id), a client heuristic would be approximate and could mislead the receptionist. The server is the only oracle.
- Optimistic UI with server confirm — DEFERRED. The apply phase does NOT change the submit flow; visual change is template-level only.

**Rationale**: this is a NEGATIVE-space decision (don't introduce a client heuristic). The rule is enforced by `CalendarAppShellTest::test_no_client_side_conflict_heuristic` (new test method, ships with PR-citas-03).

### 3.7 Decision: ConfirmationToken redaction

**Choice**: `ConfirmationToken` model is NEVER rendered on `CalendarPage.vue`, `NewAppointmentModal.vue`, `ConsultationWizard.vue`, `AppointmentTypesPage.vue`, or `AppointmentTypeDetailPage.vue`. The token + hash are admin-internal only (used by the public-link confirmation flow via reminder emails, not by the staff/patient UI). Apply phase greps to verify: `git grep -nE 'ConfirmationToken|confirmation_token' resources/js/modules/appointments resources/js/modules/appointment-types` returns zero matches.

**Alternatives considered**:

- Display a masked token for staff — REJECTED. The token is information leak even when masked (length reveals secret length); the redaction rule per proposal §3.8 is "never expose token or hash to non-admin viewers".

**Rationale**: this is a NEGATIVE-space decision (don't render the token). The rule is enforced by `CalendarAppShellTest::test_no_confirmation_token_render` (new test method, ships with PR-citas-05).

### 3.8 Decision: WorkSchedule / AppointmentBlock UX prohibition

**Choice**: zero UX that implies the system enforces `WorkSchedule` or `AppointmentBlock` validation. Those validations are commented out in `AppointmentService::createAppointment` lines 75–89 ("profesionales trabajan 24/7"). The `work_schedules` and `appointment_blocks` tables exist but are NOT enforced. The `CalendarPage.vue` and `NewAppointmentModal.vue` do NOT render error text or disabled states that imply outside-hours blocking.

**Alternatives considered**:

- Render outside-hours disabled state — REJECTED. The system does NOT enforce work schedules; rendering a disabled state would be a lie to the receptionist.
- Render blocks as visual overlays — REJECTED. The data exists in the DB, but the system does not enforce them; rendering overlays without enforcement would mislead the receptionist.

**Rationale**: this is a NEGATIVE-space decision (don't imply enforcement). The rule is enforced by `CalendarAppShellTest::test_no_work_schedule_or_block_enforcement_ux` (new test method, ships with PR-citas-05).

---

## 4. CITAS PR slicing

The CITAS rollout splits into 5 chained sub-PRs. Each fits inside the 400-line review budget (global proposal §7.15). Each is independently buildable, testable, and revertible per `chained-pr` skill rules.

| PR | Name | Scope | Files touched | Estimated lines | Depends on |
|---|---|---|---|---|---|
| PR-citas-01 | `pr-citas-01-consultation-wizard` | `ConsultationWizard.vue` (~50 raw form controls → `<UiInput>` / `<UiSelect>` + hairline; raw `<button>` step strip → `<UiTabs v-model="currentStep">` with `var(--motion-duration-fast)` transitions; raw checkboxes → `<UiCheckbox>` or `<UiStatusBadge>` indicator; `text-red-500` required asterisks → `<UiInput required>`); extend `CalendarAppShellTest` with wizard assertions; add `test_no_js_side_to_iso_string_on_datetime_local` | 1 wizard + 1 test extension | ~390 (at budget; split into 01a + 01b if reviewer flags) | PR0 (landed) |
| PR-citas-02 | `pr-citas-02-calendar-page-and-legend` | `CalendarPage.vue` (status pills → `<UiStatusBadge>` for all 7 enum values; `bg-green-500 / bg-yellow-500 / bg-red-500` legend dots → `<UiStatusBadge>` ramps; `hover-lift` on appointment blocks → `<UiCard clickable>`; `bg-primary-50` today highlight → `<UiCard variant="elevated">`); FullCalendar internals NOT overridden; extend `CalendarAppShellTest` with legend assertions; new `ConsultationWizardStatusEnumTest` | 1 page + 1 test extension + 1 new test | ~340 | PR-citas-01 |
| PR-citas-03 | `pr-citas-03-new-appointment-modal` | `NewAppointmentModal.vue` (`<Teleport>` + `bg-black bg-opacity-50` → `<UiModal>`; raw `<select>` / `<input>` borders → `<UiSelect>` / `<UiInput>` + hairline; `disabled:opacity-30` → `<UiLoadingSpinner>`; duplicate-key 422 → `<UiStatusBadge variant="error">` template-level error mapping); extend `CalendarAppShellTest` with modal assertions; add `test_no_js_side_to_iso_string_on_datetime_local` + `test_no_client_side_conflict_heuristic` | 1 modal + 1 test extension | ~320 | PR-citas-01 + PR-citas-02 |
| PR-citas-04 | `pr-citas-04-appointment-types-admin` | `AppointmentTypesPage.vue` (filter bar → `<UiSelect>`; status pills → `<UiStatusBadge>`; `price` field → `formatCurrency` from `useFormatters.js`; `tabular-nums` on price column) + `AppointmentTypeDetailPage.vue` (same primitives); new `AppointmentTypesAppShellTest`; new `AppointmentPriceFormatterTest` | 2 pages + 2 new tests | ~360 (at budget; split into 04a + 04b if reviewer flags) | PR-citas-01..03 + PAGOS PR-pagos-05 |
| PR-citas-05 | `pr-citas-05-cross-cutting-tests-and-a11y-flag` | New `tests/Unit/DesignSystem/CalendarAppShellTest.php` (extends `ModuleAppShellTestCase`; covers `CalendarPage` + `NewAppointmentModal` + `ConsultationWizard`); new `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` (covers admin CRUD triplet); new `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/a11y-followup.md` (calendar grid `role="grid"` + per-cell `aria-label` follow-up + `textColor: '#ffffff'` luminance-resolver follow-up) | 2 new test files + 1 a11y doc | ~180 | PR-citas-01..04 |

### 4.1 Ordering rationale

- **High-risk form first (PR-citas-01)**: `ConsultationWizard.vue` is the densest form surface in CITAS (~50 raw controls). Doing it first establishes the `<UiInput>` / `<UiSelect>` / `<UiTabs>` rhythm for every subsequent PR. The 5-step navigation contract must keep back/forward working; this is the riskiest UX preservation.
- **Highest-traffic second (PR-citas-02)**: `CalendarPage.vue` is the hub surface (29.7 KB; most clinic traffic). The 7-value status legend is the load-bearing bug fix; landing it second means the legend variant mapping is battle-tested before the modal inherits the same `<UiStatusBadge>` ramps.
- **Real-money UX third (PR-citas-03)**: `NewAppointmentModal.vue` is the booking path. The duplicate-key 422 handling must stay intact; landing it third means the `<UiModal>` + `<UiStatusBadge>` pattern is already proven on the calendar legend before the modal adopts it.
- **Admin CRUD triplet fourth (PR-citas-04)**: `AppointmentTypesPage` + `AppointmentTypeDetailPage` are admin-only surfaces (lower traffic than calendar). Depends on PAGOS PR-pagos-05 for `formatCurrency` consolidation.
- **Cross-cutting tests + a11y flag last (PR-citas-05)**: tests + a11y doc ship after all UI is in place; the `CalendarAppShellTest` covers all three prior PRs in one consolidated test file.

### 4.2 Alternatives considered

- **Reverse order (PR-citas-05 first)**: rejected. The cross-cutting tests assert per-module rule (token reference exists, alias absent); landing them first means the test fails RED before any UI is in place → test is meaningless.
- **Bundle wizard + calendar (PR-citas-01 + 02 merged)**: rejected. Combined diff would exceed 700 lines (wizard ~390 + calendar ~340) → exceeds the 400-line budget and triggers the `chained-pr` split rule.
- **Land admin triplet first (PR-citas-04 first)**: rejected. The admin triplet is lower-traffic; landing it first means the highest-risk wizard + highest-traffic calendar land later, which raises the regression blast radius if a primitive swap breaks.
- **Skip PR-citas-05**: rejected. The cross-cutting tests are the durable regression guard for the rollout; without them, the wizard + calendar + modal rules are asserted only by per-PR ephemeral tests.

### 4.3 Budget breakdown per PR (additions + deletions counted for authored risk)

PR-citas-01: ~390 lines (wizard rewrite ~310 + test extension ~80).
PR-citas-02: ~340 lines (calendar rewrite ~250 + legend 7-value mapping ~30 + test extension ~40 + new test ~20).
PR-citas-03: ~320 lines (modal rewrite ~250 + duplicate-key mapping ~30 + test extension ~40).
PR-citas-04: ~360 lines (list page ~180 + detail page ~90 + 2 new tests ~90).
PR-citas-05: ~180 lines (2 new test files ~140 + a11y doc ~40).

Total authored lines across PR-citas-01..05: ~1,590 lines. No single PR exceeds 400 lines. Generated goldens (test snapshots, generated CSS) are excluded from the risk count per `sdd-phase-common.md` §E.

---

## 5. Apple-language faithfulness checklist

The global spec rows (`DLR-*`) apply to CITAS unmodified. The CITAS spec rows (`CITAS-*`) add category-specific edges. The table below confirms one-line compliance per applicable row.

| Spec row | Compliance (one-line confirmation) |
|---|---|
| `DLR-CORE-001` (canvas surface) | All 3 CITAS routes are in `AppLayout.canvasRoutes` (PR0 landed); no further work needed. |
| `DLR-CORE-008` (no `<style scoped>`) | All 5 CITAS PRs remove existing `<style scoped>` blocks (where present) and add none; `ModuleAppShellTestCase::test_no_style_scoped` green per module. |
| `DLR-R-001` (canvas background) | `CalendarPage.vue`, `NewAppointmentModal.vue`, `ConsultationWizard.vue`, `AppointmentTypesPage.vue`, `AppointmentTypeDetailPage.vue` already reference `bg-canvas` (PR0 effect); no template change needed. |
| `DLR-R-002` (hairline borders) | `border-theme` literals replaced by `border-hairline` (= `rgba(60, 60, 67, 0.12)` token) on `AppointmentTypesPage` table + `NewAppointmentModal` form fields. |
| `DLR-R-004` (composed focus ring) | `focus:ring-primary-500 focus:border-accent` literals replaced by `var(--focus-ring-default)`; `LegacyAliasForbiddenTest` extended per PR. |
| `DLR-R-007` (`tabular-nums`) | Applied on `AppointmentTypesPage` price column; uses `font-feature-settings: var(--font-features-tabular-nums)`. |
| `DLR-R-009` (legacy alias ban) | `LegacyAliasForbiddenTest::LEGACY_ALIASES` extended per PR (each CITAS PR adds the aliases it migrates away from). |
| `DLR-R-013` (no new dependencies) | CITAS consumes PR0 primitives only; no new npm or composer deps. |
| `DLR-R-017` (strict TDD) | Every UI replacement comes with a test that proves the new behaviour; RED-GREEN discipline per PR. |
| `DLR-R-019` (CI green) | `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm) green at every PR-citas-NN boundary. |
| `DLR-R-021` (no `<style scoped>`) | See `DLR-CORE-008` above. |
| `DLR-MOD-002` (Calendario) | `CalendarPage.vue` chrome tokenised; FullCalendar internals NOT overridden; 7-value status legend is the rule. |
| `DLR-MOD-006` (Tipos de cita) | `AppointmentTypesPage` + `AppointmentTypeDetailPage` tokenised as admin CRUD triplet half; `price` uses `formatCurrency` from `useFormatters.js`. |
| `CITAS-CAL-001` (7-value status legend) | Legend template references all 7 enum values via `<UiStatusBadge>`; `ConsultationWizardStatusEnumTest` asserts the rule. |
| `CITAS-WIZ-001` (Ui primitives in wizard) | `<UiInput>` / `<UiSelect>` / `<UiButton>` / `<UiStatusBadge>` / `<UiTabs v-model="currentStep">` exclusively; raw checkboxes → `<UiCheckbox>` or `<UiStatusBadge>` indicator. |
| `CITAS-MOD-001` (`<UiModal>` + duplicate-key mapping) | `<UiModal>` wrapper replaces hand-built `<Teleport>` + `bg-black bg-opacity-50`; duplicate-key 422 → `<UiStatusBadge variant="error">` template-level mapping. |
| `CITAS-AT-001` (admin CRUD triplet + canonical formatter) | `<UiSelect>` filter bar; `formatCurrency` from `useFormatters.js` (depends on PAGOS PR-pagos-05). |
| `CITAS-TZ-001` (timezone contract preservation) | Zero `.toISOString()` calls on `datetime-local` inputs in modal or wizard. |
| `CITAS-CONF-001` (conflict round-trip) | Zero client-side "no conflict" claims; `AppointmentRepository::findConflicts` is the only oracle. |
| `CITAS-RT-001` (Echo channel reuse) | `useEcho` `appointments` channel subscription preserved verbatim; no new channels. |
| `CITAS-WS-001` (WorkSchedule / AppointmentBlock prohibition) | Zero UX implies enforcement; `work_schedules` + `appointment_blocks` are NOT rendered as overlays or disabled states. |
| `CITAS-REV-001` (per-PR budget) | Each PR-citas-NN ≤ 400 lines; split rule applied (01a/01b, 04a/04b) if reviewer flags. |
| `CITAS-CON-001` (existing contracts preserved) | `useConsultation` / `useEcho` / `useFormatters` contracts unchanged; `<script>` blocks of CITAS modules byte-for-byte unchanged. |
| `CITAS-A11Y-001` (calendar grid a11y follow-up) | OPTIONAL row; flagged in `a11y-followup.md` for future slice; no test fails if `role="grid"` is not introduced. |

---

## 6. Test strategy

The CITAS rollout extends the PR0 test infrastructure (global design §4) with per-module structure tests + cross-cutting legend assertions. Strict TDD: every UI replacement comes with a test that proves the new behaviour.

### 6.1 Existing tests (MUST stay green at every PR-citas-NN boundary)

| Test file | What it asserts | Witness role |
|---|---|---|
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | `canvasRoutes` array literal contains all 21 routes (including 2 CITAS: `/calendar`, `/appointment-types`) | regression guard for canvas surface |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | No legacy alias (`bg-success-100`, `bg-primary-50`, `bg-black bg-opacity-50`, etc.) in polished files | regression guard for alias ban |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | `useConsultation` / `useEcho` / `useFormatters` contracts preserved | regression guard for composable surface |
| `tests/Unit/Services/AppointmentServiceTest.php` | `AppointmentService::createAppointment` unit (validation + audit + relationship loading) | regression guard for booking service |
| `tests/Feature/Api/AppointmentValidationTest.php` | StoreAppointmentRequest + UpdateAppointmentRequest validation rules | regression guard for booking validation |
| `tests/Feature/Api/AppointmentFillableTest.php` | Mass-assignment / fillable contract | regression guard for booking contract |
| `tests/Feature/Api/ReminderCrudTest.php` | Reminder CRUD + send endpoint | regression guard for reminder API |
| `tests/Feature/Api/ReminderTemplateCrudTest.php` | Reminder template CRUD (admin) | regression guard for reminder template API |
| `tests/Feature/Modules/ReminderDispatchTest.php` | ReminderProvider hourly dispatch end-to-end | regression guard for reminder dispatch |

### 6.2 New tests (per PR)

| PR | Test file | What it asserts | Extends |
|---|---|---|---|
| PR-citas-01 | (extension of `CalendarAppShellTest.php`) | `ConsultationWizard.vue` uses `<UiTabs v-model="currentStep">` (NOT inline `@click`); zero raw `<input class="border-theme">`; zero `.toISOString()` on `datetime-local`; `<script>` block byte-for-byte unchanged | extends base + new `assertWizardUsesUiTabs()` + `assertNoJsSideToIsoString()` |
| PR-citas-02 | (extension of `CalendarAppShellTest.php`) + new `tests/Unit/DesignSystem/ConsultationWizardStatusEnumTest.php` | `CalendarPage.vue` legend references all 7 enum values; status pills use `<UiStatusBadge variant="...">`; `hover-lift` absent; `bg-primary-50` absent; FullCalendar internal selectors absent | extends base + new `assertCalendarLegendReferencesAll7EnumValues()` + new `ConsultationWizardStatusEnumTest` |
| PR-citas-03 | (extension of `CalendarAppShellTest.php`) | `NewAppointmentModal.vue` uses `<UiModal>` (NOT `<Teleport>` + `bg-black bg-opacity-50`); `disabled:opacity-30` absent; `formatCurrency` import from `useFormatters`; duplicate-key 422 → `<UiStatusBadge variant="error">` mapping | extends base + new `assertNewAppointmentModalUsesUiModal()` + `assertNoClientSideConflictHeuristic()` |
| PR-citas-04 | new `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` + new `tests/Unit/DesignSystem/AppointmentPriceFormatterTest.php` | `AppointmentTypesPage.vue` filter bar uses `<UiSelect>`; `price` field uses `formatCurrency` from `useFormatters.js`; no `Intl.NumberFormat` outside canonical helper; `tabular-nums` on price column | `ModuleAppShellTestCase` + new `assertAppointmentTypesFilterBarUsesUiSelect()` + new `AppointmentPriceFormatterTest` |
| PR-citas-05 | new `tests/Unit/DesignSystem/CalendarAppShellTest.php` + new `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` | Consolidated per-module structure tests covering `CalendarPage` + `NewAppointmentModal` + `ConsultationWizard` + `AppointmentTypesPage` + `AppointmentTypeDetailPage`; asserts all module rules (canvas token, hairline, focus-ring, no `<style scoped>`, no legacy aliases); asserts `ConfirmationToken` redaction + `WorkSchedule` / `AppointmentBlock` prohibition | `ModuleAppShellTestCase` + new `assertNoConfirmationTokenRender()` + `assertNoWorkScheduleOrBlockEnforcementUx()` |

### 6.3 Per-PR RED-GREEN discipline

Per the archive-report lesson (global design §9.3 line 1: "test pins rule, not example"), every test method asserts a RULE, not a literal string:

- `test_legend_references_all_7_enum_values` — regex-based over the legend template; accepts any order, any spacing; fails only if one of the 7 enum values is missing.
- `test_no_legacy_border_theme_literal` — regex-based, not literal-string pin.
- `assertNewAppointmentModalUsesUiModal()` — checks `<UiModal>` wrapper is present and `bg-black bg-opacity-50` is absent; does NOT pin the exact wrapper class.
- `assertAppointmentTypesFilterBarUsesUiSelect()` — checks `<UiSelect>` is present in the filter bar; does NOT pin the exact filter options.
- `AppointmentPriceFormatterTest::test_format_currency_imported_from_canonical_location` — checks `formatCurrency` is imported from `useFormatters.js` (or its alias `formatPENLabel`); does NOT pin the import statement exactly.

---

## 7. Visual verification (per PR)

Every PR-citas-NN ships with a `playwright-cli` screenshot of the touched pages for visual regression. The screenshots are saved to `.playwright-cli/screenshots-rollout/` and reviewed against the global design §6 acceptance criteria.

| PR | Screenshots required | Credentials (per `CREDENTIALS.md`) |
|---|---|---|
| PR-citas-01 | `citas-wizard-1440x900.png` (step 1: mode); `citas-wizard-step-3-1440x900.png` (procedures); `citas-wizard-step-5-1440x900.png` (summary); `citas-wizard-back-forward-1440x900.png` (after navigating forward 3 + back 2) | `odontologo@test.com` |
| PR-citas-02 | `citas-calendar-1440x900.png` (week view); `citas-calendar-390x844.png` (receptionist mobile); `citas-calendar-legend-1440x900.png` (7-value legend close-up); `citas-calendar-no-show-rescheduled-1440x900.png` (a `no_show` and a `rescheduled` appointment rendered on the calendar) | `recep@test.com` |
| PR-citas-03 | `citas-new-appointment-modal-1440x900.png` (open); `citas-new-appointment-modal-duplicate-key-1440x900.png` (friendly error after second-desk race); `citas-new-appointment-modal-disabled-1440x900.png` (loading spinner) | `recep@test.com` |
| PR-citas-04 | `citas-appointment-types-1440x900.png` (list with `formatCurrency` price column); `citas-appointment-types-detail-1440x900.png` (detail page); `citas-appointment-types-filter-1440x900.png` (filter bar open) | `admin@test.com` |
| PR-citas-05 | (regression snapshots — re-run PR-citas-01..04 screenshots to confirm no visual drift from the consolidated tests) | same as the source PR |

### 7.1 Verification discipline

- Snapshots are saved as PNG (not JPEG) to preserve text sharpness for status badge ramps.
- Snapshots are reviewed for: legacy alias absence (`border-theme`, `bg-success-100`, `text-accent`, `bg-primary-50`, `bg-black bg-opacity-50`, `hover-lift`, `disabled:opacity-30`, etc.), canvas surface presence (`bg-canvas` visible), focus-ring composition (when tab-cycled), `tabular-nums` on numeric columns, `<UiStatusBadge>` ramps (no `bg-system*-100` heavy borders), wizard step strip `<UiTabs>` motion (single opacity + translateY ≤8px).
- The visual sweep is documented verification, not a CI gate (per global proposal §4.3).
- Manual smoke test for realtime: two browser tabs on `/calendar`; create an appointment in tab A; verify tab B receives the `AppointmentCreated` event within 1 second (per `CITAS-RT-001-1`).

---

## 8. Risks & mitigations

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | **ConsultationWizard 5-step navigation could break if `<UiTabs>` transitions interfere with the step change.** The current inline `@click="currentStep = step.id"` swaps steps instantly; the `<UiTabs>` transition adds opacity + translateY ≤8px which could leave the strip in a stuck state if the step content re-renders before the transition ends. | Medium | Apply phase: keep step transitions minimal (single opacity + translateY ≤8px); don't over-animate. Visual verification per PR-citas-01 with clinical role at 1440×900. Manual smoke test: enter wizard, navigate forward 3 steps, navigate back 2 steps, complete — verify no stuck-state on the step strip. `ComposablesStandardizationTest` stays green (the `currentStep` ref binding is preserved). |
| 2 | **`CalendarService::getCalendarData` line 101 hardcodes `textColor: '#ffffff'` against `appointmentType->color` (which can be any hex).** If the type color is yellow/light, text becomes unreadable. Color contrast is an existing a11y defect. | Medium | The rollout MUST NOT regress the contract (currently: hardcoded white, accept it as-is) — UI changes do NOT touch `CalendarService.php`. Flag in `a11y-followup.md` for a future a11y slice that introduces a luminance-based text-color resolver. Document the defect in the PR-citas-02 description. |
| 3 | **`useEcho` `appointments` channel subscription must keep firing after primitive swap.** Any `<script>` edit that accidentally removes `.listen(...)` or `echo.leave(...)` silently breaks realtime across the calendar. | Low | Apply phase scope rule: `<script>` blocks of `CalendarPage.vue`, `NewAppointmentModal.vue`, `ConsultationWizard.vue`, `AppointmentTypesPage.vue`, `AppointmentTypeDetailPage.vue` are NEVER touched (per `CITAS-CON-001`). Visual smoke test: open `/calendar` in two browser tabs, create an appointment in tab A, verify tab B receives the `AppointmentCreated` event within 1 second. |
| 4 | **`ConfirmationToken` exposure risk.** The `ConfirmationToken` model backs a public-link confirmation flow; visual change to the appointment card MUST NOT expose the token or its hash to non-admin viewers. | Low | `ConfirmationToken` is not rendered in `CalendarPage.vue`'s appointment card markup; the rollout does NOT add token rendering. Apply phase: verify `grep -r "ConfirmationToken\|confirmation_token" resources/js/modules/appointments resources/js/modules/appointment-types` returns zero matches. `CalendarAppShellTest::test_no_confirmation_token_render` asserts the rule. |
| 5 | **`WorkSchedule` + `AppointmentBlock` UX prohibition violation.** The validations are commented out in `AppointmentService` (lines 75–89) — "profesionales trabajan 24/7". UI must NOT imply the system enforces them. | Low | `WorkSchedule` + `AppointmentBlock` admin UIs are out of scope (no frontend surface yet). The rollout does NOT introduce UX that suggests enforcement. `CalendarAppShellTest::test_no_work_schedule_or_block_enforcement_ux` asserts the rule. |
| 6 | **`formatCurrency` consolidation in PAGOS PR-pagos-05 lands first; if that PR slips, `AppointmentTypesPage.price` cannot consume the canonical helper.** | Medium | PR-citas-04 is gated on PAGOS PR-pagos-05. If PAGOS slips, CITAS PR-citas-04 is held back. Fallback (per proposal §8 risk #6): import `formatCurrency` from a TEMPORARY local helper matching the canonical signature `(amount, options) => string`; PAGOS PR-pagos-05 deletes the temporary helper on landing. Apply phase MUST NOT ship a formatting fork. |
| 7 | **Chained PRs may exceed the 400-line budget.** PR-citas-01 (wizard, ~390 lines) and PR-citas-04 (admin triplet, ~360 lines) are both near the budget. | Medium | If a PR's diff exceeds 400 lines, split per `chained-pr` skill: PR-citas-01a (steps 1–3: mode + SOAP + procedures) + 01b (steps 4–5: materials + odontogram/summary); PR-citas-04a (`AppointmentTypesPage`) + 04b (`AppointmentTypeDetailPage`). |
| 8 | **Status legend variant collision.** Adding `no_show` (`neutral`) and `rescheduled` (`warning`) could overlap with existing `completed` (`neutral`) or `in_progress` (`warning`) if the dot presence alone does not distinguish them. | Low | `no_show` shows the dot (like `completed`); `rescheduled` does NOT show the dot (unlike `in_progress`). Visual verification per PR-citas-02: confirm legend swatches are perceptually distinct. `ConsultationWizardStatusEnumTest` asserts the 7 enum values map to distinct variant + dot combinations. |
| 9 | **Duplicate-key 422 mapping incomplete.** The `AppointmentService::createAppointment` wraps `Appointment::create` in a `DB::transaction`; the error bubbles as a `QueryException` with a SQLSTATE code, not a friendly HTTP 422 by default. The template-level mapping may miss edge cases (e.g. SQLSTATE `23000` with a non-unique-constraint cause). | Medium | Apply phase: map on `error.code === 'duplicate_key'` OR `error.sqlstate === '23000'` AND message contains `unique_user_time_slot` OR `unique_chair_time_slot`. Visual verification per PR-citas-03 with two browser tabs: book the same slot from both tabs → second tab shows the friendly error. |

---

## 9. File changes

### 9.1 Modified files (across PR-citas-01..05)

| File | Action | PR | Description |
|---|---|---|---|
| `resources/js/modules/appointments/ConsultationWizard.vue` | Modify | PR-citas-01 | Template class-string replacement; raw `<button>` step strip → `<UiTabs v-model="currentStep">`; raw `<input>` / `<textarea>` / `<select>` → `<UiInput>` / `<UiSelect>` + hairline; raw checkboxes → `<UiCheckbox>` or `<UiStatusBadge>` indicator; `text-red-500` asterisks → `<UiInput required>` indicator; `bg-accent bg-opacity-5` → `<UiTabs>` active state. `<script>` block byte-for-byte unchanged. |
| `resources/js/modules/appointments/CalendarPage.vue` | Modify | PR-citas-02 | Status pills → `<UiStatusBadge>` for all 7 enum values; `bg-green-500 / bg-yellow-500 / bg-red-500` legend dots → `<UiStatusBadge>` ramps; `hover-lift` on appointment blocks → `<UiCard clickable>`; `bg-primary-50` today highlight → `<UiCard variant="elevated">`. FullCalendar internals NOT overridden. `<script>` block byte-for-byte unchanged. |
| `resources/js/components/appointments/NewAppointmentModal.vue` | Modify | PR-citas-03 | `<Teleport>` + `bg-black bg-opacity-50` → `<UiModal>`; raw `<select>` / `<input>` borders → `<UiSelect>` / `<UiInput>` + hairline; `disabled:opacity-30` → `<UiLoadingSpinner>`; duplicate-key 422 → `<UiStatusBadge variant="error">` template-level mapping. `<script>` block byte-for-byte unchanged. |
| `resources/js/modules/appointment-types/AppointmentTypesPage.vue` | Modify | PR-citas-04 | Filter bar → `<UiSelect>`; status pills → `<UiStatusBadge>` (active/inactive); `price` field → `formatCurrency` from `useFormatters.js`; `tabular-nums` on price column. `<script>` block byte-for-byte unchanged. |
| `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` | Modify | PR-citas-04 | `<UiCard>` + `<UiInput>` + `<UiSelect>` + `<UiStatusBadge>`; `formatCurrency` from `useFormatters.js`. `<script>` block byte-for-byte unchanged. |

### 9.2 New files

| File | PR | Description |
|---|---|---|
| `tests/Unit/DesignSystem/CalendarAppShellTest.php` | PR-citas-05 | Extends `ModuleAppShellTestCase`; covers `CalendarPage` + `NewAppointmentModal` + `ConsultationWizard`. |
| `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` | PR-citas-05 | Extends `ModuleAppShellTestCase`; covers `AppointmentTypesPage` + `AppointmentTypeDetailPage`. |
| `tests/Unit/DesignSystem/ConsultationWizardStatusEnumTest.php` | PR-citas-02 | Asserts the 7-value status legend rule. |
| `tests/Unit/DesignSystem/AppointmentPriceFormatterTest.php` | PR-citas-04 | Asserts `formatCurrency` is imported from the canonical `useFormatters.js` location. |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/a11y-followup.md` | PR-citas-05 | Calendar grid `role="grid"` + per-cell `aria-label` follow-up + `textColor: '#ffffff'` luminance-resolver follow-up. |

### 9.3 Unchanged files (CITAS MUST NOT touch)

| File | Why frozen |
|---|---|
| `resources/js/components/ui/StatusBadge.vue` | PR0 primitive; immutable thereafter per global design §6.1. |
| `resources/js/components/ui/Modal.vue` / `Tabs.vue` / `Button.vue` / `Card.vue` / `Input.vue` / `Select.vue` / `LoadingSpinner.vue` / `EmptyState.vue` | Existing primitives; consumed as-is. |
| `AppLayout.canvasRoutes` array literal | PR0 one-shot extension; frozen per global design §3.4. |
| `tokens.js` / `tokens.generated.css` / `scripts/build-tokens-css.mjs` / `tailwind.config.js` | Frozen for entire rollout per `DLR-R-013`. |
| Backend (controllers, services, jobs, listeners, models, migrations) | Out of scope per proposal §3.1. |
| `<script>` blocks of every CITAS module | Per `CITAS-CON-001`; UI changes are template-level only. |
| `useConsultation.js` / `useEcho.js` / `useFormatters.js` | Composable surface preserved per `ComposablesStandardizationTest`. |
| `app/Services/AppointmentService.php` / `CalendarService.php` / `ConsultationService.php` / `ReminderService.php` / `AppointmentRepository.php` | Out of scope; `createAppointment` + `findConflicts` + `checkIn` + `complete` + `getCalendarData` byte-for-byte unchanged. |
| `database/migrations/2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php` | 7-value status enum source; no migration change. |
| `database/migrations/2026_06_02_173228_fix_appointments_timezone_offset.php` | Timezone correctness; contract preserved verbatim. |
| `ConfirmationToken` model | Never rendered to non-admin viewers per `CITAS-CONF-001` (negative-space). |

---

## 10. References

### 10.1 Spec files (CITAS contract)

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/citas/spec.md` | The 11 CITAS scenarios (`CITAS-CAL-001`, `CITAS-WIZ-001`, `CITAS-MOD-001`, `CITAS-AT-001`, `CITAS-TZ-001`, `CITAS-CONF-001`, `CITAS-RT-001`, `CITAS-WS-001`, `CITAS-REV-001`, `CITAS-CON-001`, `CITAS-A11Y-001`) — the contract this design satisfies. |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Cross-cutting `DLR-R-*` rules + per-module `DLR-MOD-002/006` rows — inherited unmodified. |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` | PR0 contract (`StatusBadge.vue`, `canvasRoutes`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`). |
| `design.md` | Global design — tokens, primitive API, motion durations, focus-ring composition, PHPUnit invariants. |

### 10.2 Source artifacts

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/explore.md` | CITAS inventory (frontend, backend, controllers, services, jobs, models, tests, known gotchas). |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/proposal.md` | CITAS proposal (intent, scope, risk register, PR chain, success criteria). |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §7.8 | Global PR chain (`PR7` Calendario + global `PR4` admin CRUD triplet). |
| `openspec/changes/archive/2026-08-12-ui-pagos/categories/pagos/design.md` | PAGOS design — reference for category delta structure + tone + section granularity. |
| `resources/js/composables/useConsultation.js` | Wizard composable — preserved verbatim per `CITAS-CON-001`. |
| `resources/js/composables/useEcho.js` | `appointments` channel subscription — preserved verbatim per `CITAS-RT-001`. |
| `resources/js/composables/useFormatters.js` | Target location for canonical `formatCurrency` (PAGOS PR-pagos-05; CITAS PR-citas-04 consumes). |
| `app/Services/AppointmentService.php` | `createAppointment` + conflict detection + timezone contract — out of scope. |
| `app/Services/CalendarService.php` line 101 | `textColor: '#ffffff'` hardcoded white — out of scope (a11y follow-up). |
| `app/Services/ConsultationService.php` | `checkIn` / `complete` — out of scope. |
| `app/Services/ReminderService.php` | `scheduleReminders` idempotent — out of scope. |
| `app/Repositories/AppointmentRepository.php` | 3-axis `findConflicts` — out of scope. |
| `app/Models/Appointment.php` | SoftDeletes + 7-value status enum — source for 7-value legend. |
| `database/migrations/2025_09_20_082341_create_appointments_table.php` | 7-value status enum source. |
| `database/migrations/2025_10_14_123001_fix_appointments_status_enum.php` | Status enum alignment (no_show / rescheduled). |
| `database/migrations/2026_06_02_173228_fix_appointments_timezone_offset.php` | Timezone correctness — contract preserved verbatim. |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Abstract base class for `*AppShellTest` subclasses. |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | Forbidden alias pin (extended per PR). |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | `useConsultation` contract guard. |
| `CREDENTIALS.md` | `recep@test.com` for calendar + modal; `odontologo@test.com` for wizard; `admin@test.com` for appointment-types CRUD. |

### 10.3 Process invariants

1. **Test pins rule, not example** (global design §9.3): `ModuleAppShellTestCase` + per-module subclasses assert RULES, not literal strings. The CITAS-specific test methods follow the same discipline.
2. **`<script>` blocks NEVER edited** (proposal §11.2 line 7): UI changes are template-level class-string replacement only. No CITAS PR edits `<script>` blocks of any module.
3. **Strict TDD forward** (proposal §4.5): every UI replacement comes with a test that proves the new behaviour; RED-GREEN per PR.
4. **Per-PR budget** (`CITAS-REV-001`): each `pr-citas-NN` ≤ 400 lines; split rule applied (01a/01b, 04a/04b) if reviewer flags.
5. **Existing contracts preserved** (`CITAS-CON-001`): `useConsultation` / `useEcho` / `useFormatters` contracts unchanged; `<script>` blocks of CITAS modules byte-for-byte unchanged.

---

## 11. What this design does NOT do

- Does NOT add new tokens. `tokens.js` is frozen.
- Does NOT add new primitives. CITAS consumes `<UiStatusBadge>` (PR0), `<UiModal>`, `<UiTabs>`, `<UiButton>`, `<UiInput>`, `<UiSelect>`, `<UiCard>`, `<UiLoadingSpinner>`, `<UiEmptyState>`, `<UiCheckbox>` from the proven set.
- Does NOT add dark mode.
- Does NOT touch the backend (no controller, no service, no listener, no migration, no job).
- Does NOT relax any standing guard rail from §0.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks of any CITAS module — UI changes are template-level only.
- Does NOT change `useConsultation` reactivity contract, debounce, or step navigation logic.
- Does NOT change `useEcho` `appointments` channel subscription (`.listen(...)` + `echo.leave(...)` stay verbatim).
- Does NOT alter `AppointmentService::createAppointment` conflict detection (the 3-axis `findConflicts` stays the only oracle).
- Does NOT alter the timezone contract (no JS-side `toISOString()` on `datetime-local`).
- Does NOT touch `CalendarService::getCalendarData` `textColor: '#ffffff'` (existing a11y defect; flagged for future slice).
- Does NOT add `role="grid"` + per-cell `aria-label` to the calendar grid (a11y follow-up; out of scope).
- Does NOT add `WorkSchedule` / `AppointmentBlock` / `WaitingList` admin frontend.
- Does NOT add a "edit recurring" feature (`AppointmentRecurrence` is single-edit semantics only).
- Does NOT expose `ConfirmationToken` to non-admin viewers.
- Does NOT introduce UX that suggests `WorkSchedule` / `AppointmentBlock` are enforced (they are commented out in `AppointmentService`).
- Does NOT alter the 7-value status enum semantics (only the legend rendering of all 7 values is the load-bearing fix).

---

*End of CITAS category design.*
