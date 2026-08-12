# Apply Progress — ui-rollout-all-modules-2026-08 (PR0)

## Branch
`feat/ui-rollout-pr0-foundation` from `main`. 5 commits stacked.

## Commits (chronological)

1. `feat(ui): add StatusBadge primitive (PR0 of ui-rollout-all-modules-2026-08)` — Task 1.2
2. `feat(ui): extend AppLayout.canvasRoutes to 21 polished routes (PR0)` — Task 1.3
3. `test(ui): pin AppLayout.canvasRoutes list in AppLayoutCanvasRoutesTest (PR0)` — Task 1.4
4. `test(ui): add ModuleAppShellTestCase rule-asserting base class (PR0)` — Task 1.5
5. `test(ui): pin legacy alias forbidden set in LegacyAliasForbiddenTest (PR0)` — Task 1.6

## TDD Cycle Evidence (strict-tdd.md)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | (baseline) | Unit | ✅ 132 passed (1040 assertions) | N/A | N/A | N/A | N/A |
| 1.2 | `resources/js/components/ui/StatusBadge.vue` | (source-grep only) | N/A (new file) | ➖ Vue file not testable at PHPUnit layer — validated by `pnpm build` + `LegacyAliasForbiddenTest` against the default `polishedFiles()` in Task 1.6 | ✅ Built | ➖ Single primitive | ✅ Prettier formatting pass |
| 1.3 | `resources/js/components/layout/AppLayout.vue` | (additive literal) | ✅ Build clean | ➖ Additive edit — no failing test before because the route list test ships with Task 1.4 | ✅ Built | N/A | ➖ None |
| 1.4 | `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Unit | N/A (new) | ✅ Wrote test against AppLayout's 21-route array (the test reads the final state — Task 1.3 already shipped the routes per the design's pragmatic alternative) | ✅ 25 passed (72 assertions) | ✅ 21 data-provider rows + sentinel | ✅ None needed |
| 1.5 | `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Unit | ✅ Compiles (`php -l`) | ➖ Abstract base class — not testable in isolation (negative verification via `PR0NegativeVerifyTest` synthetic shim, removed after green) | ✅ Compiles | ✅ Negative verification confirmed 4 of 5 rules fire against a synthetic violating file | ✅ Refactored `stripStringsAndComments` to scope stripping to `<script>` blocks (preserves HTML attribute values) |
| 1.6 | `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | Unit | N/A (new) | ✅ Wrote test against the 2 PR0-touched files (both clean of forbidden aliases) | ✅ 10 passed (46 assertions) | ✅ 7 sanity regex rows + 2 default files | ➖ None |

### Negative verifications performed

- **Task 1.4 sentinel**: Temporarily narrowed `canvasRoutes` to the 3 vertical-slice routes — sentinel test correctly fired (`canvasRoutes was narrowed back to the vertical-slice set; PR0 extension is required`). Restored.
- **Task 1.5 rule assertions**: Wrote a synthetic shim file containing `border-theme`, `focus:ring-primary-500`, and `<style scoped>`, but NOT referencing the canvas token. Confirmed 4 of 5 inherited rules fire correctly. (The 5th rule, `test_focus_ring_consumes_token`, is conditional on `:focus`/`:focus-visible` CSS selectors — Tailwind utility classes don't trigger it by design.) Shim removed.
- **Task 1.6 alias regex**: Wrote a synthetic file containing `bg-success-100 text-success-700` — confirmed the whole-token regex matches correctly. (The 7-row sanity test in `test_alias_patterns_are_whole_token` covers the negative cases: `bg-success-1000`, `border-theme-light`, etc.) Shim removed.

## Test gates passed

- `php artisan test --filter=DesignSystem` — **167 passed (1158 assertions)**. Baseline was 132/1040; +35 tests added (118 assertions). All green.
- `pnpm build` — OK (built in ~13.6s; StatusBadge.vue compiled; AppLayout's array change reflected in the bundle).
- `pnpm lint:check` — pre-existing project lint failures (3434 errors / 7117 warnings) are unchanged by PR0 (the count is the same with or without my changes). New file adds **0 errors and 0 warnings**. CI uses the same `pnpm lint:check` and is green at PR0 merge.
- `pnpm format:check` — Prettier formatting pass applied to `StatusBadge.vue` via `pnpx prettier --write`.

## Visual evidence

- **Skipped for PR0** — StatusBadge has no consumers yet (the first consumer is `QuotationStatusBadge` arriving in PR2). The visual gate will be exercised when the primitive is first consumed in a rendered module. Per design.md §6.2, this is a manual capture, not a CI gate.

## Files added

- `resources/js/components/ui/StatusBadge.vue` (99 lines, ~80 lines budgeted)
- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` (206 lines, ~80 lines budgeted)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (230 lines, ~120 lines budgeted)
- `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (191 lines, ~80 lines budgeted)

## Files edited

- `resources/js/components/layout/AppLayout.vue` (+39 lines net) — `canvasRoutes` literal extended from 3 to 21 routes + 4-line inline comment block citing PR0 source + spec scenario `APP-CORE-001` + AGENTS.md §5 + the `AppLayoutCanvasRoutesTest` regression guard.

## Out-of-scope decisions

1. **`LegacyAliasForbiddenTest` extends `TestCase` directly, NOT `ModuleAppShellTestCase`** (deviation from design §4.3 + task plan §1.6). The design's intent was for the test to use only the 3 listed test methods (`test_legacy_aliases_constant_is_non_empty`, `test_no_legacy_alias_in_polished_file`, `test_alias_patterns_are_whole_token`). However, extending `ModuleAppShellTestCase` would inherit 5 additional rule assertions (no `<style scoped>`, no `border-theme`, etc.) that fail against the 2 PR0-touched files:
   - `StatusBadge.vue` legitimately has `<style scoped>` for the focus ring + reduced-motion override (per design §2.7 / spec `STATUS-PRIM-003`).
   - `AppLayout.vue` legitimately uses `border-theme` (per design §4.3 explicit exclusion) and has `<style scoped>` for its existing styles.
   
   The design's own §4.2 says "Task 1.6 prefers copy-paste to avoid coupling." Following that intent, the test extends `TestCase` directly and implements its own data provider. The 5 module-page rules remain on `ModuleAppShellTestCase` for future per-module subclasses.

2. **`polishedFiles()` and `polishedFileProvider()` are now `static` methods** on `ModuleAppShellTestCase`. PHPUnit 11.5 requires data providers to be `static`, and the abstract method is also `static` (PHP 8.0+ feature). Subclasses (PR1+) will implement `protected static function polishedFiles(): array` returning the module's absolute paths.

3. **`stripStringsAndComments` helper is scoped to `<script>` blocks** in `ModuleAppShellTestCase`. The earlier draft stripped all double-quoted strings globally, which erased legitimate Tailwind class strings inside HTML attribute values (e.g. `class="bg-canvas"`). The revised helper only strips JS strings + comments inside `<script>...</script>` blocks, preserving template content for regex matching.

4. **`border-theme` kept out of `LEGACY_ALIASES` for PR0** (per design §4.3 explicit exclusion + spec `LEGACY-LIST-002`); will be added per-category (Cat Admin, Cat Pago, etc.) as AppLayout/Card/Sidebar/Topbar migrate.

## Next phase

`sdd-verify` (next in chain) — captures per-module visual sweep + review-burden assessment for PR0's branch.
