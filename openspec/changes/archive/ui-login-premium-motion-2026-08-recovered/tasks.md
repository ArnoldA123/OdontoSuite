# Tasks: Login Premium Motion (`ui-login-premium-motion-2026-08`)

Change: `ui-login-premium-motion-2026-08`
Delivery strategy: `auto-chain` (stacked-to-main, 2 PRs)
Branch (current): `main`
Strict TDD: ACTIVE — test runner is `php artisan test` (PHPUnit only). No JS test runner.

---

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines (total) | ~610 (sum of PR1 ~330 + PR2 ~280) |
| Estimated changed lines (per PR) | PR1 ~330 / PR2 ~280 |
| 400-line budget risk | Low (each PR strictly under 400) |
| Chained PRs recommended | Yes |
| Suggested split | PR1 foundation → PR2 composition |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |
| Decision needed before apply | No (auto-chain proceeds; open questions resolved in user round Q1-Q3) |

Decision needed before apply: **No**
Chained PRs recommended: **Yes**
Chain strategy: **stacked-to-main**
400-line budget risk: **Low**

### Suggested Work Units

| Unit | Goal | PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|----|----------------------|-----------------|-------------------|
| PR1 | Foundation: install + composables + Button hook + PHPUnit | PR1 | `php artisan test --filter=MagneticHoverTest\|FieldValidationTest` | `php artisan test` | revert `package.json`, `app.js`, two composables, Button.vue additive CSS, two PHPUnit files |
| PR2 | LoginPage composition + 6 new source-inspection assertions + manual sweep | PR2 | `php artisan test --filter=LoginPageRenderTest\|AppShellTest` | `php artisan test` | revert `LoginPage.vue` only |

---

## Phase 1 — PR1 foundation (~330 lines)

### 1.1 Install `@vueuse/motion` + register plugin (RED → GREEN → commit)

- [x] 1.1.1 RED: Create `tests/Unit/Composables/AppShellTest.php::app_js_registers_motion_plugin` (source-grep `resources/js/app.js` for the literal string `MotionPlugin`). Run the test; confirm RED.
- [x] 1.1.2 GREEN: `pnpm add @vueuse/motion@3.0.3` from the project root; verify `package.json` has the dep and `pnpm-lock.yaml` resolves it.
- [x] 1.1.3 GREEN: In `resources/js/app.js`, add `import { MotionPlugin } from '@vueuse/motion'` at the top of the import block and `app.use(MotionPlugin)` immediately after `app.use(router)`.
- [x] 1.1.4 GREEN: Re-run `tests/Unit/Composables/AppShellTest.php` — confirm GREEN (the registration string is now present).
- [x] 1.1.5 GREEN: `pnpm build` — confirm bundle builds successfully.
- [ ] 1.1.7 Commit: orchestrator handles after user OK.

### 1.2 `useMagneticHover` composable + `Button.vue` hook (TDD: RED → GREEN → TRIANGULATE → REFACTOR)

- [x] 1.2.1 RED: Create `tests/Unit/Composables/MagneticHoverTest.php` with the first test case: `clamp_returns_zero_when_inside_max_distance`. The test reads the `computeOffset` helper via a small `node -e` script.
- [x] 1.2.2 GREEN: Create `resources/js/composables/magneticHoverMath.js` with the pure `computeOffset` (deviation D2 — extracted from design for parity with `useSpringMath.js`).
- [x] 1.2.3 GREEN: Create `resources/js/composables/useMagneticHover.js` wrapping `useSpring2D` + `computeOffset` + reduced-motion + coarse-pointer bypass. Re-exports `computeOffset` for public API.
- [x] 1.2.4 GREEN: Re-run first test — GREEN.
- [x] 1.2.5 TRIANGULATE: 3 more cases added (`clamp_caps_magnitude_at_max_distance_factor`, `clamp_handles_zero_bounds`, `reduced_motion_bypass_returns_inert`).
- [x] 1.2.6 GREEN: All 4 cases GREEN.
- [x] 1.2.7 RED: `MagneticHoverTest::button_vue_consumes_spring_magnet_vars` source-grep.
- [x] 1.2.8 GREEN: Additive scoped CSS in `Button.vue` (appended after `prefers-reduced-motion` block).
- [x] 1.2.9 GREEN: Re-run Button.vue source-grep — GREEN.
- [x] 1.2.10 GREEN: `UseSpringMathTest` regression check — 11/11 GREEN.
- [x] 1.2.11 REFACTOR: split into `magneticHoverMath.js` (pure) + `useMagneticHover.js` (Vue wrapper). Public API unchanged.
- [ ] 1.2.12 Commit: orchestrator handles after user OK.

### 1.3 `useFieldValidation` composable (TDD: RED → GREEN → TRIANGULATE → REFACTOR)

- [x] 1.3.1 RED: Create `tests/Unit/Composables/FieldValidationTest.php` with the first test case: `validate_field_runs_immediately`.
- [x] 1.3.2 GREEN: Create `resources/js/composables/fieldValidationMath.js` (pure state machine `attachValidator`, deviation D2).
- [x] 1.3.3 GREEN: Create `resources/js/composables/useFieldValidation.js` wrapping `attachValidator` with Vue `reactive` + `watch`.
- [x] 1.3.4 GREEN: Re-run first test — GREEN.
- [x] 1.3.5 TRIANGULATE: 4 more cases (`debounce_resets_on_rapid_input`, `success_immediate_when_rule_passes`, `clear_field_resets_both_states`, `validate_all_returns_false_when_any_field_errors`).
- [x] 1.3.6 GREEN: All 5 cases GREEN.
- [x] 1.3.7 RED (TRIANGULATE 2): timing test `debounce_is_250ms_within_50ms_tolerance`.
- [x] 1.3.8 GREEN: timing test GREEN (250ms ± 50ms).
- [x] 1.3.9 GREEN: full focused regression — `AppShellTest|MagneticHoverTest|FieldValidationTest|UseSpringMathTest` — 24/24 GREEN.
- [x] 1.3.10 REFACTOR: split into `fieldValidationMath.js` (pure) + `useFieldValidation.js` (Vue wrapper). Public API unchanged.
- [ ] 1.3.11 Commit: orchestrator handles after user OK.

### 1.4 PR1 merge gate

- [x] 1.4.1 Focused tests GREEN: `AppShellTest|MagneticHoverTest|FieldValidationTest|UseSpringMathTest` — 24 tests, 0 failures.
- [x] 1.4.2 `pnpm build` succeeds.
- [ ] 1.4.3 `pnpm lint:check` (orchestrator runs before commit; see apply-progress.md D1).
- [x] 1.4.4 Bundle size delta: **+12.95 kB gzip** (8% over the +12 kB aspirational budget; deviation D3 documented + accepted).
- [ ] 1.4.5 Open PR1 → review → merge to main (orchestrator handles).

---

## Phase 2 — PR2 composition (~280 lines)

### 2.1 Brand glyph SVG morph (pre-validation + template)

- [ ] 2.1.1 RED: In `tests/Unit/DesignSystem/LoginPageRenderTest.php`, add `login_page_brand_glyph_has_two_paths_with_matched_command_count` (source-grep for two `d=` attribute values; assert the command-letter counts match via a regex capturing `M`, `C`, `L`, `Z`).
- [ ] 2.1.2 Hand-build the check path: copy the existing tooth path's command-letter sequence (M5 C5 L0 Z1 per the comment in the template) and reshape the coordinates into a checkmark silhouette. Verify parity by hand + via a small Inkscape or `node -e` script that diffs command-letter counts.
- [ ] 2.1.3 GREEN: Update `resources/js/modules/auth/LoginPage.vue` template's brand-glyph SVG:
  - Wrap the existing `<path d="...">` in a `<g>`.
  - Add a second `<path>` for the check with matched command-letter count.
  - Add an `<animate attributeName="d" values="tooth;check" dur="0.6s" fill="freeze" />` element inside the `<g>`.
  - Add a `v-if="!prefersReducedMotion"` guard around the `<animate>` so reduced-motion users get an opacity cross-fade path instead.
- [ ] 2.1.4 GREEN: Add the opacity cross-fade branch (two `<g>` blocks with `opacity` transitions) in the same `<g>` wrapper, gated on `prefersReducedMotion`.
- [ ] 2.1.5 GREEN: Re-run `LoginPageRenderTest::login_page_brand_glyph_has_two_paths_with_matched_command_count` — confirm GREEN.
- [ ] 2.1.6 Commit: `feat(login): brand glyph tooth → check SMIL morph`.

### 2.2 Magnetic hover wiring (PR2 edits to LoginPage.vue)

- [ ] 2.2.1 RED: Add `login_page_uses_magnetic_hover_composable` to `LoginPageRenderTest.php` (source-grep for `useMagneticHover` import + `data-magnetic` attribute on the submit ref).
- [ ] 2.2.2 GREEN: In `LoginPage.vue`:
  - Import `useMagneticHover` from `@/composables/useMagneticHover`.
  - Add a `const submitRef = ref(null)` and wire the composable to it.
  - Bind `data-magnetic="true"` + `:ref="(el) => { submitRef.value = el }"` to the `<UiButton type="submit">` (note: the existing `<UiButton>` may not forward `ref` — verify; if not, switch to a wrapping `<div ref="submitRef">` around the button OR add a `ref` prop on UiButton if cheap).
- [ ] 2.2.3 GREEN: Re-run `LoginPageRenderTest::login_page_uses_magnetic_hover_composable` — confirm GREEN.
- [ ] 2.2.4 Commit: `feat(login): wire magnetic hover on submit button`.

### 2.3 Live validation wiring (PR2 edits to LoginPage.vue)

- [ ] 2.3.1 RED: Add `login_page_uses_field_validation_composable` to `LoginPageRenderTest.php` (source-grep for `useFieldValidation` import + replacement of the existing `errors.username`/`errors.password` reactive refs).
- [ ] 2.3.2 GREEN: In `LoginPage.vue`:
  - Import `useFieldValidation` from `@/composables/useFieldValidation`.
  - Define the rules object (one rule per field: `username` non-empty, `password` non-empty).
  - Replace the existing `errors` ref with the composable's `errors`.
  - Add `:class="{ 'field-success': successes[field] }"` + the success checkmark `<svg>` in each field's template.
  - Replace the existing `validateField` / `validateForm` functions with calls to `useFieldValidation`'s `validateField` / `validateAll`.
  - Add `@blur="validateField('username')"` (and password) to each input.
- [ ] 2.3.3 GREEN: Add `.field-success` scoped CSS: a small green checkmark icon, `opacity 0 → 1` over 200ms with `scale 0.8 → 1` (ease-ios). Reduced-motion block: opacity only.
- [ ] 2.3.4 GREEN: Re-run `LoginPageRenderTest::login_page_uses_field_validation_composable` — confirm GREEN.
- [ ] 2.3.5 GREEN: Run `php artisan test --filter=LoginPageRenderTest|FieldValidationTest` — confirm regression-guard green.
- [ ] 2.3.6 Commit: `feat(login): wire live form validation`.

### 2.4 Glassmorphism on form Card (PR2 edits to LoginPage.vue)

- [ ] 2.4.1 RED: Add `login_page_form_card_uses_glass_class` to `LoginPageRenderTest.php` (source-grep for `decorative-glass` class OR `backdrop-filter` + a `prefers-reduced-transparency` recovery rule in the same block).
- [ ] 2.4.2 GREEN: In `LoginPage.vue` template, add the `decorative-glass` class to the `.login-card-surface` Card wrapper (or to the parent `<div class="login-form-wrap">` if Card's root eats the class).
- [ ] 2.4.3 GREEN: Verify `.decorative-glass` is present in `resources/css/tokens.generated.css` (it is — generated). Run `node scripts/build-tokens-css.mjs` to regenerate; confirm exit code 0.
- [ ] 2.4.4 GREEN: Re-run `LoginPageRenderTest::login_page_form_card_uses_glass_class` — confirm GREEN.
- [ ] 2.4.5 GREEN: Add a `@media (prefers-reduced-transparency: reduce)` block in LoginPage.vue's scoped style that flattens the form Card surface to `background: var(--color-surface-elevated)` (defensive backstop; `.decorative-glass` already has its own collapse).
- [ ] 2.4.6 Commit: `feat(login): glassmorphism on form Card via .decorative-glass`.

### 2.5 Multi-stage loading + failure shake (PR2 edits to LoginPage.vue)

- [ ] 2.5.1 RED: Add `login_page_loading_has_multi_stage_indicator` to `LoginPageRenderTest.php` (source-grep for the three literal stage labels `Validando`, `Autenticando`, `Listo`).
- [ ] 2.5.2 GREEN: In `LoginPage.vue` template, wrap the form content in `<Transition name="login-loading">` and add the `.login-loading` block from `design.md` (with three stage labels + skeleton headline). Use `loading` reactive ref + a new `stage` reactive ref.
- [ ] 2.5.3 GREEN: Add `stageAdvance` setTimeout chain in `handleLogin` (stage 1 → 150ms → stage 2 → on API resolve → stage 3 → on success → start morph).
- [ ] 2.5.4 GREEN: Add the shake keyframe (6-stop) + reduced-motion opacity-flash block in scoped style.
- [ ] 2.5.5 GREEN: Add a `<div :class="['login-card-shake', { 'is-shaking': shakeTrigger }]">` wrapper around the form Card; bind `shakeTrigger` to `true` on error, then `false` after 220ms.
- [ ] 2.5.6 GREEN: Re-run `LoginPageRenderTest::login_page_loading_has_multi_stage_indicator` — confirm GREEN.
- [ ] 2.5.7 GREEN: Add `login_page_prefers_reduced_motion_collapses_all_new_motion` to `LoginPageRenderTest.php` (source-grep for ≥4 `@media (prefers-reduced-motion: reduce)` blocks in LoginPage.vue — one each for magnetic, validation, morph, loading).
- [ ] 2.5.8 GREEN: Re-run the reduced-motion test — confirm GREEN.
- [ ] 2.5.9 Commit: `feat(login): multi-stage loading + failure shake + per-motion reduced-motion`.

### 2.6 LoginPageRenderTest extension + regression gate

- [ ] 2.6.1 Run the full `LoginPageRenderTest.php` suite — all new + existing assertions green.
- [ ] 2.6.2 Run `php artisan test --testsuite=Unit` — full regression-guard green.
- [ ] 2.6.3 Run `pnpm build` — succeeds; bundle delta vs PR1 merge ≤ +0kb (composition only, no new deps).
- [ ] 2.6.4 Run `pnpm lint:check` — succeeds.

### 2.7 Manual Playwright sweep (documented in PR2 description)

- [ ] 2.7.1 At 1440x900 viewport: mouse over submit button → tilts toward cursor, bounces gently on mouse-leave.
- [ ] 2.7.2 At 1440x900 viewport: Tab to username, type 1 char, Tab away → error "El usuario es requerido" slides down.
- [ ] 2.7.3 At 1440x900 viewport: Tab to password, type 8+ chars, Tab away → green checkmark scales in.
- [ ] 2.7.4 At 1440x900 viewport: submit form (mock success) → 3-stage indicator advances → brand glyph morphs to check → route transitions.
- [ ] 2.7.5 At 1440x900 viewport: submit form (mock failure) → shake on form Card → form reappears with error.
- [ ] 2.7.6 Toggle DevTools `prefers-reduced-motion: reduce` and re-run 2.7.1-2.7.5: all motion collapses to opacity/colour.
- [ ] 2.7.7 Toggle DevTools `prefers-reduced-transparency: reduce`: form Card flattens to opaque.
- [ ] 2.7.8 At iPhone 12 viewport (390x844): all of the above pass without breaking readability.

### 2.8 PR2 merge gate

- [ ] 2.8.1 All 6 acceptance criteria for `LoginPageRenderTest.php` extensions are green.
- [ ] 2.8.2 `php artisan test --testsuite=Unit` all green.
- [ ] 2.8.3 `pnpm build` succeeds.
- [ ] 2.8.4 `pnpm lint:check` succeeds.
- [ ] 2.8.5 Manual Playwright sweep complete + screenshots attached to the PR.
- [ ] 2.8.6 Open PR2 → review → merge to main.

---

## Documentation nit to fix at archive time

- The `premium-design-foundation` spec from the prior slice already lists `motion.dampingBounce = 0.8` as unconsumed. This slice consumes a similar constant (`damping = 0.7`) inside the magnetic composable but does NOT promote it to a token. The archive report should note that the "bounce constant" is now domain-specific (magnetic = 0.7) rather than a project-wide token. No code change at archive time.

---

## Result

```yaml
status: success
executive_summary: |
  Two stacked PRs. PR1 foundation has 4 sub-phases (install, magnetic
  composable, validation composable, PR merge gate) totaling ~330 lines.
  PR2 composition has 8 sub-phases (morph, magnetic wiring, validation
  wiring, glass, loading, test extension, manual sweep, merge gate) totaling
  ~280 lines. Each PR has explicit RED → GREEN → TRIANGULATE → REFACTOR
  cycles where TDD applies (composable tests) and direct GREEN steps for
  template/wiring edits.
artifacts:
  - openspec/changes/ui-login-premium-motion-2026-08/tasks.md    (this file)
  - engram://sdd/ui-login-premium-motion-2026-08/tasks           (persisted)
next_recommended: sdd-apply (after a single user round of "go" — see Auto-chain note below)
risks:
  - SVG path parity is a hard gate (task 2.1.2). The apply phase must verify command-letter count equality BEFORE committing the morph template.
  - The magnetic composable is the first honest consumer of useSpring2D. If `useSpring2D` regresses under the new context (e.g. window resize), the magnetic effect drifts — task 1.2.2's wiring must include the resize/scroll refresh.
  - The PR2 manual sweep at iPhone 12 is the final gate. If the form Card's glassmorphism renders as a smear on small viewports, task 2.4.6's defensive collapse to opaque surface is the recovery.
auto_chain_note: |
  Tasks are designed for auto-chain. The apply phase runs them in order;
  a single user round-trip after PR1 lands is sufficient to gate PR2's
  manual sweep (task 2.7). No mid-phase pauses are required.
skill_resolution: paths-injected
```
