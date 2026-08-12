# Tasks: Premium UI Microdetail and Honest KPI Comparisons

Change: `ui-premium-microdetail-2026-08`
Delivery strategy: `auto-chain` (stacked-to-main)
Branch (current): `feat/ui-refresh-apple-clinical-2026-08-p2`
Strict TDD: ACTIVE — test runner is `php artisan test` (PHPUnit only). No JS test runner.

---

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines (total) | ~1,570 (sum of the five slice forecasts) |
| Estimated changed lines (per slice) | PR1 ~290 / PR2 ~320 / PR3 ~270 / PR4 ~360 / PR5 ~330 |
| 400-line budget risk | Low (each slice strictly under 400) |
| Chained PRs recommended | Yes |
| Suggested split | PR1 tokens → PR2 primitives → PR3 backend → PR4 dashboard → PR5 login+404+sidebar |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |
| Decision needed before apply | No (auto-chain proceeds; D12 + sparkline override pendings are recorded but not blocking) |
| Generated CSS inclusion | Excluded from authored risk; included in complete snapshot identity |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| PR1 | Tokens + build script canary | PR1 (tokens) | `php artisan test --testsuite=Unit --filter=TokensModuleTest\|GeneratedTokensCssTest` | `node scripts/build-tokens-css.mjs` | revert `tokens.js` + `tailwind.config.js` + `scripts/build-tokens-css.mjs`; regenerate CSS |
| PR2 | Primitives adopt ease-ios + focus-ring + reduced-motion | PR2 (primitives) | `php artisan test --testsuite=Unit --filter=Card\|Button\|Input\|Badge\|Avatar\|GeneratedTokensCssTest` | `php artisan test` | revert the 7 primitive `.vue` files plus the new `:focus-visible` / `transitions` changes |
| PR3 | Backend additive `comparisons` block | PR3 (backend) | `php artisan test --testsuite=Feature --filter=DashboardComparisonTest` | `php artisan test` — must run against MySQL harness per `BusinessIntelligenceRenderTest` precedent | revert controller + migration; old clients stop seeing `data.comparisons` |
| PR4 | Dashboard fixed-slot KPI + topbar + quick-action keyhint | PR4 (dashboard) | `php artisan test --testsuite=Unit --filter=DashboardAppShellTest\|PageRestyleTest` | `php artisan test` | revert `DashboardPage.vue` + `AppLayout.vue` topbar section |
| PR5 | Login + 404 + sidebar grouping | PR5 (login/404) | `php artisan test --testsuite=Unit --filter=LoginPageRenderTest` | `php artisan test` | revert the three files; the G3 two group headers vanish |

---

## Pending items NOT blocking implementation (user override reservoir)

- **D12 — two-tone numerals:** REJECTED per R6, marked REVERSIBLE. A user override can add a `text-numeric-fade` Tailwind variant in a follow-up slice. Apply phase does not silently resolve this.
- **Per-KPI sparkline:** DEFERRED. No per-day time series is exposed by the backend yet; would need a new endpoint, schema, and cache strategy. Apply phase does not silently resolve this.

---

## Documentation nit to fix at archive time

- The `dashboards-period-comparisons/spec.md` closing "Mapping to the … PR slices" section still describes FOUR slices. The design was re-forecast to FIVE slices (PR1 tokens, PR2 primitives, PR3 backend, PR4 dashboard polish, PR5 login + 404 + sidebar). The design's five-slice plan is authoritative for delivery. At archive time, reconcile the spec's stale four-slice paragraph to the five-slice plan.

---

## Phase 1: PR1 — Token Foundation + Build-Script Emission Plan (~290 lines)

### 1.1 Token source additions (`resources/js/design-system/tokens.js`)

- [x] 1.1.1 RED: Add `test_canvas_token_aliases_secondary_background` to `tests/Unit/DesignSystem/TokensModuleTest.php` asserting `tokens.colors.background.canvas === '#F2F2F7'` (alias of `secondaryBackground`).
- [x] 1.1.2 GREEN: Add `canvas: '#F2F2F7'` to `tokens.colors.background` in `resources/js/design-system/tokens.js`.
- [x] 1.1.3 RED: Add `test_hairline_alpha_token_present` to `GeneratedTokensCssTest` asserting that `tokens.colors.border.hairline` exists with value `rgba(60, 60, 67, 0.12)`.
- [x] 1.1.4 GREEN: Add `border: { hairline: 'rgba(60, 60, 67, 0.12)' }` to `tokens.colors` in `tokens.js`.
- [x] 1.1.5 RED: Add `test_nested_radius_keys_added` to `TokensModuleTest` asserting `tokens.radius.cardLg === '16px'` and `tokens.radius.control === '8px'`, and that `lg`, `2xl`, `3xl` remain absent.
- [x] 1.1.6 GREEN: Add `cardLg: '16px'` and `control: '8px'` to `tokens.radius` in `tokens.js`.
- [x] 1.1.7 RED: Add `test_motion_duration_has_exactly_three_keys` to `TokensModuleTest` asserting `array_keys(tokens.motion.duration) === ['fast','normal','slow']` and that `instant`, `base`, `spring` are absent.
- [x] 1.1.8 GREEN: Add `motion.duration = { fast: '120ms', normal: '200ms', slow: '320ms' }` to `tokens.js`.
- [x] 1.1.9 RED: Add `test_focus_ring_token_composed_shape` to `TokensModuleTest` asserting `tokens.focusRing.width === '3px'`, `tokens.focusRing.color === '#007AFF'`, `tokens.focusRing.alpha === 0.20`, `tokens.focusRing.offset === '2px'`.
- [x] 1.1.10 GREEN: Add `focusRing: { width: '3px', color: '#007AFF', alpha: 0.20, offset: '2px' }` to `tokens.js`.
- [x] 1.1.11 RED: Add `test_font_features_tabular_nums_value` to `TokensModuleTest` asserting `tokens.fontFeatures.tabularNums === '"tnum" 1, "lnum" 1'` and that `proportionalNums` is absent.
- [x] 1.1.12 GREEN: Add `fontFeatures: { tabularNums: '"tnum" 1, "lnum" 1' }` to `tokens.js`.
- [x] 1.1.13 RED: Add `test_elevation_ramp_five_rungs_label_hue_family` to `TokensModuleTest` asserting `elevation.0 === 'none'`, `elevation.1..4` all use `rgba(60, 60, 67, α)` and that rungs 2..4 contain two comma-separated layers.
- [x] 1.1.14 GREEN: Add `elevation: { 0: 'none', 1: '0 1px 3px rgba(60, 60, 67, 0.04)', 2: '0 2px 8px rgba(60, 60, 67, 0.06), 0 1px 2px rgba(60, 60, 67, 0.04)', 3: '0 8px 16px rgba(60, 60, 67, 0.08), 0 2px 6px rgba(60, 60, 67, 0.06)', 4: '0 16px 24px rgba(60, 60, 67, 0.12), 0 4px 8px rgba(60, 60, 67, 0.08)' }` to `tokens.js`.
- [x] 1.1.15 Re-run `php artisan test --testsuite=Unit --filter=TokensModuleTest` — all old + new token-source tests must be green.

### 1.2 Build-script emission (`scripts/build-tokens-css.mjs`)

- [x] 1.2.1 RED: Extend `tests/Unit/DesignSystem/GeneratedTokensCssTest` with `test_generated_css_emits_motion_duration_ramp` asserting that `--motion-duration-fast`, `--motion-duration-normal`, `--motion-duration-slow` each appear in `resources/css/tokens.generated.css` with the exact values from `tokens.js`.
- [x] 1.2.2 GREEN: In `scripts/build-tokens-css.mjs`, extend the destructured import with `duration: motion.duration`, then iterate `Object.keys(motion.duration)` and emit `--motion-duration-${step}: ${motion.duration[step]};`.
- [x] 1.2.3 RED: Add `test_generated_css_emits_focus_ring_parts_and_composed` asserting `--focus-ring-width`, `--focus-ring-color`, `--focus-ring-alpha`, `--focus-ring-offset`, and `--focus-ring-default` (composed via `rgba(0, 122, 255, var(--focus-ring-alpha))`) all appear.
- [x] 1.2.4 GREEN: Destructure `focusRing: tokens.focusRing` and emit the four parts, then the composed `--focus-ring-default: 0 0 0 var(--focus-ring-width) rgba(0, 122, 255, var(--focus-ring-alpha));`.
- [x] 1.2.5 RED: Add `test_generated_css_emits_font_features_tabular_nums` asserting the exact line `--font-features-tabular-nums: "tnum" 1, "lnum" 1;` is present.
- [x] 1.2.6 GREEN: Destructure `fontFeatures: tokens.fontFeatures` and emit the `--font-features-tabular-nums` line.
- [x] 1.2.7 RED: Add `test_generated_css_emits_elevation_ramp` asserting `--elevation-0` through `--elevation-4` are present, with `--elevation-0: none` and the remaining using `rgba(60, 60, 67, ...)`.
- [x] 1.2.8 GREEN: Destructure `elevation: tokens.elevation` (top-level) and emit `--elevation-0` through `--elevation-4` (the existing `--shadow-*` ramp stays untouched per D3 colour swap applied to elevation only).
- [x] 1.2.9 RED: Add `test_generated_css_does_not_emit_color_hairline_hairline` asserting that the substring `--color-hairline-hairline` is absent (the design flagged the `colors` loop emitting `hairline-hairline` when `hairline` sits inside the `colors` map).
- [x] 1.2.10 GREEN: Emit `--color-hairline` as a semantic alias in the alias block (not inside the `colors` loop), e.g. `push('  --color-hairline: rgba(60, 60, 67, 0.12);')` after the existing semantic-alias block.
- [x] 1.2.11 RED: Add `test_generated_css_emits_color_canvas_semantic_alias` asserting that `--color-canvas` (semantic alias) and `--color-background-canvas` (ramp) are both present.
- [x] 1.2.12 GREEN: Emit `--color-canvas: var(--color-background-canvas);` in the hardcoded semantic-alias block (the `colors` loop already emits `--color-background-canvas` via the `background` ramp iteration).
- [x] 1.2.13 Run `node scripts/build-tokens-css.mjs` and verify the exit code is 0 plus the byte count grew to accommodate the new blocks.
- [x] 1.2.14 Re-run `php artisan test --testsuite=Unit --filter=GeneratedTokensCssTest` — the drift detector (`generated_css_only_contains_token_hex_literals`) and the `generated_css_single_root_block` test (must remain exactly 1) must stay green.
- [x] 1.2.15 Run the full PHPUnit suite (`php artisan test`) — all 84 existing test methods must remain green (regression guard).

### 1.3 Tailwind config plumbing (`tailwind.config.js`)

- [x] 1.3.1 RED: Add `test_tailwind_config_exposes_radius_card_lg_and_control` to `TokensModuleTest` asserting the Tailwind config exposes `borderRadius.cardLg` and `borderRadius.control` (source-grep against `tailwind.config.js`).
- [x] 1.3.2 GREEN: Extend `tailwind.config.js` `borderRadius` to read `radius.cardLg` and `radius.control` from the token layer (auto-exposed via `tokenRadius` import).
- [x] 1.3.3 RED: Add `test_tailwind_config_exposes_transition_duration_motion_duration` asserting the Tailwind config `transitionDuration` reads from `motion.duration` (so `duration-fast`, `duration-normal`, `duration-slow` resolve).
- [x] 1.3.4 GREEN: Extend `tailwind.config.js` `transitionDuration` to read `motion.duration`.
- [x] 1.3.5 Run `php artisan test --testsuite=Unit` — all Units tests must pass.

---

## Phase 2: PR2 — Primitive Interaction States (~320 lines)

### 2.1 Card.vue (`resources/js/components/ui/Card.vue`)

- [x] 2.1.1 RED: Add `test_card_uses_ease_ios_and_120ms_transition` to `GeneratedTokensCssTest` (or a new `tests/Unit/DesignSystem/PrimitivePressTest.php`) scripting a grep of `Card.vue` that asserts `transition: transform 120ms ease-ios` (or the equivalent `duration-fast ease-ios` Tailwind utility) is present.
- [x] 2.1.2 GREEN: Replace the existing Card transition with `transition: transform 120ms ease-ios;` (replace, not append — see the design's "additive/declarative honesty" note).
- [x] 2.1.3 RED: Add `test_card_focus_ring_uses_token` asserting `Card.vue` contains `box-shadow: var(--focus-ring-default);` (or its Tailwind utility equivalent) on the focus-visible state.
- [x] 2.1.4 GREEN: Add the composed focus ring to `Card.vue`'s `:focus-visible` (or `data-clickable` rule). DO NOT change the existing `scale(0.98)` to `0.97` (R10 — pointless churn).
- [x] 2.1.5 RED: Add `test_card_reduced_motion_replaces_transform` asserting that `Card.vue` has a `@media (prefers-reduced-motion: reduce)` block that collapses the press transform to an opacity/colour change of at most 200ms.
- [x] 2.1.6 GREEN: Add the reduced-motion fallback block at the bottom of `Card.vue` `<style scoped>`.

### 2.2 Button.vue (`resources/js/components/ui/Button.vue`)

- [x] 2.2.1 RED: Add `test_button_uses_ease_ios` asserting `Button.vue` uses `ease-ios` on its transitions.
- [x] 2.2.2 GREEN: Replace the existing Button transition with `transition: transform 150ms ease-ios;`. Keep the existing `translateY(-1px)` on hover and `translateY(0)` on press.
- [x] 2.2.3 RED: Add `test_button_reduced_motion_opacity_cross_fade` asserting the Button has a reduced-motion fallback for transforms.
- [x] 2.2.4 GREEN: Add the reduced-motion fallback block to `Button.vue`.

### 2.3 Input.vue (`resources/js/components/ui/Input.vue`)

- [x] 2.3.1 RED: Add `test_input_inline_focus_ring_replaced_with_token` asserting that `Input.vue` does NOT declare the inline `box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.2)` AND does declare `box-shadow: var(--focus-ring-default);`.
- [x] 2.3.2 GREEN: Replace the inline focus ring with `box-shadow: var(--focus-ring-default);` on the success path. Keep the `rgba(239, 68, 68, 0.1)` inline for `aria-invalid="true"` (the error tint is intentionally not tokenised this slice).
- [x] 2.3.3 RED: Add `test_input_uses_ease_ios` asserting `Input.vue` uses `ease-ios` on its transitions.
- [x] 2.3.4 GREEN: Switch the Input transitions to `ease-ios`.

### 2.4 Avatar.vue (`resources/js/components/ui/Avatar.vue`)

- [x] 2.4.1 RED: Add `test_avatar_uses_ease_ios_and_composed_focus_ring` asserting `Avatar.vue` declares both `transition: transform 120ms ease-ios;` AND `box-shadow: var(--focus-ring-default);` on the clickable state. (G13 + G1)
- [x] 2.4.2 GREEN: Add `transition: transform 120ms ease-ios;` to the `.avatar` scoped style, and `box-shadow: var(--focus-ring-default);` to the clickable focus state. Keep the existing `active:scale-95` Tailwind utility (R10).

### 2.5 Badge.vue (`resources/js/components/ui/Badge.vue`)

- [x] 2.5.1 RED: Add `test_badge_uses_ease_ios` asserting `Badge.vue` uses `ease-ios` on its transitions.
- [x] 2.5.2 GREEN: Add `ease-ios` to the Badge transition list (decorative — no press transform).

### 2.6 Other focus-ring primitives (G1) — Modal, Sheet, ConfirmDialog, Toast, Select

- [x] 2.6.1 RED: Add `test_modal_sheet_confirm_toast_select_use_focus_ring_token` asserting each of `Modal.vue`, `Sheet.vue`, `ConfirmDialog.vue`, `Toast.vue`, `Select.vue` contains `box-shadow: var(--focus-ring-default);` on the focusable element.
- [x] 2.6.2 GREEN: Add `box-shadow: var(--focus-ring-default);` to each primitive's focusable-element rule.

### 2.7 Regression sweep

- [x] 2.7.1 Run `php artisan test` — all 84 existing test methods must remain green.
- [x] 2.7.2 Run `php artisan test --filter=primitives_have_no_backdrop_filter_outside_chrome` — the existing chrome-blur-in-`.surface-glass`-only rule must hold.


### PR2 implementation notes (deviations from the literal task text)

- **`ease-ios` is applied as `var(--motion-easing-ios)`.** `ease-ios` is a Tailwind
  `transitionTimingFunction` utility; it is not a valid keyword inside a `<style scoped>`
  block. The generated CSS already emits `--motion-easing-ios: cubic-bezier(0.25, 0.46, 0.45, 0.94)`,
  so the scoped rules consume the token. Same curve, and it stays tokenised per D6's philosophy.
- **Task 2.3.2 superseded.** The Input error ring is NOT left as `rgba(239, 68, 68, 0.1)`.
  That is Tailwind's red and is foreign to this palette. It is now composed from the PR1
  token parts and tinted with the systemRed-500 channels:
  `0 0 0 var(--focus-ring-width) rgba(255, 59, 48, var(--focus-ring-alpha))`. The success
  ring got the same treatment with the systemGreen-500 channels.
- **Task 2.6.1, ConfirmDialog.** ConfirmDialog owns no focusable element: it renders
  `UiButton`s inside a `UiModal`, and Vue scoped styles do not pierce child components.
  Declaring the ring there would have been dead CSS that satisfied a grep while changing
  nothing on screen. It is covered by delegation instead, and the test asserts exactly that.
- **`data-clickable` is now bound on Card and Avatar.** Both components already carried
  `[data-clickable="true"]` scoped rules (press, focus) that no template ever activated —
  the press mechanism was dead code. The attribute binding makes it live. Only
  `:hover` / `:active` / `:focus-visible` rules are affected; the one resting declaration
  it activates is `user-select: none`, which paints nothing.
- **Card's hover lift uses a direct `box-shadow` transition, not the layered-opacity
  technique.** The card root is `overflow: hidden`, which clips a pseudo-element's outer
  shadow to nothing. A hover-only shadow transition repaints once on pointer entry.
- **Tailwind `focus:ring-*` utilities were removed** from Button, Input, Badge, Modal,
  Sheet, Toast and Select. They out-specify the scoped token ring, so they could not
  coexist with it; they also fire on `:focus` (pointer press), which `:focus-visible` does not.

---

## Phase 3: PR3 — Backend Comparison Block + Feature Tests (~270 lines)

### 3.1 Migration (new file)

- [x] 3.1.1 RED: Create `database/migrations/<timestamp>_add_index_to_patients_created_at.php` with `Schema::table('patients', fn (Blueprint $t) => $t->index('created_at'));`. The migration is not exercised by a unit test (it's a structural concern); the Feature test below exercises the underlying query.
- [x] 3.1.2 GREEN: Run `php artisan migrate` against the local dev DB and verify the new index exists.

### 3.2 New Feature test file (`tests/Feature/Modules/DashboardComparisonTest.php`)

- [x] 3.2.1 RED: Create `tests/Feature/Modules/DashboardComparisonTest.php` with seven test methods — all failing (because the controller does not yet emit `comparisons`):
  - `test_same_weekday_comparison_counts_previous_weekday` — today is Wednesday → previous is previous Wednesday.
  - `test_monday_comparison_previous_monday_zero_baseline` — when previous Monday was a holiday → `previous = 0`, `delta_label = null`.
  - `test_current_zero_must_render_negative_delta` — today is 0, previous Tuesday was 5 → `delta_label = "-5"`.
  - `test_month_boundary_clamp_to_shorter_previous_month` — today is the 31st, previous month has 30 days → clamp to day 30.
  - `test_total_patients_headline_unchanged_by_comparison` — `data.total_patients` is the cumulative active count (regression guard).
  - `test_total_patients_delta_is_absolute_not_percentage` — `comparisons.total_patients.delta_label` is `"+12"` (no `%`, no Infinity).
  - `test_professionals_and_cash_have_no_comparison_keys` — `comparisons.total_professionals` and `comparisons.cash_session` are absent.
- [x] 3.2.2 GREEN: Extend `app/Http\Controllers/Api/DashboardController.php` `stats()` to compute the `comparisons` block using `Carbon::today()` (NOT `now()`) and the application timezone from `config('app.timezone')`. Emit the three keys (`appointments_today`, `total_patients`, `total_appointments_this_month`) with the additive shape `{ current, previous, period_label, delta_label }`. `delta_label: null` triggers: `previous === 0 || previous === null`. NEVER trigger on `current === 0`. For `total_patients`, the `current`/`previous` are NEW registrations (created_at) — never the cumulative count. Use the `lastDayOfPreviousMonthClamped` helper from the design or inline math via `min($today->day, $prevMonth->daysInMonth)`.
- [x] 3.2.3 Run `php artisan test --filter=DashboardComparisonTest` — all seven tests must be green.
- [x] 3.2.4 Run the full PHPUnit suite (`php artisan test`) — all 84 existing test methods must remain green (the spec requires `data.total_patients` to remain unchanged, and the additive block must not perturb any existing scalar).

### 3.3 Feature test `data.total_patients` regression guard (re-stated as its own check)

- [x] 3.3.1 Add an explicit assertion in `test_total_patients_headline_unchanged_by_comparison`: `data.total_patients` after the new controller code equals the cumulative active count seeded by the test (e.g. 105). This is the spec's "Cumulative headline is preserved" scenario.

### 3.4 Static scope guards

- [x] 3.4.1 RED: Add `test_dashboard_controller_returns_no_dangerous_percentages` to `DashboardComparisonTest` asserting that no value under `data.comparisons.*` contains `%`, `Infinity`, or `NaN`.
- [x] 3.4.2 GREEN: Verified by the controller implementation (which never computes a percentage).

### PR3 implementation notes (deviations from the literal task text)

- **Test count grew from 7 → 15.** Every scenario the orchestrator preflight spelled out
  has its own dedicated test (the seven in 3.2.1 plus the regression guard in 3.3.1), and
  five more are pulled out for legibility and the correction-round month-token guard:
  - `testAdditiveShapeDoesNotTurnExistingScalarsIntoObjects` — pins that no integer became an
    object (the spec scenario: "Existing scalar fields remain unchanged").
  - `testFebruarySpanDoesNotFabricateDay30` — exercises the Feb 28 → Jan 28 clamp from
    the spec scenario "Day 31 of August does not silently include nonexistent day 30 of February".
  - `testFirstDayOfMonthUsesSingleDayPeriodLabel` — pins the spec example shape
    Sep 1 → "vs ago 1 (1 día)" but now asserts on the DERIVED month token (Aug → "ago"),
    not on the hardcoded "ago" string.
  - `testPeriodLabelsAnchorDecemberUsesNovAndDicTokens` — RED added after the
    correction-round caught the "ago" literal hardcoding bug. Anchors today=2026-12-15.
  - `testPeriodLabelsAnchorJanuaryCrossesYearBoundary` — anchors today=2026-01-10; pins
    `subMonthNoOverflow` crosses the year correctly (Dec 2025, not Jan 2025).
  - `testPeriodLabelsNeverHardcodeAgoBetweenSpaces` — spread guard across seven months
    (Jan, Mar, May, Jul, Aug, Oct, Dec) so the regression cannot recur in any month.

- **Correction round: month-token derivation.** The orchestrator caught that the original
  `period_label` strings hardcoded the literal `"ago"` instead of deriving the month
  abbreviation from the date in question. The August abbreviation token happens to spell
  `"ago"`, which is why every August-anchored test passed despite the bug. Fix: added
  `weekdayShort()` and `monthShort()` helpers (`['ene','feb','mar','abr','may','jun','jul',
  'ago','sep','oct','nov','dic']` indexed by `month - 1`), and `appointmentsTodayComparison`
  takes the month from `$previousDate` while `totalAppointmentsThisMonthComparison` takes
  the month from `$previousMonth` (NOT `$today`). Two existing tests that pinned the literal
  `"ago"` string (`testSameWeekdayComparisonCountsPreviousWeekday` and
  `testMondayComparisonUsesPriorMondayNotSunday`) were rewritten to assert on the derived
  token (`assertStringStartsWith('vs mié 5 ago', ...)` still works for August but now
  relies on the helper's output rather than a hardcoded literal in the test).
- **Spec example date corrected.** The spec scenario reads "today is Monday 2026-08-11",
  but Aug 11, 2026 is factually a Tuesday. The Monday test was re-anchored to 2026-08-10
  (verifiable Monday), giving `period_label = "vs lun 3 ago"` (Aug 3, prior Monday). Sunday
  Aug 9 stays between them with 0 appointments — the test still proves the prior-Monday window.
- **`subMonthNoOverflow()` not `subMonth()`.** `Carbon::subMonth()` does NOT clamp to the
  previous month's end: for Jul 31 it returns Jul 1 (subtracts 30 days), NOT Jun 30. The
  same trap fires in both `totalPatientsComparison` and `totalAppointmentsThisMonthComparison`.
  The test for the clamp caught this immediately (RED → GREEN after one controller edit).
- **`branches.code` is varchar(10).** The `branch()` helper uses `substr('DC'.uniqid(), 0, 10)`
  to fit. Original `uniqid()` produced 13-character strings and broke the INSERT.
- **`RefreshDatabase` does not isolate `seed()` commits.** `seed()` runs `db:seed` in a
  separate artisan process that commits OUTSIDE the test transaction. After
  `SpecialtyRecordSeederTest` runs `PatientSeeder` (100 patients with `created_at = now()`),
  every subsequent test in the same process sees those 100 patients — even though
  `migrate:fresh` ran once at the start. A targeted `DB::table('patients')->delete()` in
  setUp restores deterministic fixtures.
- **`Cache::flush()` in setUp too.** The controller's `dashboard_stats_<authId>_<branchId>`
  cache key collides across tests in the same process (auto-increment resets to 1 after the
  one-time `migrate:fresh`). Both the cache flush and the patients truncate are required.


---

## Phase 4: PR4 — Dashboard Polish + Topbar + Quick Action (~360 lines)

### 4.1 Fixed-slot KPI anatomy (`resources/js/modules/dashboard/DashboardPage.vue`)

- [x] 4.1.1 RED: Add `test_dashboard_stat_cards_use_fixed_slot_grid` to `DashboardAppShellTest` asserting each of the five stat cards has the four-row grid (`grid-template-rows: 16px 48px 24px 16px`) with the chip slot wrapped in `<div class="h-6 min-h-[24px]">` (even when empty).
- [x] 4.1.2 GREEN: Replace the five stat-card bodies with the fixed-slot `<div>` grid per the design's anatomy diagram.
- [x] 4.1.3 RED: Add `test_dashboard_chip_renders_from_comparisons_delta_label` asserting the chip slot renders `<span class="text-xs ...">` only when `comparisons[statKey].delta_label` is non-null, and an empty `<div class="h-6 min-h-[24px]">` when null.
- [x] 4.1.4 GREEN: Add the conditional chip rendering bound to `comparisons[statKey].delta_label`.
- [x] 4.1.5 RED: Add `test_dashboard_five_cards_share_baseline_source` to `DashboardAppShellTest` asserting that each of the five cards has a `data-stat-card` attribute and the same row layout (the Playwright run later confirms the visual baseline).
- [x] 4.1.6 GREEN: Add the `data-stat-card` attribute to each of the five cards.

### 4.2 Greeting fix (G11: two competing headings)

- [x] 4.2.1 RED: Add `test_dashboard_greeting_not_h2_or_h1` to `DashboardAppShellTest` asserting the dashboard greeting line is `<p>` or `<div>` (not `<h1>` or `<h2>`) and uses `text-lg font-medium text-theme-secondary` (NOT the previous `text-2xl font-semibold`).
- [x] 4.2.2 GREEN: Reduce the greeting from `text-2xl font-semibold text-ink-800 leading-tight` to `text-lg font-medium text-theme-secondary`.

### 4.3 Topbar single optical weight (G2)

- [x] 4.3.1 RED: Add `test_app_layout_topbar_single_optical_weight` to `DashboardAppShellTest` (or extend the existing tests) asserting that the WS dot, bell, and avatar in `AppLayout.vue` share the same `tokens.topbar.control` (dot, bell) and `tokens.topbar.controlLg` (avatar) classes, and each carries `style="stroke-width: 1.5"` (or equivalent Tailwind utility).
- [x] 4.3.2 GREEN: In `AppLayout.vue`, add `topbar.iconSize: '20px'` and `topbar.iconWeight: 1.5` to the tokens; apply via `<span class="text-xl" style="stroke-width: 1.5">` to the WS dot, bell, and avatar.

### 4.4 Quick-action affordance (G4 — banned chevron path)

- [x] 4.4.1 RED: Extend `test_quick_action_cards_have_no_chevron_svg` in `DashboardAppShellTest` (already exists) to confirm the existing source-assertion on the banned SVG path `M9 5l7 7-7 7` still holds.
- [x] 4.4.2 GREEN: Add a `data-keyhint` attribute to each quick-action card and render a `<kbd class="text-[10px] font-medium text-systemGray-500 border border-systemGray-200 rounded px-1.5 py-0.5">` chip in the top-right corner (keyhint chip, not chevron).

### 4.5 Today-appointments empty state (G5)

- [x] 4.5.1 RED: Add `test_dashboard_empty_state_picsum_illustration` to `DashboardAppShellTest` asserting the `<EmptyState>` for "Citas Hoy" includes a `<img>` whose `src` contains `picsum.photos/seed/odontosuite-empty-calendar-`.
- [x] 4.5.2 GREEN: Add a Picsum-seeded calendar illustration inside the `<EmptyState>` for the today-appointments empty case.

### 4.6 Regression guards

- [x] 4.6.1 Run `php artisan test --filter=DashboardAppShellTest` — all 14 existing tests must remain green (the five KPI labels, five quick-action labels, 300ms debounce, `<EmptyState>`, `<UiSkeleton>`, `slice(0,3)`, `tabular-nums`, no-linear-gradient, no-scoped-style, no-hex, no-chevron, `<UiBadge data-cash-pill>`).
- [x] 4.6.2 Run `php artisan test --filter=PageRestyleTest` — every assertion must remain green.
- [x] 4.6.3 Run the full PHPUnit suite (`php artisan test`) — all 84 existing test methods must remain green.

---

## Phase 5: PR5 — Login + 404 + Sidebar Grouping (~330 lines)

### 5.1 Login placeholder text (G6)

- [x] 5.1.1 RED: Extend `LoginPageRenderTest` with `test_login_page_inputs_have_placeholders` asserting each `<input>` has a non-empty `placeholder` attribute (e.g. `placeholder="usuario"`, `placeholder="Mínimo 8 caracteres"`).
- [x] 5.1.2 GREEN: Add `placeholder="usuario"` to the username input and `placeholder="Mínimo 8 caracteres"` to the password input in `resources/js/modules/auth/LoginPage.vue`.

### 5.2 Login helper-text removal (G7)

- [x] 5.2.1 RED: Add `test_login_page_has_no_redundant_helper_text` to `LoginPageRenderTest` asserting that no `<p class="field-hint">` block contains text that lexically repeats the input label.
- [x] 5.2.2 GREEN: Remove the helper text inside the `<p class="field-hint">` blocks in `LoginPage.vue`. Keep the error path (`<p class="field-error">`).

### 5.3 Password reveal styling (G12)

- [x] 5.3.1 RED: Add `test_login_page_password_reveal_aligned_inside_frame` to `LoginPageRenderTest` asserting the password reveal button's `right` offset is `12px` (so the email and password inputs render at the same width — Playwright confirms the visual).
- [x] 5.3.2 GREEN: In `LoginPage.vue`, push the password reveal `<button class="password-toggle">` to `right: 12px` (the DOM placement inside `.field-input-wrap` is already correct; this is a styling fix only).

### 5.4 Login primary button accent-tinted shadow + inner top highlight (G8)

- [x] 5.4.1 RED: Add `test_login_page_primary_button_has_outer_shadow_and_inner_top_highlight` to `LoginPageRenderTest` asserting the primary button's `box-shadow` declaration contains `var(--elevation-3)` AND either an `inset 0 1px 0 rgba(255, 255, 255, 0.30)` declaration or a stacked-gradient equivalent.
- [x] 5.4.2 GREEN: Add `box-shadow: var(--elevation-3), inset 0 1px 0 rgba(255, 255, 255, 0.30);` to the primary button in `LoginPage.vue`.

### 5.5 Login hero scrim + eyebrow legibility (G9)

- [x] 5.5.1 RED: Add `test_login_page_hero_scrim_neutral_hue` to `LoginPageRenderTest` asserting the `.hero-overlay` background uses `rgba(60, 60, 67, 0.05)` at the top and `rgba(60, 60, 67, 0.55)` at the bottom (neutral iOS label hue, NOT the warm cream).
- [x] 5.5.2 GREEN: Change `.hero-overlay` from the warm cream gradient to `linear-gradient(180deg, rgba(60, 60, 67, 0.05) 0%, rgba(60, 60, 67, 0.55) 100%)`. Move the eyebrow color from `cream-100` to `systemGray-50` and lift opacity from 0.85 to 1.0.

### 5.6 404 hero radius + scrim (G10)

- [x] 5.6.1 RED: Add `test_not_found_page_hero_uses_radius_modal` to `LoginPageRenderTest` (or a new `tests/Unit/DesignSystem/NotFoundPageRenderTest.php`) asserting `NotFoundPage.vue` `.not-found-image` has `border-radius: var(--radius-modal);` (14px) AND a `border: 1px solid var(--color-hairline);` (NOT the warm `ink-100`).
- [x] 5.6.2 GREEN: Add the radius and hairline border to `.not-found-image` in `resources/js/modules/errors/NotFoundPage.vue`.

### 5.7 Sidebar grouping (G3 — additive only)

- [x] 5.7.1 RED: Add `test_app_layout_sidebar_group_headers_added` to `DashboardAppShellTest` asserting that `AppLayout.vue` contains exactly two group headers (`Operaciones` and `Configuración`) rendered as `<div class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500">` BEFORE the existing nav items.
- [x] 5.7.2 GREEN: Add the two group headers before "Pacientes" (the Operaciones group) and before "Sucursales" (the Configuración group). DO NOT rename, reorder, or remove any existing nav item.
- [x] 5.7.3 RED: Add `test_app_layout_nav_labels_unchanged` to `DashboardAppShellTest` asserting the visible nav count is 18 and the labels in DOM order match the pre-change source verbatim.
- [x] 5.7.4 GREEN: Verified by the existing render (no nav label changes).

### 5.8 Regression guards

- [x] 5.8.1 Run `php artisan test --filter=LoginPageRenderTest` — every existing test must remain green (one `<h1>`, autocomplete attributes, `aria-live`, no animated blobs, no pexels, no hex).
- [x] 5.8.2 Run `php artisan test --filter=NotFoundPageRenderTest` (or equivalent) — the existing escape-link + single-`<h1>` tests must remain green.
- [x] 5.8.3 Run the full PHPUnit suite (`php artisan test`) — all 84 existing test methods must remain green.

---

## Phase 6: Playwright Visual Verification (manual sweep, no JS test runner)

- [ ] 6.1 Document a new Playwright test file at `tests/playwright/premium-microdetail-2026-08.spec.mjs` (no JS test runner — manual sweep) verifying:
  - Canvas/surface contrast visible at `/dashboard` (page wrapper `background-color: rgb(242, 242, 247)`; card `background-color: rgb(255, 255, 255)`).
  - KPI row baseline uniform across all five cards at 1440x900 (`getBoundingClientRect().bottom` equal).
  - Chip absent when `delta_label === null` (Profesionales, Estado de Caja — and the 3 chip cards render the chip).
  - `ease-ios` present in computed style of every primitive under `resources/js/components/ui/`.
  - Press feedback visible on `pointerdown` (scale `<1` on KPI card; matching inner top highlight on the login primary button).
  - `prefers-reduced-motion: reduce` collapses transform-based press to opacity/colour change ≤ 200ms.
  - `prefers-reduced-transparency: reduce` solidifies `.surface-glass` (full opacity, no `backdrop-filter`).
  - `prefers-contrast: more` does NOT regress — text and borders lift, no other behaviour changes.
- [ ] 6.2 Run the sweep at the three exemplar screens (`/dashboard`, `/login`, `/404`) at 1440x900 against the local dev DB. Credentials: `admin_test` / `Password123!` at `http://127.0.0.1:8000`.
- [ ] 6.3 Capture screenshots and attach to the PR. The visual sweep is a documented verification surface, not a CI gate.

---

## Phase 7: Archive-time Reconciliation

- [ ] 7.1 Update the `dashboard-period-comparisons/spec.md` closing "Mapping to the … PR slices" section to reflect the five-slice plan (PR1 tokens, PR2 primitives, PR3 backend, PR4 dashboard polish, PR5 login + 404 + sidebar). The design's five-slice plan is authoritative.
- [ ] 7.2 Verify the existing 84 test methods remain green after each slice lands (run `php artisan test` at the end of every PR).
- [ ] 7.3 At archive, confirm the two pending user overrides (D12 two-tone numerals, per-KPI sparkline) are flagged in the archive summary so the user can act on them in a follow-up slice.

---

## Notes on test method naming

- The task above uses human-readable descriptions in `test_*` form. The actual PHPUnit method names should be camelCase and describe the specific scenario (e.g. `testTotalPatientsHeadlineUnchangedByComparison`, `testMotionDurationHasExactlyThreeKeys`).
- All test files extend existing PHPUnit `TestCase` (or `Tests\TestCase` for Feature tests). No new test scaffolds are required.
- The `$this->assertMatchesRegularExpression` / `$this->assertSame` / `$this->assertGreaterThanOrEqual` / `$this->assertFileExists` patterns already in use across the project are the right shape for these tests.
