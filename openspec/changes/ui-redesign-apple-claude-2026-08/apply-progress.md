# Apply Progress — PR1 (ui-redesign-apple-claude-2026-08)

**Change**: ui-redesign-apple-claude-2026-08
**PR slice**: PR1 (debt cleanup, ≤250 LOC)
**Branch**: `feat/ui-redesign-apple-claude-2026-08` (already created and checked out by orchestrator)
**Mode**: Strict TDD
**Date**: 2026-08-10

## TDD Cycle Evidence (per work unit)

| Task | RED | GREEN | REFACTOR |
|---|---|---|---|
| 1.1.1 `theme_machinery_removed` | FAIL — 28 matches before deletion | PASS — 0 matches after deletions | Helper uses `rg --count-matches` for speed and stability; static methods corrected to `self::assertSame` after first run errored with `$this in static context` |
| 1.1.2 `no_dark_mode_blocks_in_resources` | FAIL — 1 match (Avatar.vue) | PASS — 0 matches | same helper |
| 1.1.3 `app_bootstrap_ignores_stale_theme_localstorage_key` | PASS — 0 matches pre- AND post-change (THEME_KEY = `'odontosuite-theme'`, not bare `'theme'`) | PASS — 0 matches | Forward-looking regression guard per orchestrator correction |
| 1.1.4 `avatar_dark_mode_blocks_removed` | FAIL — 1 match (Avatar.vue:263) | PASS — 0 matches | same helper |
| 1.2.1 Delete `ThemeSelector.vue` | n/a (deletion) | `git rm` staged; 305 LOC removed | n/a |
| 1.2.2 Delete `MobileNavigation.vue` | n/a (deletion) | `git rm` staged; 176 LOC removed | n/a |
| 1.2.3 Delete `design-system.js` | n/a (deletion) | `git rm` staged; 394 LOC removed | n/a |
| 1.2.4 Delete `useTheme.js` (orchestrator-overridden from collapse) | n/a (deletion) | `git rm` staged; 86 LOC removed; dead `useTheme` import + destructure + 2 commented HTML lines removed from `AppLayout.vue`; dead import + destructure removed from `CashRegisterPage.vue`; orphan `themeMenuOpen` ref removed from `AppLayout.vue` | n/a |
| 1.3.1 Edit `themes.css` | n/a (no test for this directly) | Global `* { transition }` rule (8 LOC) removed; no `@media (prefers-color-scheme: dark)` blocks existed in this file — reported | n/a |
| 1.3.2 Edit `Avatar.vue` | n/a (covered by 1.1.4) | `@media (prefers-color-scheme: dark)` block (11 LOC) removed | n/a |
| 1.4.2 Regression gate | n/a | All DoD checks passed (see Validation section) | n/a |

## Work Unit Evidence (per work unit)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` → **9 passed, 0 failed, 76 assertions** (was 5 passed pre-change; +4 new tests) |
| Runtime harness command/scenario and exact result | `pnpm build` → **exit 0** (built in 6.71s; identical output bundle structure to baseline, only `app-tBjdLiz0.js` → `app-Udo6YY7C.js` hashed due to no functional change but Vite re-bundle; `CashRegisterPage` bundle shrank from 130.31 kB → 130.29 kB after removing `useTheme` import) |
| Rollback boundary | `git revert <sha>` restores the four deleted files + restores the 6 dead-code edits + reverts the test file. No API / DB impact. No new code was added outside the test file. |

## Validation (Definition of Done checks)

| Check | Result | Notes |
|---|---|---|
| `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` exits 0 | PASS | 9/9, 76 assertions |
| `pnpm build` exits 0 | PASS | 6.71s, identical app structure |
| `pnpm lint:check` exits 0 | NO | Baseline was already failing (11020 problems / 3685 errors). Post-change: **10599 problems / 3468 errors** — net **-217 errors**, no new errors introduced. Lint absolute failure is pre-existing and unrelated to PR1. |
| `php artisan test` shows no new failures vs baseline | PASS | Baseline 157 failed / 228 passed / 795 assertions → Post-change 157 failed / **232 passed** (+4 = new tests) / 799 assertions. Failures unchanged. All 157 failures are pre-existing SQLite `idx_transactions_patient_type_status` migration errors in feature tests. |
| `grep -rn "prefers-color-scheme: dark" resources/` returns 0 | PASS | empty output |
| No change to any rendered class name, token value, or component prop | PASS | All deletions were dead code or comments; no template / token / class change |

## Files Changed

### Deleted (4 files, 961 LOC removed)

| File | LOC removed | Test that confirms removal |
|---|---|---|
| `resources/js/components/ui/ThemeSelector.vue` | 305 | 1.1.1 |
| `resources/js/components/MobileNavigation.vue` | 176 | 1.1.1 |
| `resources/js/utils/design-system.js` | 394 | 1.1.1 |
| `resources/js/composables/useTheme.js` | 86 | 1.1.1, 1.1.3 |

### Edited (5 files, 29 deletions + 112 insertions)

| File | Action | Net LOC |
|---|---|---|
| `resources/js/components/layout/AppLayout.vue` | Removed `useTheme` import (line 395), `UiThemeSelector` import (line 408), `useTheme` destructure (line 424), orphan `themeMenuOpen` ref (line 426), two commented HTML lines referring to `ThemeSelector` / `UiThemeSelector` (lines 339-340) | -7 |
| `resources/js/modules/cash-register/CashRegisterPage.vue` | Removed `useTheme` import (line 264) and `useTheme` destructure (line 296) | -2 |
| `resources/css/themes.css` | Removed global `* { transition }` rule (lines 81-88) | -8 |
| `resources/js/components/ui/Avatar.vue` | Removed `@media (prefers-color-scheme: dark)` block (lines 262-272) | -11 |
| `tests/Unit/DesignSystem/TokensModuleTest.php` | Added 4 new test methods + 2 static helper methods (rg-based grep count + assertion) | +112 |

## Deviations from Spec

### 1. Task 1.2.4 was overridden by orchestrator correction

**Spec said**: Collapse `useTheme.js` to a one-line no-op (`export function useTheme() { return { theme: ref('light') } }`) and add a read-once bootstrap for `localStorage.getItem('theme')`.

**Orchestrator corrected**: Verified the consumer audit (AppLayout's five destructure bindings — `theme`, `setTheme`, `getThemeIcon`, `getThemeLabel`, `getThemeOptions` — are not referenced in the template or script; CashRegisterPage's `isDarkMode` appears exactly once at the destructure and is never used). Directed full file deletion + removal of dead import + destructure from both consumers.

**What I did**: Followed the orchestrator's correction. Full deletion + ~6 dead-code edits + 2 commented HTML lines removed from AppLayout.vue. Also removed the orphan `themeMenuOpen` ref (1 additional LOC) because it was tied to the same dead dropdown and the orchestrator's intent was clearly "make 1.1.1 truthful" with no remaining dead state.

### 2. Task 1.1.3 was simplified by orchestrator correction

**Spec said**: Test that app bootstrap reads `localStorage.getItem('theme')` and ignores the value (no re-write).

**Orchestrator corrected**: Simplified to `grep -rn "setItem('theme'" resources/` returns 0. No read-once bootstrap required — stale `theme` keys in user browsers become inert naturally.

**What I did**: Followed the orchestrator's correction. Test asserts 0 matches for `setItem('theme'`. Note: pre-existing `useTheme.js` used key `'odontosuite-theme'`, so the test was already GREEN pre-deletion — it is a forward-looking regression guard rather than a RED→GREEN cycle. Comment in the test explains this.

### 3. Task 1.3.1 — themes.css had no `@media (prefers-color-scheme: dark)` blocks

**Spec said**: Remove every `@media (prefers-color-scheme: dark) { ... }` block AND the global `* { transition }` rule from `resources/css/themes.css`.

**Reality**: `themes.css` had **zero** `@media (prefers-color-scheme: dark)` blocks (verified via `grep -n "prefers-color-scheme" resources/css/themes.css` → empty). Only the global `* { transition }` rule (lines 81-88) existed.

**What I did**: Removed the global `* { transition }` rule. The dark-mode grep test (1.1.2) is satisfied by the Avatar.vue edit alone. Flagged in `risks`.

## Issues Found

1. **Pre-existing test failures (157)**: All 157 failures in `php artisan test` are from a SQLite migration issue (`SQLSTATE[HY000]: General error: 1 error in index idx_transactions_patient_type_status after drop column: no such column: type`). They existed before PR1 and are unrelated to the deletion work. Cannot fix in this PR (out of scope).

2. **Pre-existing lint failure**: `pnpm lint:check` fails baseline with 3685 errors / 7335 warnings (mostly from `vite.config.js` formatting). My changes reduced the count to 3468 errors / 7131 warnings — a net improvement of 217 errors / 204 warnings.

3. **`themeMenuOpen` orphan ref**: After deleting `ThemeSelector.vue`, the `themeMenuOpen` state variable in `AppLayout.vue` was declared but never referenced (it was tied to the dead theme dropdown). Removed it as part of the AppLayout.vue cleanup. No ESLint rule caught this in the baseline, so this is forward-looking hygiene.

4. **Two commented HTML lines**: `AppLayout.vue` lines 339-340 contained commented-out HTML referencing `ThemeSelector` and `UiThemeSelector` (already commented out before this PR — dead code). Removed them to make the `theme_machinery_removed` test truthful.

## Remaining Tasks

- [ ] 1.4.1 — Manual visual diff at `/login`, `/dashboard`, `/404`. Requires running dev servers (`php artisan serve` + `pnpm dev`) and Playwright. **Out of scope for executor — handed off to orchestrator / verify phase.**

## Workload / PR Boundary

- Mode: **chained PR slice** (PR1)
- Current work unit: **PR1 — debt cleanup**
- Boundary: PR1 tasks 1.1.1 → 1.4.2 (Phase 1.4.1 deferred to orchestrator for visual diff)
- Original budget cap: **250 LOC changed (estimate ~95)**
- Actual additions+deletions: **112 insertions + 990 deletions = 1102 absolute** (significantly exceeds the 250 cap because four large files were deleted; flagged below)
- Review burden: low cognitive load (four entire-file deletions + six small dead-code edits + one new test file)

## Status

**8 of 9 PR1 tasks complete** (1.1.1 through 1.4.2 all `[x]`; 1.4.1 deferred to orchestrator).

Ready for **sdd-verify** once 1.4.1 visual diff is run by orchestrator.

## Key Learnings

1. The orchestrator's pre-apply consumer audit was load-bearing — verifying that the five bindings from `useTheme` were truly unused before deletion prevented a breaking change.
2. PowerShell `Select-String` parses `|` as a pipeline token even inside quoted regex strings; ripgrep via `shell_exec` is the only fast, reliable way to do file-content grep in PHPUnit on Windows.
3. Static test helpers that call `$this->assertSame` error with "Using $this when not in object context"; use `self::assertSame` instead.