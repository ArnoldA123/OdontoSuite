# Tasks: Login Viewport Fit

Strict TDD at per-task granularity.

## Phase 1 — CSS fix + tests

### 1.1 Source-inspection RED tests
- [ ] RED: `login_page_split_card_constrains_height_to_viewport` — grep LoginPage.vue for `max-height: calc(100vh` or `max-height: calc(100dvh`. Confirm RED.
- [ ] RED: `login_page_form_column_has_overflow_auto` — grep for `overflow-y: auto` on `.login-form-column`. Confirm RED.
- [ ] RED: `login_page_uses_min_height_100vh_on_shell` — grep for `min-height: 100vh` or `min-height: 100dvh` on `.login-page` or `.login-page-shell`. Confirm RED.
- [ ] RED: `login_page_collapses_to_single_column_below_768` — grep for `@media (max-width: 768px)` AND `grid-template-columns: 1fr` inside that block. Confirm RED.

### 1.2 CSS fix
- [ ] GREEN: in `resources/js/modules/auth/LoginPage.vue` `<style scoped>` block, add the 6 rules from `proposal.md` Approach section, plus the `@media (max-width: 768px)` mobile collapse.
- [ ] Re-run all 4 source-inspection tests — all GREEN.

### 1.3 Playwright-driven test (optional)
- [ ] Add a Playwright-driven test to `tests/Visual/` that opens the page at 1440×900 + 390×844 + 768×1024 and asserts no page scroll. Documented in `tests/Visual/login-viewport-fit.md` (not a CI gate; manual sweep evidence).

### 1.4 Regression guard
- [ ] `vendor/bin/phpunit tests/Unit/DesignSystem/LoginPageRenderTest.php` → 33 + 4 = 37/37 GREEN.
- [ ] `pnpm build` succeeds; bundle delta ≤ 0 kB.

### 1.5 Manual Playwright sweep
- [ ] 1440×900: no scroll, card visible end-to-end.
- [ ] 390×844: single column, hero top 240px, form below scrolls internally if needed.
- [ ] 768×1024: single column, hero top 240px, form below.
- [ ] 1024×768 (landscape tablet): desktop layout, no overflow.

## Phase 2 — Commit + PR
- [ ] `git add` (only LoginPage.vue + LoginPageRenderTest.php + openspec/ artifacts).
- [ ] `git commit -m "fix(login): constrain login to viewport, fix vertical overflow"`.
- [ ] `git push` + `gh pr create`.

## Phase 3 — Archive
- [ ] `archive-report.md` summarizing the cycle.
- [ ] Move the change folder to `openspec/changes/archive/`.
