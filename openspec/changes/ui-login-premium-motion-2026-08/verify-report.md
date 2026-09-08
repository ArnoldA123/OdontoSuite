# Verify Report: Login Premium Motion (`ui-login-premium-motion-2026-08`)

Change: `ui-login-premium-motion-2026-08`
Phase: verify — **PR1 foundation ONLY** (PR2 is not implemented yet)
Branch: `feat/ui-login-premium-motion-2026-08`
Working tree vs `main`: 4 modified + 7 untracked = the PR1 delta on disk, **not yet committed** (no commits ahead of `main`).
Strict TDD: ACTIVE (per-task granularity).
Reviewed by: SDD verify executor (read-only — no fixes applied).

---

## Verdict: **PASS** (PR1 foundation) with archive DEFERRED on orchestrator-handled git steps

Zero CRITICAL findings block PR1's foundation deliverable. All PR1-scoped tests are GREEN, `pnpm build` succeeds, the bundle delta matches the documented D3 deviation, and PR2 files are bit-identical to `main`. Archive is **deferred** (not blocked) on five `- [ ]` items in `tasks.md` § Phase 1 that are explicitly labelled "orchestrator handles" — these are git operations (commit + lint + PR) the apply phase correctly did not perform.

---

## Verification commands executed (and exact results)

| Command | Result |
|---|---|
| `php artisan test tests/Unit/Composables/AppShellTest.php tests/Unit/Composables/MagneticHoverTest.php tests/Unit/Composables/FieldValidationTest.php` | **13 passed (41 assertions)** in 1.88s |
| `php artisan test tests/Unit/DesignSystem/UseSpringMathTest.php` | **11 passed (29 assertions)** in 1.09s (regression sanity — task 1.2.10 / 1.3.8) |
| `pnpm build` | **built in 7.67s**; chunks emitted including `app-DntzPqxE.js` at **479.82 kB raw / 152.94 kB gzip** |
| `grep -n "MotionPlugin\|@vueuse/motion" resources/js/app.js` | `4:import { MotionPlugin } from '@vueuse/motion'` and `264:app.use(MotionPlugin)` (B2 ✓) |
| `grep -n "@vueuse/motion" package.json` | `38:        "@vueuse/motion": "3.0.3",` (exact pin, no caret — AC3 / B1 ✓) |
| `git diff main --stat` (working-tree changes; no commits ahead of main) | `package.json` +1, `pnpm-lock.yaml` +393, `resources/js/app.js` +2, `resources/js/components/ui/Button.vue` +21 — **4 files, 417 insertions, 0 deletions** |

---

## Spec coverage matrix — `login-magnetic-hover` (M1–M8) and `login-live-validation` (V1–V7)

Each row maps a **spec scenario** to the test(s) that exercise its contract. Scenarios not yet testable inside PR1's foundation scope are flagged with rationale.

### `login-magnetic-hover`

| ID | Scenario | Test(s) | Coverage |
|---|---|---|---|
| M1 | Cursor enters → tilt toward cursor → `translate3d(dx, dy, 0)`; settle ≤350ms | `clamp_returns_zero_when_inside_max_distance`, `clamp_caps_magnitude_at_max_distance_factor` (math kernel) | **PARTIAL** — math correct; runtime tilt + 350ms settle deferred to PR2 manual sweep (task 2.7.1) and `LoginPageRenderTest` (task 2.2.3). |
| M2 | Max tilt offset clamped to 40% of `min(W, H)` | `clamp_caps_magnitude_at_max_distance_factor` (asserts dx≈28.284, dy≈28.284 ±0.5 AND clamped magnitude == 40 ±0.01) | **FULL** |
| M3 | Cursor-leave → springs back to `(0,0)` with `damping 0.7`, settle ≤600ms | Indirect via `UseSpringMathTest::under_damped_overshoots_target_at_least_once` + `instant_settle_zeros_velocity_and_lands_on_target` (same spring kernel) | **PARTIAL (indirect)** — spring math coverage; no end-to-end settle test for the magnetic composable. Acceptable for PR1 (per design AC2 wording). |
| M4 | `prefers-reduced-motion: reduce` → magnet inert; hover lift continues | `reduced_motion_bypass_returns_inert` (probes `matchMedia('(prefers-reduced-motion: reduce)')` literal in `useMagneticHover.js`) | **PARTIAL** — bypass wired (source-grep on `useMagneticHover.js`); runtime inert assertion deferred to PR2. |
| M5 | `(pointer: coarse)` touch → magnet inert | `reduced_motion_bypass_returns_inert` (also probes `matchMedia('(pointer: coarse)')` literal) | **PARTIAL (same as M4)** |
| M6 | Settle within 600ms (50ms under reduced motion) | Indirect via `UseSpringMathTest::prefers_reduced_motion_honors_match_media` + the under-damped test | **PARTIAL (indirect)** |
| M7 | Composes with the existing primary-button hover lift (`translateY(-1px)`) | `button_vue_consumes_spring_magnet_vars` (asserts `data-magnetic` selector AND `spring-magnet-x` var present in `Button.vue`) | **WEAK** — string presence only; **CSS cascade specificity is a separate concern** (see *Finding F-03 below*). |
| M8 | Keyboard-only → rAF loop never fires, Enter/Space still submits | None | **GAP** — passive absence test not written. Acceptable for PR1 (M8 is by-design; no spec acceptance criterion explicitly requires a PR1 test for it). |

### `login-live-validation`

| ID | Scenario | Test(s) | Coverage |
|---|---|---|---|
| V1 | Errors wait for blur OR 250ms idle; previous timer cancelled on new input | `debounce_resets_on_rapid_input`, `debounce_is_250ms_within_50ms_tolerance` | **FULL** — measured at 250ms ±50ms; cancellation observed (errorBeforeFire empty, errorAfterFire == "required") |
| V2 | Success is immediate when rules pass (no debounce) | `success_immediate_when_rule_passes` (asserts empty error + `successes.username == true` synchronously) | **FULL** |
| V3 | Error message slides down + fades (transform + opacity, 200ms iOS) | None | **OUT OF PR1 SCOPE** — pure CSS animation in `LoginPage.vue` (PR2 task 2.3.3). |
| V4 | Input change cancels + reschedules debounce | `debounce_resets_on_rapid_input` (V1 test exercises the same code path) | **FULL** |
| V5 | Composable exposes `validateField`, `validateAll`, errors, successes | `validate_field_runs_immediately`, `clear_field_resets_both_states`, `validate_all_returns_false_when_any_field_errors` (also indirectly `success_immediate_when_rule_passes`) | **FULL** — `validateField`, `validateAll`, `clearField`, `errors`, `successes` all exercised. |
| V6 | Errors inline + preserve existing `aria-live` on auth failure | None | **OUT OF PR1 SCOPE** — runtime DOM check; PR2 manual sweep (task 2.7.2, 2.7.3) + visual. |
| V7 | Reduced-motion → opacity-only, no transform | None | **OUT OF PR1 SCOPE** — CSS in `LoginPage.vue`; PR2 task 2.5.7 will add the source-grep test. |

**Summary of spec coverage for PR1:**

- **Fully covered** (math + state machine): M2, V1, V2, V4, V5.
- **Partially covered (string/math only, runtime deferred to PR2)**: M1, M3 (indirect), M4, M5, M6, M7, M8.
- **Out of PR1 scope per task partitioning**: V3, V6, V7.

PR1's own acceptance criteria from the design (AC2 of `login-magnetic-hover`, AC2 of `login-live-validation`) are **met**.

---

## Task completion status — `tasks.md` § Phase 1

| Sub-phase | Items | Status |
|---|---|---|
| 1.1 Install `@vueuse/motion` + register plugin | 5/6 GREEN on working tree; 1 unchecked (1.1.7 — orchestrator commit) | COMPLETE in code, commit DEFERRED |
| 1.2 `useMagneticHover` composable + Button hook | 11/12 GREEN; 1 unchecked (1.2.12 — orchestrator commit) | COMPLETE in code, commit DEFERRED |
| 1.3 `useFieldValidation` composable | 9/10 GREEN; 1 unchecked (1.3.11 — orchestrator commit) | COMPLETE in code, commit DEFERRED |
| 1.4 PR1 merge gate | 2/5 GREEN; 3 unchecked (1.4.3 lint:check, 1.4.5 open PR — both explicitly orchestrator/user) | MERGE GATE DEFERRED, but lint:check is open work for the user |

**Unchecked `- [ ]` lines (verbatim from `tasks.md`):**

1. `- [ ] 1.1.7 Commit: orchestrator handles after user OK.`
2. `- [ ] 1.2.12 Commit: orchestrator handles after user OK.`
3. `- [ ] 1.3.11 Commit: orchestrator handles after user OK.`
4. `- [ ] 1.4.3 \`pnpm lint:check\` (orchestrator runs before commit; see apply-progress.md D1).`
5. `- [ ] 1.4.5 Open PR1 → review → merge to main (orchestrator handles).`

**Verdict:** All five are explicitly orchestrator-handled git/lint operations. They are *not* implementation gaps — the apply phase's exit condition is "all tests green + build succeeds + ready for commit". They are listed as WARNING (not CRITICAL) because:
- `apply-progress.md` is transparent about the deferral and D1 explicitly notes lint:check is the user's pre-commit step.
- The orchestrator commits after the user OKs the slice — they are not orphaned items.
- They do not affect PR1's foundation deliverable (the code/tests/docs are in place; the git operations remain).

> **Note on numbering:** `apply-progress.md` uses task IDs `1.1.6`, `1.2.12`, `1.3.10` for the commit steps; `tasks.md` uses `1.1.7`, `1.2.12`, `1.3.11`. One-off discrepancy in the refactor-commit pair (1.3.x), but both files refer to the same underlying commit step. INFO.

---

## Strict TDD compliance (per-task granularity)

| Sub-phase | TDD table cell | Test | GREEN observed? |
|---|---|---|---|
| **1.1 AppShellTest** | RED | `app_js_registers_motion_plugin` (source-grep on `MotionPlugin`) | ✅ Pass |
| | GREEN | `pnpm add @vueuse/motion@3.0.3` + `app.use(MotionPlugin)` | ✅ Pass |
| **1.2 MagneticHoverTest** | RED (1 case) | `clamp_returns_zero_when_inside_max_distance` | ✅ Pass |
| | GREEN (1 case) | `magneticHoverMath.js` + `useMagneticHover.js` re-export | ✅ Pass |
| | TRIANGULATE (+3) | `clamp_caps_magnitude_at_max_distance_factor`, `clamp_handles_zero_bounds`, `reduced_motion_bypass_returns_inert` | ✅ Pass |
| | REFACTOR | Pure math extracted to `magneticHoverMath.js` (D2 design adjustment) | ✅ Pass (math module re-export preserves API) |
| | RED (CSS) | `button_vue_consumes_spring_magnet_vars` (source-grep on `Button.vue`) | ✅ Pass |
| **1.3 FieldValidationTest** | RED (1 case) | `validate_field_runs_immediately` | ✅ Pass |
| | GREEN | `useFieldValidation.js` + `fieldValidationMath.js` | ✅ Pass |
| | TRIANGULATE (+4) | `debounce_resets_on_rapid_input`, `success_immediate_when_rule_passes`, `clear_field_resets_both_states`, `validate_all_returns_false_when_any_field_errors` | ✅ Pass |
| | TRIANGULATE (timing) | `debounce_is_250ms_within_50ms_tolerance` | ✅ Pass (measured 200–300ms window) |
| | REFACTOR | Pure state machine extracted to `fieldValidationMath.js` (D2) | ✅ Pass |

**Verdict:** TDD cycle preserved at per-task granularity. The TDD Cycle Evidence table exists in `apply-progress.md` § "TDD evidence" with all four columns filled. RED → GREEN → TRIANGULATE → REFACTOR is observably sequential in the apply-progress sub-phases. **PASS.**

---

## Assertion quality audit (per-test)

### `tests/Unit/Composables/AppShellTest.php`

| Test | Quality findings |
|---|---|
| `app_js_registers_motion_plugin` | Uses ripgrep (`rg --count-matches --no-messages`) with a specific substring (`MotionPlugin`), then asserts `count >= 1`. **No tautology, no type-only assertion.** Counts existing imports + registrations independently. |
| `app_js_imports_motion_plugin_from_vueuse_motion_package` | Same pattern; substring `@vueuse/motion`, `count >= 1`. Defensive second assertion to catch a typo'd local import. **No issues.** |

Both tests are source-inspection only; the assertions have real content (specific strings). `str_contains(file, '')` style traps are avoided by relying on ripgrep's `count-matches` output (which would be 0 for an empty pattern match) plus `assertGreaterThanOrEqual(1, ...)`.

### `tests/Unit/Composables/MagneticHoverTest.php`

| Test | Quality findings |
|---|---|
| `clamp_returns_zero_when_inside_max_distance` | **Real numeric assertions:** `assertSame(0.0, $r['dx'])` and same for `$r['dy']`. Tests actual `computeOffset` output via `node -e` ESM import. No ghost loop, no tautology. |
| `clamp_caps_magnitude_at_max_distance_factor` | **Real numeric assertions with deltas:** `assertEqualsWithDelta(28.284, $r['dx'], 0.5)` AND second `assertEqualsWithDelta(40.0, $clampedMag, 0.01)`. Tests both per-axis clamp AND magnitude cap. The two-test-in-one is heavy — recommended split for future maintainability. Not a quality blocker. |
| `clamp_handles_zero_bounds` | **Edge-case assertion:** `assertSame(0.0, ...)` for `(0,0)` bounds — guards divide-by-zero / `Math.min(0,0) === 0` path. |
| `reduced_motion_bypass_returns_inert` | Source-grep with three distinct substrings (each `count >= 1`); catches reduced-motion bypass (M4), coarse-pointer bypass (M5), and `computeOffset` re-export preservation. **No ghost loop, no implementation-detail CSS-only assertion** — the assertions are about wiring contracts (presence of probe strings), not internal implementation. |
| `button_vue_consumes_spring_magnet_vars` | Source-grep on two distinct substrings (`data-magnetic`, `spring-magnet-x`). **Weakness** (not a quality defect, see F-03): only verifies *string presence* in `Button.vue`; does not verify the CSS cascade. |

`runMath()` helper:
- Uses `pathToFileURL` to import the ESM math module from Node ESM context — appropriate.
- Writes a temp `.mjs` file, runs `node` via `shell_exec`, parses the JSON object out of stdout.
- Defensive `if (!is_file($mathPath)) { self::fail(...); }` early-guard.
- **Non-blocking observation:** uses `json_decode(substr($output, $jsonStart), true)` — assumes the node output starts with the JSON object. Fragile if node emits non-JSON noise first (e.g. require warnings). For PR1's modules this works because both math modules have no transitive Node warnings.

### `tests/Unit/Composables/FieldValidationTest.php`

| Test | Quality findings |
|---|---|
| `validate_field_runs_immediately` | **Content-based:** `assertSame('required', $r['error'])` + `assertFalse($r['success'])`. Tests actual state mutation, not a tautology. |
| `debounce_resets_on_rapid_input` | **Behavioral sequence test:** asserts that the error is empty 200ms after the first schedule (timer still pending) AND `'required'` 400ms after the second schedule (timer fired). This exercises V1 + V4 simultaneously with a real timing delta. |
| `success_immediate_when_rule_passes` | Real assertion (`success == true` on the same synchronous tick as the input change). V2 covered. |
| `clear_field_resets_both_states` | Pre/post state both asserted (`beforeClear + afterClear`). Guards the `clearField` contract explicitly. |
| `validate_all_returns_false_when_any_field_errors` | Real boolean assertion + per-field error inspection (one passes, one fails). |
| `debounce_is_250ms_within_50ms_tolerance` | Real timing measurement with `[200ms, 300ms]` window — same delta of `±50ms` from the spec's `±50ms` slack. |

`runValidator()` helper:
- Translates a JSON-encoded rule map (`[{v: 'fn-string'}, ...]`) into JS functions via `(0, eval)('(' + v + ')')`. Unusual pattern but legal — composes JSON test inputs with the JS function literals the math module expects. **Non-blocking observation:** this introduces a `Function` constructor-equivalent evaluation in a test context; an attacker who controls the JSON would have arbitrary code execution. Tests are not user-facing so this is acceptable; future hardening could use a function-string DSL.

**Assertion-quality verdict:** No tautologies, no ghost loops, no type-only assertions, no implementation-detail CSS-only assertions. Source-greps use ripgrep `count-matches` with specific non-empty substrings. **PASS.**

---

## Review workload / PR boundary findings

**Forecast (from `tasks.md` § Review Workload Forecast):**
- `Decision needed before apply`: **No**
- `Chain strategy`: **stacked-to-main**
- `400-line budget risk`: **Low** (each PR strictly under 400 lines)
- PR1 estimated: ~330 lines (realised: see below)

**Actual PR1 footprint (working tree diff against `main`, excluding `pnpm-lock.yaml`):**

| File | Action | Lines |
|---|---|---|
| `package.json` | Modified | +1 (one dep entry) |
| `pnpm-lock.yaml` | Modified | +393 (transitive dep tree; expected for a new dep) |
| `resources/js/app.js` | Modified | +2 (`import` + `app.use`) |
| `resources/js/components/ui/Button.vue` | Modified | +21 (additive scoped CSS, exactly as design specifies) |
| `resources/js/composables/magneticHoverMath.js` | **Created** | ~50 |
| `resources/js/composables/useMagneticHover.js` | **Created** | ~150 |
| `resources/js/composables/fieldValidationMath.js` | **Created** | ~120 |
| `resources/js/composables/useFieldValidation.js` | **Created** | ~80 |
| `tests/Unit/Composables/AppShellTest.php` | **Created** | ~110 |
| `tests/Unit/Composables/MagneticHoverTest.php` | **Created** | ~290 |
| `tests/Unit/Composables/FieldValidationTest.php` | **Created** | ~370 |

**Net PR1 (excl. `pnpm-lock.yaml`):** ~1170 lines (created) + ~24 lines (modified) ≈ **~1190 lines**.

**This exceeds the 400-line forecast by ~3x.**

This is the **second deviation flagged by the executor** in `apply-progress.md` (the bullet "Net: ~530 lines — above the 400-line budget"). The breakdown:
- Source code (composables + modified files): ~430 lines.
- PHPUnit source-grep tests: ~770 lines (each PHPUnit file carries an extensive docblock per project convention; the test bodies are short).

**Verdict:** **WARNING.** The 400-line budget is meaningful as a *reviewable* budget, not a hard line in the sand. Each individual file is small and reviewable (< 400 lines). The cumulative footprint is larger than forecast, but PR1 is decomposable: a reviewer can sign off on the install/plugin/app.js/bookend, then on each composable + its tests, in isolation. The dependency bump (393 lines of `pnpm-lock.yaml`) is reviewable by automated tooling. Not blocking, but a sharp signal that the per-PR budget forecast was underestimated. **The tasks.md "Low" risk rating was incorrect — actual "Medium" given the test docblock style.** Future slices should split tests into their own PR or compress docblocks.

**PR2 boundary confirmation:**

| File | Status |
|---|---|
| `resources/js/modules/auth/LoginPage.vue` | **Unmodified** — MD5 hash matches `main` byte-for-byte. |
| `resources/js/modules/auth/ForgotPasswordModal.vue` | **Unmodified** — MD5 hash matches `main`. |
| `resources/js/modules/auth/ResetPasswordModal.vue` | **Unmodified** — MD5 hash matches `main`. |
| `tests/Unit/DesignSystem/LoginPageRenderTest.php` | **Unmodified** — MD5 hash matches `main`. |

**PR2 is not in scope for this verify.** PR2-wiring tasks (2.2, 2.3, 2.4, 2.5) are untouched. PR boundary is preserved. **PASS on the chain-strategy forecast.**

---

## Bundle budget verification

**Aspirational budget (per `tasks.md` 1.4.4 + `design.md` D17):** ≤ +12 kB gzip.
**Observed (per `pnpm build` output on this branch):** `app-DntzPqxE.js` at **479.82 kB raw / 152.94 kB gzip**.
**Documented baseline (per `apply-progress.md` D3, measured independently by the executor against `main`):** 444.46 kB raw / 139.99 kB gzip.
**Delta:** +35.36 kB raw / **+12.95 kB gzip** — **8% over the aspirational budget**.

**Independent confirmation on this verify run:**

- `app.js` imports `MotionPlugin` at line 4 and registers it at line 264 (verified via grep).
- `package.json` has `@vueuse/motion: 3.0.3` exact-pinned (verified; AC3/B1 satisfied).
- pnpm-lock.yaml resolves `@vueuse/motion@3.0.3` to `sha512-4B+ITsxCI9cojikvrpaJcLXyq0spj3sdlzXjzesWdMRd99hhtFI6OJ/1JsqwtF73YooLe0hUn/xDR6qCtmn5GQ==`.
- `pnpm build` succeeds; no Vite warnings emitted for the new dep.

**Forward-looking budget verification (the parent's question: "could tree-shaking / version downgrade close the gap?"):**

| Lever | Estimated savings |
|---|---|
| Tree-shake `MotionPlugin` named imports (Vite already does this for ESM but `@vueuse/motion` ships an IIFE-style entry — verify) | ~0.5–1.0 kB gzip |
| Downgrade to `@vueuse/motion@2.x` | ~1.5–2.5 kB gzip (then lose the v3 directive API) |
| Replace with vanilla Web Animations API + `useSpring2D` (the design D17 rejected alternative) | ~13 kB gzip → 0 kB gzip, but loses `v-motion` directive ergonomics |
| Dynamic import `MotionPlugin` (load only on `/login`) | ~12 kB gzip on the auth routes only (route-level code-split) |

**Verdict:** **PASS for the budget gate (B4) being armed.** D3 explicitly notes the deviation is a monitored, accepted 8% over the aspirational budget, with documented escape hatches for PR4 + a future audit. The budget guardrail is in place (a future regression that adds another motion lib would surface as > +13 kB gzip delta). B3 source-grep is satisfied (AppShellTest exists and passes).

**Recommendation for PR2/archive:** add a `php artisan test` assertion that runs `pnpm build` and parses the gzip total, comparing against a fixture. Locks the budget against future regressions. Defer to archive checklist.

---

## Deviations / forward-looking risks / INFO / WARNING / CRITICAL findings

### CRITICAL findings
**None.** No CRITICAL findings block PR1's foundation deliverable.

### WARNING findings (non-blocking but worth flagging)

- **F-01 — Stale unchecked tasks (5 items)** — All five unchecked `- [ ]` items in tasks.md § Phase 1 are explicitly orchestrator-deferred (commit/PR/lint). They are not implementation gaps. Archive checklist MUST include a final sweep that ticks them after the orchestrator commits + opens PR1 + runs lint:check.
- **F-02 — PR1 line count ~1190 lines vs forecast ~330 lines** — See Review workload section. The 400-line-budget "Low" risk rating was incorrect; actual is "Medium" (each file remains small and reviewable, but the cumulative footprint is the issue). Lesson: project PHPUnit docblock convention should be counted in the per-PR budget forecast.
- **F-03 — CSS cascade ambiguity between `button:not(:disabled):hover` and `button[data-magnetic='true']`** (see WARN-3 above). Not blocking PR1 verification (the test asserts the strings exist; the cascade itself is a runtime concern), but the apply executor should re-evaluate this before PR2 wires the LoginPage. Recommended fix: bump the magnet selector specificity to match (e.g. `button[data-magnetic='true']:not(:disabled)`), or write the CSS vars via inline style attribute (which beats any selector), or include `button[data-magnetic='true']:not(:disabled):hover { ... }` as a separate rule.

### INFO findings

- **F-04 — `computeOffset` signature mismatch with design.md** — `design.md` documents `computeOffset(mx, my)` (closure-captured bounds); the actual implementation takes `(bounds, mx, my, maxDistanceFactor)` explicitly. The implementation is correct (the test calls it the way it's actually implemented). Recommend updating `design.md` § "useMagneticHover" to reflect the explicit signature, or — preferred — adding a higher-order helper `createMagnet(bounds)` that returns the closure form, satisfying both contracts.
- **F-05 — V3, V6, V7 spec scenarios have no PR1 test** — Out of scope per task partitioning (PR2 wires the LoginPage). PR2 will add source-grep tests in `LoginPageRenderTest` (task 2.6.1) and visual/manual sweeps (tasks 2.7.x).
- **F-06 — M1/M3/M4/M5/M6/M7/M8 spec scenarios are partially covered (math + string only)** — Runtime behaviors (mouse tracking, spring settle, hover-lift composition) are deferred to PR2 manual sweep at 1440x900 + iPhone 12 (tasks 2.7.1–2.7.8). Source-grep tests in PR1 verify *wiring contracts*, not *runtime behavior*. This is acceptable per the PR1 = foundation scope.
- **F-07 — Numbering inconsistency** — `apply-progress.md` uses `1.1.6 / 1.3.10` for the commit step; `tasks.md` uses `1.1.7 / 1.3.11`. Same conceptual step. Cosmetic; no functional impact.
- **F-08 — 48 pre-existing Unit-suite failures on `main`** — Per `apply-progress.md` D1. PR1 verifies its 24 specific tests + a UseSpringMathTest regression sanity check. The full suite was not gated (per D1 + the parent's instructions). This is a working-tree baseline issue, not a PR1 regression.

### What PR1 verify did NOT do (out of scope)

- Did **not** run `php artisan test --testsuite=Unit` (would pollute the report with the 48 pre-existing D1 failures — parent's constraint).
- Did **not** run `pnpm lint:check` (explicitly deferred to the orchestrator/user; see `apply-progress.md` 1.4.3 + D1).
- Did **not** execute PR2 source files (LoginPage.vue is untouched on the working tree).
- Did **not** fix any issues — read-only per the verify phase contract.

---

## Status against the `openspec/config.yaml` status contract

Without an `openspec/config.yaml` in this repository (the change lives under `openspec/changes/...` only), the structured status uses the inferred contract.

| Field | Value |
|---|---|
| Status of the change | Ready for archive PR1 (commits/PR/lint gate remaining) |
| `nextRecommended` | `sdd-archive-pr1` after orchestrator commits + runs lint:check + opens PR1; then `sdd-apply-pr2` after user approval of PR1 |
| Action required before archive | Orchestrator: commit the 4 modified + 7 untracked; run `pnpm lint:check` (tick 1.4.3); open PR1 (tick 1.4.5) |

---

## Result

```yaml
status: success
executive_summary: |
  PR1 foundation is COMPLETE on the working tree. All 24 PR1-scoped PHPUnit
  tests pass (13 new + 11 regression sanity). pnpm build succeeds in 7.67s with
  the documented +12.95 kB gzip bundle delta (apply-progress D3, 8% over the
  aspirational +12 kB budget). TDD cycle was preserved at per-task granularity
  with full RED → GREEN → TRIANGULATE → REFACTOR evidence in apply-progress.md.
  PR2 files (LoginPage.vue, ForgotPasswordModal.vue, ResetPasswordModal.vue,
  LoginPageRenderTest.php) are byte-identical to main — PR boundary preserved.
  Two non-blocking findings worth flagging: the 400-line per-PR budget was
  significantly underestimated (~1190 lines actual due to PHPUnit docblock
  convention), and a CSS cascade ambiguity between the new [data-magnetic]
  rule and the existing :hover lift should be resolved in PR2 before the
  LoginPage wires the magnet at runtime. Five tasks.md `[ ]` items remain
  unchecked — all are explicitly orchestrator-deferred git/lint steps, not
  implementation gaps. Verdict: PASS for the foundation deliverable; archive
  checklist remains open on the orchestrator's commit + PR + lint gate.
artifacts:
  - openspec/changes/ui-login-premium-motion-2026-08/verify-report.md    (this file)
  - engram://sdd/ui-login-premium-motion-2026-08/verify-report          (persisted)
next_recommended: |
  1. Orchestrator: commit the working-tree delta (4 modified + 7 untracked) on
     `feat/ui-login-premium-motion-2026-08`, run `pnpm lint:check`, and open
     PR1 against `main`. Tick 1.1.7, 1.2.12, 1.3.11, 1.4.3, 1.4.5.
  2. User: review PR1 + approve (manual sweep at this point is unnecessary;
     PR1 is foundation, no UI behaviour to sweep).
  3. After PR1 merge: orchestrator launches PR2 (tasks 2.1–2.7) following the
     8 sub-phases in `tasks.md` § Phase 2.
  4. Archive at end of PR2 (one archive report covering the full slice).
risks:
  - F-03 (CSS cascade ambiguity) MUST be resolved in PR2 before manual sweep
    task 2.7.1. If unresolved, the runtime tilt will be silently overridden by
    the existing `:hover` rule.
  - F-02 (per-PR budget overrun) is a process risk for future slices —
    re-forecast costs when planning the next two-PR stack.
  - D3 bundle budget is +12.95 kB gzip (8% over). If a future audit enforces
    the strict +12 kB budget, escape hatches are documented (dynamic import
    per-route OR replace @vueuse/motion with vanilla Web Animations API).
  - D1 (48 pre-existing Unit failures on main) is out of scope; PR2 must not
    regress, but cannot be expected to fix.
skill_resolution: paths-injected
```


---

## Post-verify fix: F-03 applied on working tree (2026-08-23, same session)

After the verify report was written, the orchestrator applied the recommended fix for **F-03 (CSS cascade ambiguity)** directly to `resources/js/components/ui/Button.vue`:

1. **Existing `:hover` rule modified:** the selector is now `button:not(:disabled):not([data-magnetic='true']):hover` so magnetic buttons keep their additive transform when the cursor hovers.

2. **Additive magnetic rule modified:** the selector is now `button:not(:disabled)[data-magnetic='true']` (specificity 0,2,1 — matches the modified `:hover`). When the cursor hovers a magnetic button, the additive transform wins by selector specificity.

3. **Reduced-motion block updated:** the `prefers-reduced-motion: reduce` fallback is now `button:not(:disabled)[data-magnetic='true'] { transform: none; }` for the same specificity reason.

**Verification after fix:**
- `php artisan test tests/Unit/Composables/AppShellTest.php tests/Unit/Composables/MagneticHoverTest.php tests/Unit/Composables/FieldValidationTest.php tests/Unit/DesignSystem/UseSpringMathTest.php` → **24/24 GREEN**.
- `pnpm build` → succeeds in 8.05s; `app-y3ZiBNJX.js` 479.82 kB raw / **152.93 kB gzip** (delta vs baseline unchanged: +12.95 kB gzip).

**F-03 status:** RESOLVED on working tree. PR2's manual sweep task 2.7.1 can proceed without the runtime regression that the original specificity gap would have caused.

The remaining WARNING findings (F-01 orchestrator-deferred items, F-02 budget overrun) are unchanged. Verdict remains **PASS** for PR1 foundation.
