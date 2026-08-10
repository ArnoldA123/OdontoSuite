# Tasks: ui-redesign-apple-claude-2026-08

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~1280 (PR1 95 + PR2 595 + PR3 555) additions + deletions |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 (debt cleanup) -> PR2 (tokens + primitives + motion) -> PR3 (Login + Dashboard + 404 + AppLayout chrome) |
| Delivery strategy | ask-on-risk (already exercised -> 3 chained PRs) |
| Chain strategy | stacked-to-main |

```
Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High
```

### Branch topology (stacked-to-main)

- PR1 branch: `feat/ui-redesign-apple-claude-2026-08`  <- `main`
- PR2 branch: `feat/ui-redesign-apple-claude-2026-08-p2` <- PR1 branch
- PR3 branch: `feat/ui-redesign-apple-claude-2026-08-p3` <- PR2 branch

### Per-slice work units

| Unit | Goal | PR | Focused test command | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|
| 1 | Remove dark-mode machinery + dead files; no visual change | PR1 | `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` | `php artisan serve` + `pnpm dev`; manual visual diff | `git revert <sha>` restores 5 files; no API/DB impact |
| 2 | New token pipeline, primitives, motion runtime, fonts | PR2 | `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php tests/Unit/DesignSystem/GeneratedTokensCssTest.php` + `pnpm build` | `php artisan serve` + `pnpm dev`; pre-PR2 visual baselines | `git revert <sha>` (must revert test in same commit) |
| 3 | Login + Dashboard + 404 + AppLayout chrome | PR3 | `php artisan test` + 7-step Playwright recipe | `php artisan serve` + `pnpm dev`; `adm1n` / `password123` | `git revert <sha>`; primitives stay on PR2 tokens |

### Environment / tool prerequisites (apply phase must verify)

- `node` >= 18 available (used by `scripts/build-tokens-css.mjs`).
- `ffmpeg` available locally **only** if the optional login mp4 (`6763242_clinic_v1.mp4`) is committed. Otherwise skip.
- **Network access required**: downloading `Newsreader-VariableFont_OPSZ,wght.woff2` from `https://fonts.google.com` / `https://github.com/ProductionType/Newsreader` release. If offline, ship a placeholder woff2 named identically and flag in PR2 description.
- Playwright CLI per `C:/Users/chomb/.claude/skills/playwright-cli/SKILL.md`.

---

## PR1: Debt cleanup (target: `feat/ui-redesign-apple-claude-2026-08` from `main`)

Goal: visually inert. App loads identically. No token / class / behavior change.

PR1 running-total budget cap: **<= 250 LOC changed** (current estimate: ~95).

### Phase 1.1: RED tests first (TDD)

- [x] 1.1.1 Add test `theme_machinery_removed` to `tests/Unit/DesignSystem/TokensModuleTest.php`: shells `grep -rn "useTheme\\|setTheme\\|getThemeOptions\\|ThemeSelector\\|design-system.js\\|MobileNavigation" resources/` (excluding `tests/`) and asserts 0 matches. Verify: `vendor/bin/phpunit --filter theme_machinery_removed` exits 0 only after deletions.
- [x] 1.1.2 Add test `no_dark_mode_blocks_in_resources` to `tests/Unit/DesignSystem/TokensModuleTest.php`: shells `grep -rn "prefers-color-scheme: dark" resources/` and asserts 0 matches. Verify: same command exits 1 (no matches) after PR1 deletions.
- [x] 1.1.3 Add test `app_bootstrap_ignores_stale_theme_localstorage_key` to `tests/Unit/DesignSystem/TokensModuleTest.php`: static-greps `resources/js/app.js` (or the actual bootstrap entry) for the one-line read-once of `localStorage.getItem('theme')` and asserts the value is consumed but never re-written via `localStorage.setItem('theme', ...)`. Verify: `grep -rn "setItem('theme'" resources/` returns 0.
- [x] 1.1.4 Add test `avatar_dark_mode_blocks_removed` to `tests/Unit/DesignSystem/TokensModuleTest.php`: shells `grep -rn "prefers-color-scheme: dark" resources/js/components/ui/Avatar.vue` and asserts 0 matches. Verify after edit.

### Phase 1.2: Pure deletions + no-op collapse (lowest risk, ordered first)

- [x] 1.2.1 Delete file `resources/js/components/ui/ThemeSelector.vue`. Verify: `git ls-files resources/js/components/ui/ThemeSelector.vue` exits non-zero; test 1.1.1 passes. LOC: -306.
- [x] 1.2.2 Delete file `resources/js/components/MobileNavigation.vue` (actual path; proposal said `layout/MobileNavigation.vue`, file lives at repo root of `components/`). Verify: `git ls-files` returns nothing for either spelling; test 1.1.1 passes. LOC: -177.
- [x] 1.2.3 Delete file `resources/js/utils/design-system.js` (stale duplicate, no importers per exploration). Verify: `grep -rn "from.*design-system.js" resources/` returns 0; test 1.1.1 passes. LOC: -394.
- [x] 1.2.4 (orchestrator-overridden) Delete `resources/js/composables/useTheme.js` entirely and remove dead import + destructure lines from `AppLayout.vue` and `CashRegisterPage.vue`. Verify: tests 1.1.1 + 1.1.3 pass. LOC: -86 + 4 = -90.

### Phase 1.3: CSS + Avatar dark-mode block removal

- [x] 1.3.1 Edit `resources/css/themes.css`: remove every `@media (prefers-color-scheme: dark) { ... }` block and remove the global `* { transition: ... }` rule. (Note: themes.css has NO `@media (prefers-color-scheme: dark)` blocks; only the global `* { transition }` rule was present and is removed.) Verify: `grep -n "prefers-color-scheme: dark" resources/css/themes.css` returns 0; `grep -n "^\s*\*\s*{" resources/css/themes.css | grep transition` returns 0. LOC: ~-8.
- [x] 1.3.2 Edit `resources/js/components/ui/Avatar.vue`: remove the `@media (prefers-color-scheme: dark)` block (and any dark-variant style it owns). Verify: test 1.1.4 passes; `pnpm build` exits 0. LOC: -11.

### Phase 1.4: PR1 regression gate (must run last)

- [ ] 1.4.1 Run `php artisan serve` (`:8000`) + `pnpm dev` (`:5173`) and confirm `GET /login`, `GET /dashboard` (after `adm1n` / `password123` login), `GET /404` are pixel-identical to pre-PR1 screenshots. No new console errors. Verify: `git stash` + diff of the three screenshots equals empty. **(DEFERRED to orchestrator handoff — requires running dev servers + visual diff, outside executor scope.)**
- [x] 1.4.2 Run `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` -> exit 0. Run `pnpm build` -> exit 0. Run `php artisan test --filter=AuthTest` -> exit 0 (regression on login API surface). (AuthTest: 4 pre-existing failures from SQLite migration `idx_transactions_patient_type_status` — identical to baseline, unrelated to PR1.)

PR1 changed-line estimate: **actual ~1102 absolute (112 insertions in test file + 29 deletions in modified files + 961 deletions in removed files)**. Original spec estimate was ~95 LOC assuming the `useTheme.js` collapse (task 1.2.4); the orchestrator's correction overrode the collapse with full deletion, increasing the absolute change count. The PR1 budget cap of 250 was written for the collapsed plan; reviewers will see four large deletions + small edits in two existing files + a test addition — easy to scan, well under review cognitive load, but exceeds the originally stated 250-LOC budget. Flagged in `risks`.

---

## PR2: Token pipeline + primitives + motion (target: `feat/ui-redesign-apple-claude-2026-08-p2` from PR1 branch)

Goal: ship the new design language at the primitive layer without touching the three vertical exemplars (those land in PR3). Every Tailwind class name used by the 17 un-migrated modules is preserved; only rendered values change.

PR2 running-total budget cap: **<= 600 LOC changed** (current estimate: ~595).

### Phase 2.1: RED tests for the token surface

- [ ] 2.1.1 Extend `tests/Unit/DesignSystem/TokensModuleTest.php` with `tokens_module_exposes_new_ramps`: asserts `tokens.colors` contains `terracotta`, `cream`, `ink`, `clinicalTeal` and that the required steps exist (`terracotta` {400,500,600,700}, `cream` {50,100,200}, `ink` {700,800,900}, `clinicalTeal` {500,600}). Verify: `vendor/bin/phpunit --filter tokens_module_exposes_new_ramps` exits 0 only after 2.2.1.
- [ ] 2.1.2 Add `tokens_module_drops_info_and_dark_suffixes`: asserts no key ends in `-dark`/`Dark`/`_dark` and `colors.info` is absent. Verify after 2.2.1.
- [ ] 2.1.3 Add `tokens_module_typography_has_serif_and_per_step_tracking`: asserts `tokens.typography.fontFamily.serif` starts with `Newsreader` AND `tokens.typography.fontSize[display][1].letterSpacing === '-0.03em'`. Verify after 2.2.1.
- [ ] 2.1.4 Add `tokens_module_motion_section_present`: asserts `tokens.motion.response === 0.35` AND `tokens.motion.damping === 1.0`. Verify after 2.2.1.
- [ ] 2.1.5 Create `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` with `generated_css_single_root_block`: shells `grep -c "^:root" resources/css/tokens.generated.css` and asserts exactly 1. Verify after 2.2.4.
- [ ] 2.1.6 Add `generated_css_has_no_external_font_request`: shells `grep -rn "fonts.googleapis\\|fonts.gstatic" resources/css/ resources/js/ resources/views/` and asserts 0 matches. Verify after 2.2.4.
- [ ] 2.1.7 Add `generated_css_has_font_face_swap`: parses `tokens.generated.css`, asserts `@font-face` block exists, `font-family` contains `Newsreader`, `src` references `/fonts/Newsreader-VariableFont_OPSZ,wght.woff2`, AND `font-display: swap`. Verify after 2.2.4.
- [ ] 2.1.8 Add `generated_css_surface_glass_class_emitted_exactly_once`: shells `grep -c "^\.surface-glass" resources/css/tokens.generated.css` and asserts 1. Verify after 2.2.4.
- [ ] 2.1.9 Add `card_variant_glass_has_no_backdrop_filter`: shells `grep -n "backdrop-filter" resources/js/components/ui/Card.vue` and asserts 0. Verify after 2.3.2.
- [ ] 2.1.10 Add `primitives_have_no_backdrop_filter_outside_chrome`: shells `grep -rn "backdrop-filter" resources/js/components/ui/ | grep -v Card.vue` and asserts 0. Verify after 2.3.x.
- [ ] 2.1.11 Add `no_universal_transition_selector_in_css`: shells the grep from design Testing Strategy #1 and asserts 0 matches across `resources/css/themes.css`, `resources/css/tokens.css`, `resources/css/utilities.css`. Verify after 2.2.4.
- [ ] 2.1.12 Add `generated_css_only_contains_token_hex_literals`: parses `tokens.generated.css`, extracts every `#[0-9A-Fa-f]{6}`, asserts set-equal to the union of all `tokens.colors.*` hex values. Verify after 2.2.4 (catches iCloud-blue drift the unit test cannot).

### Phase 2.2: GREEN - token pipeline + CSS file collapse

- [ ] 2.2.1 Edit `resources/js/design-system/tokens.js`: rename `primary` -> `terracotta` (keep old name as a deprecated alias to avoid 2.x churn), add `cream {50,100,200,300}`, `ink {50,100,200,300,500,600,700,800,900}`, `clinicalTeal {50,100,300,500,600,700}`. Delete `colors.info`. Extend `typography.fontFamily` with `serif: ['Newsreader', 'ui-serif', 'New York', 'Georgia', 'serif']`. Extend `typography.fontSize` with per-step `letterSpacing` / `lineHeight` per Decision 3. Add `motion` section exporting `{ response: 0.35, damping: 1.0, dampingBounce: 0.8, stiffness: 1.0, easings: {...} }`. Verify: tests 2.1.1-2.1.4 pass. LOC: ~+90.
- [ ] 2.2.2 Edit `tailwind.config.js`: re-source color imports from the new `tokens.js` exports (drop the iCloud-blue `#0066CC` literal that lives in `themes.css` today). Wire `addUtilities` for `--color-accent`, `--color-surface-elevated`, `--glass-bg` aliases that point to new ramp values. Verify: `pnpm build` exits 0. LOC: ~+30.
- [ ] 2.2.3 Create `scripts/build-tokens-css.mjs` (Node, ESM): dynamically imports `tokens.js`, emits `resources/css/tokens.generated.css` containing one `:root` block with `--color-terracotta-*`, `--color-cream-*`, `--color-ink-*`, `--color-clinical-teal-*`, `--color-success-*`, `--color-warning-*`, `--color-error-*` variables, the semantic aliases (`--color-accent`, `--color-surface-elevated`, `--glass-bg`), the `--motion-*` variables, the `@font-face` block for Newsreader (single file, `font-display: swap`, `font-optical-sizing: auto`), one `@media (prefers-reduced-transparency: reduce)` block, and one `@media (prefers-contrast: more)` block. Add `package.json` script `tokens:build`. Verify: `node scripts/build-tokens-css.mjs` exits 0; `cat resources/css/tokens.generated.css | head` shows a single `:root`. LOC: ~+120.
- [ ] 2.2.4 Run `node scripts/build-tokens-css.mjs` to emit `resources/css/tokens.generated.css` (committed artifact, not gitignored). Verify: tests 2.1.5-2.1.8, 2.1.11, 2.1.12 pass. LOC: ~+260 (generated, but counted once toward budget).
- [ ] 2.2.5 Delete `resources/css/design-tokens.css`, `resources/css/themes.css`, `resources/css/animations.css`. Edit `resources/css/utilities.css` to drop duplicate `@keyframes shimmer`, `@keyframes slideUp`, `.ds-*` duplicates; keep `.spinner-ring`, `.pulse-subtle`, `.focus-ring`, z-index, scrollbar, safe-area, reduced-motion overrides. Edit `resources/css/app.css` imports to `tokens.generated.css` + `utilities.css` only; preserve the `@media print` block byte-identical. Verify: `grep -rn "design-tokens.css\\|themes.css\\|animations.css" resources/` returns 0 (excluding any reference comments); `php artisan view:cache` exits 0; print block selectors (`summary-box`, `cash-reports`) still present. LOC: ~-560 (deletions) + ~+60 (utilities rewrite) = net -500.
- [ ] 2.2.6 Add `public/fonts/Newsreader-VariableFont_OPSZ,wght.woff2` (~38 KB, downloaded from ProductionType / Google Fonts). Verify: `Test-Path 'public/fonts/Newsreader-VariableFont_OPSZ,wght.woff2'` is true; `(Get-Item ...).Length` <= 51200 bytes. **REQUIRES NETWORK ACCESS in apply phase; without network, ship a placeholder woff2 named identically and flag in PR2 description.** LOC: +0 (binary asset, not counted toward budget cap but tracked in `git ls-files`).

### Phase 2.3: GREEN - primitive restyle

- [ ] 2.3.1 Edit `resources/js/components/ui/Button.vue`: swap hardcoded hex for `bg-terracotta-500` / `border-terracotta-500` / `text-cream-50` token classes; add scoped `transition` on `:hover`/`:focus`/`:active` for `background-color`, `border-color`, `color`, `box-shadow` only (replacement for the removed global). Verify: `pnpm build` exits 0; visual diff vs pre-PR2 baseline near-identical except accent shift. LOC: ~+25.
- [ ] 2.3.2 Edit `resources/js/components/ui/Card.vue`: redefine `variant="glass"` as **opaque** (cream-100 bg, ink-200 hairline, shadow-medium, rounded-xl, NO `backdrop-filter`). Add `// Historical name: kept for compat. See .surface-glass for chrome translucent surfaces.` comment in `<script setup>`. Keep `default`, `flat`, `elevated`, `outlined` variants - only rendered values change. Verify: test 2.1.9 passes; the 76 call sites across 21 modules continue to render the data-card look. LOC: ~+15.
- [ ] 2.3.3 Edit `resources/js/components/ui/Modal.vue`: token-driven colors; remove dark block; add `@media (prefers-reduced-motion: reduce)` + `@media (prefers-contrast: more)` blocks. Verify: `pnpm build` exits 0. LOC: ~+20.
- [ ] 2.3.4 Edit `resources/js/components/ui/Sheet.vue`: token-driven; add `position="left"` styling for the mobile menu reuse; add `@media (prefers-reduced-transparency: reduce)` solid fallback. Verify: `pnpm build` exits 0. LOC: ~+20.
- [ ] 2.3.5 Edit `resources/js/components/ui/Input.vue`: token-driven; replace hex with `border-ink-200`, `focus:border-terracotta-500`, `focus:ring-terracotta-500`; add reduced-motion + reduced-transparency + contrast blocks. Verify: `pnpm build` exits 0; visual baseline near-identical. LOC: ~+20.
- [ ] 2.3.6 Edit `resources/js/components/ui/Badge.vue`: keep `variant="info"` but alias it to `clinicalTeal-500`; token-driven otherwise. Verify: `pnpm build` exits 0. LOC: ~+10.
- [ ] 2.3.7 Edit `resources/js/components/ui/Toast.vue`, `Skeleton.vue`, `LoadingSpinner.vue`, `EmptyState.vue`: token-driven only (no class renames). Verify: `pnpm build` exits 0. LOC: ~+30 (split).
- [ ] 2.3.8 Edit `resources/js/components/ui/Avatar.vue`, `Breadcrumbs.vue`, `Tabs.vue`, `ConfirmDialog.vue`, `NotificationToast.vue`: token-driven only. Verify: `pnpm build` exits 0. LOC: ~+25 (split).
- [ ] 2.3.9 Create `resources/js/composables/useSpring.js` (~180 LOC per Decision 2): rAF loop + CSS var writes; `set(target, { velocity })` API; reduced-motion instant fallback; `onUnmounted(stop)`. Verify: smoke test in browser console `useSpring({response:0.35,damping:1.0}).set(100)` reaches 100 without overshoot. LOC: ~+180.
- [ ] 2.3.10 Create `resources/js/composables/useSpring2D.js` (~60 LOC): independent X/Y springs; `projectAndSnap(current, velocity, snapPoints, d=0.998)`. Verify: `x.set(120, {velocity:800})` + `y.set(-40)` do not couple. LOC: ~+60.
- [ ] 2.3.11 Create `resources/js/composables/useFontsLoaded.js` (~20 LOC): flips a ref on `document.fonts.ready`; sets `<html data-fonts-loaded="true">`. Verify: load `/login` with throttled network; data attribute appears within ~200 ms after first paint. LOC: ~+20.

### Phase 2.4: PR2 regression gate

- [ ] 2.4.1 Capture visual baselines in `tests/Visual/baselines/{login,dashboard,not-found}-pre-pr2.png` (PNG, <= 200 KB each, byte-stable via `pnpm vitest --update`). Verify: files exist; each <= 204800 bytes.
- [ ] 2.4.2 Run `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php tests/Unit/DesignSystem/GeneratedTokensCssTest.php` -> exit 0. Run `pnpm build` -> exit 0. Run `php artisan test --testsuite=Unit` -> exit 0. Run `pnpm lint:check` -> exit 0.
- [ ] 2.4.3 Playwright diff: `php artisan serve` + `pnpm dev`, `playwright-cli open http://localhost:8000/login --filename=after-tokens.png`, `playwright-cli open http://localhost:8000/dashboard --filename=after-dashboard.png`. Compare to baselines; allowed diff: accent color only (iCloud-blue -> terracotta).
- [ ] 2.4.4 Run `git ls-files | grep _v1.mp4` and assert zero or one entry (the optional login candidate). Confirm `.gitignore` contains `public/images/pexels/**/_v*.mp4` exception line. Verify: `grep "public/images/pexels/\*\*/_v" .gitignore` exits 0.

PR2 changed-line estimate: **~595 LOC**. Budget cap 600 -> 99% used. PR2 is at the cap; if any task overruns, split `tokens.js` typography edits into a follow-up PR.

PR2 risks that may push over cap:
- Generator script complexity (2.2.3): the font-face block + media queries + semantic aliases could exceed 260 LOC. If it does, split media-query blocks into a sibling `tokens.media.css` generated by the same script.
- `useSpring.js` (2.3.9) at 180 LOC is the largest single file; if reduced-motion paths add more, split into `useSpring.js` + `useSpring.reduced.js`.

---

## PR3: Login + Dashboard + 404 + AppLayout chrome (target: `feat/ui-redesign-apple-claude-2026-08-p3` from PR2 branch)

Goal: the design-language exemplar end-to-end. Three vertical flows on the new primitive layer. Asset commits. Playwright verification.

PR3 running-total budget cap: **<= 550 LOC changed** (current estimate: ~555).

### Phase 3.1: RED tests + asset prep

- [ ] 3.1.1 Add `login_form_has_exactly_one_h1_and_programmatic_labels` to a new `tests/Feature/Visual/LoginRenderTest.php`: GETs `/login` (after seeding an admin), asserts exactly one `<h1>`, each `<input>` has an associated `<label for>`, the form has `aria-live="polite"` region, and the username input is the first focusable. Verify: `vendor/bin/phpunit --filter login_form_has_exactly_one_h1_and_programmatic_labels` exits 0 only after 3.3.1.
- [ ] 3.1.2 Add `dashboard_renders_three_regions_in_order`: GETs `/dashboard` as `adm1n`, asserts DOM order is `stats row` -> `quick actions row` -> `appointments list`. Verify after 3.4.1.
- [ ] 3.1.3 Add `dashboard_no_inline_gradients_or_scoped_style`: `grep -n "linear-gradient\\|bg-gradient" resources/js/modules/dashboard/DashboardPage.vue` returns 0; the file contains no `<style scoped>` block. Verify after 3.4.1.
- [ ] 3.1.4 Add `not_found_page_has_escape_link_and_image`: GETs `/this-route-does-not-exist`, asserts one `<h1>`, one link to `/login`, one `<img>` pointing to `4439425_page-404_p3.jpg`. Verify after 3.5.1.
- [ ] 3.1.5 Add `app_layout_uses_surface_glass_and_min_dvh`: `grep -n "surface-glass" resources/js/components/layout/AppLayout.vue` >= 2 (sidebar + topbar); `grep -n "h-screen\\|height: 100vh" resources/js/components/layout/AppLayout.vue` returns 0; the layout uses `min-h-[100dvh]`. Verify after 3.6.1.
- [ ] 3.1.6 Add `reset_token_not_in_reset_password_modal`: `grep -rn "reset_token" resources/js/modules/auth/ResetPasswordModal.vue` returns 0. Verify after 3.3.3.
- [ ] 3.1.7 Commit `public/images/pexels/auth/login/6812463_modern-dental_p2.jpg` (56 KB hero still) and `public/images/pexels/errors-404/4439425_page-404_p3.jpg` (16 KB). Verify: `git ls-files` shows both; each <= 60 KB and <= 30 KB respectively.
- [ ] 3.1.8 OPTIONAL commit of `public/images/pexels/auth/login/6763242_clinic_v1.mp4` after re-encoding via `ffmpeg` to H.264 720p <= 2 MB and extracting a JPEG poster frame. **REQUIRES `ffmpeg` in environment; if missing or output > 2 MB, skip and ship only the still fallback.** Verify: `ffmpeg -i ... -c:v libx264 -preset slow -crf 28 -vf scale=-2:720 -movflags +faststart -an output.mp4 && ffmpeg -i output.mp4 -ss 1 -frames:v 1 poster.jpg`. `git ls-files | grep _v1.mp4` returns 0 or 1 row.

### Phase 3.2: GREEN - LoginCard deletion + LoginPage rebuild

- [ ] 3.2.1 Delete `resources/js/components/auth/LoginCard.vue`. Verify: `git ls-files` shows it removed. LOC: -42.
- [ ] 3.2.2 Rebuild `resources/js/modules/auth/LoginPage.vue` (~530 -> ~280 LOC, net -250): editorial split grid (`grid-cols-1 md:grid-cols-2`), brand mark + serif headline (`.font-serif`, `text-hero`, `letter-spacing: -0.035em`) on left, hero `<img>` with `loading="lazy"`, `decoding="async"`, `alt="Equipo dental moderno"` on right. Delete the three `div.shape` blobs and the `linear-gradient` button override. Form uses `UiCard variant="glass" padding="lg"` (opaque per 2.3.2) as the surface. Primary CTA uses `bg-terracotta-500 text-cream-50`. Verify: test 3.1.1 passes; `pnpm build` exits 0. LOC: ~-250.
- [ ] 3.2.3 Wire `useSpring({ response: 0.35, damping: 1.0 })` for the card entrance. Under `prefers-reduced-motion: reduce`, the spring is instant and only opacity cross-fades (<= 200 ms). Verify: Playwright checkpoint 2 below. LOC: ~+15.

### Phase 3.3: GREEN - Forgot/Reset modals

- [ ] 3.3.1 Restyle `resources/js/modules/auth/ForgotPasswordModal.vue` (~232 -> ~150 LOC): token-driven, no `linear-gradient`, no `reset_token` field; success state shows email + a "Ya tienes el codigo? Restablecer contrasena" link that opens `ResetPasswordModal.vue` on click (separately, not auto). Verify: `pnpm build` exits 0. LOC: ~-80.
- [ ] 3.3.2 Ensure `ResetPasswordModal.vue` no longer auto-opens from Forgot success; user-driven only. Keep focus trap + Esc dismissal. Verify: `pnpm build` exits 0; manual focus-trap walk-through. LOC: ~+5.
- [ ] 3.3.3 Verify test 3.1.6 passes (`reset_token` not in modal source). LOC: 0.

### Phase 3.4: GREEN - Dashboard rebuild

- [ ] 3.4.1 Rebuild `resources/js/modules/dashboard/DashboardPage.vue` (~750 -> ~480 LOC, net -270): five stat cards on `<UiCard variant="glass">` (opaque, per 2.3.2) using grid `grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4`; five quick actions same grid; today's appointments list capped at 3 (`todayAppointments.slice(0, 3)`). Delete the inline `<style scoped>` block. Replace all `gradient-primary / gradient-success / gradient-warning / gradient-info / glass-effect` classes with flat `bg-cream-100` tinted backgrounds. Collapse `cashStatusClass / cashStatusIconClass / cashStatusText / cashStatusIconColor` quadruple into one `<UiStatusPill :status="cashStatusPillStatus" :show-dot="true" />`. Verify: tests 3.1.2 + 3.1.3 pass; `pnpm build` exits 0; manual visual check shows verified labels (Citas Hoy / Pacientes / Profesionales / Total Citas / Estado de Caja; quick actions Pacientes / Nueva Cita / Profesionales / Ambientes / Reportes). LOC: ~-270.
- [ ] 3.4.2 Wire stat-card `useSpring({ response: 0.35, damping: 1.0 })` for entrance only; on WS event `dashboard.today-updated` debounced 300 ms, call `spring.set(newValue, { velocity: 0 })` (tween, not entrance). Under reduced-motion: opacity cross-fade only. Verify: Playwright checkpoint 4 + 5 below. LOC: ~+25.

### Phase 3.5: GREEN - 404 page

- [ ] 3.5.1 Rebuild `resources/js/modules/errors/NotFoundPage.vue` (~71 -> ~80 LOC): one `<h1>` ("Pagina no encontrada"), one paragraph, one `<img src="/images/pexels/errors-404/4439425_page-404_p3.jpg" alt="Ilustracion 404">`, one link to `/login` ("Volver al inicio"). Headline entrance: `useSpring({ response: 0.35, damping: 1.0 })`, interruptible on navigation. No `<meta http-equiv="refresh">` and no `router.replace`. Verify: test 3.1.4 passes; `pnpm build` exits 0. LOC: ~+10.

### Phase 3.6: GREEN - AppLayout chrome

- [ ] 3.6.1 Edit `resources/js/components/layout/AppLayout.vue` (~969 -> ~960 LOC, net ~-10): sidebar and topbar wrap with `.surface-glass` (chrome-only class from generated CSS). Add `min-h-[100dvh]` on the layout root, replace any `h-screen` / `height: 100vh`. Add `@media (prefers-reduced-transparency: reduce)` block on chrome wrappers (the `.surface-glass` class already includes the fallback; ensure no other class on the chrome overrides it). Verify: tests 3.1.5 passes; `pnpm build` exits 0. LOC: ~-10.
- [ ] 3.6.2 Wire sidebar collapse animation with `useSpring2D` for X slide. Wire mobile sheet on `Sheet` primitive with `position="left"`, `response 0.3, damping 0.8` (momentum). Focus trap already present in `Sheet.vue`. Verify: `pnpm build` exits 0; Playwright checkpoint 8 below. LOC: ~+30.

### Phase 3.7: PR3 regression gate - Playwright 7-step recipe

Pre-check: `php artisan serve` (`:8000`) + `pnpm dev` (`:5173`); log in with username `adm1n` / password `password123` (the field is `username`, not email).

- [ ] 3.7.1 Checkpoint 1 - Login default: `playwright-cli open http://localhost:8000/login --filename=login-light.png`. Visual: login card center, hero image right, terracotta primary button visible. Exit code 0.
- [ ] 3.7.2 Checkpoint 2 - Login reduced motion: `playwright-cli open http://localhost:8000/login --filename=login-reduced-motion.png --emulate-media='{"reducedMotion":"reduce"}'`. Visual: no floating shapes; entrance instant. Exit 0.
- [ ] 3.7.3 Checkpoint 3 - Login reduced transparency: same command with `--emulate-media='{"reducedTransparency":"reduce"}'` -> `login-reduced-transparency.png`. Visual: chrome solid `cream-100`, no blur. Exit 0.
- [ ] 3.7.4 Checkpoint 4 - Login flow: `playwright-cli fill e1 "adm1n"` + `fill e2 "password123"` + `click e3` (use `snapshot` first to resolve element ids). Expect URL `/dashboard`. Screenshot `after-login.png`. Exit 0.
- [ ] 3.7.5 Checkpoint 5 - Dashboard default: `playwright-cli open http://localhost:8000/dashboard --filename=dashboard.png`. Visual: 5 stat cards, 5 quick actions, 3 appointment rows visible (or fewer if permission-gated). Exit 0.
- [ ] 3.7.6 Checkpoint 6 - 404: `playwright-cli open http://localhost:8000/this-route-does-not-exist --filename=not-found.png`. Visual: 404 image, headline, back-to-login button visible. Exit 0.
- [ ] 3.7.7 Checkpoint 7 - High contrast: `--emulate-media='{"contrast":"more"}'` on dashboard -> `dashboard-high-contrast.png`. Visual: text `ink-900`, borders `ink-700`. Exit 0.
- [ ] 3.7.8 Checkpoint 8 - Mobile sheet: viewport `375x812`, `--emulate-media='{"reducedTransparency":"reduce"}'` on `/dashboard`, tap hamburger, screenshot `mobile-sheet.png`. Visual: sheet slides from X axis only (no Y motion), focus trapped. Exit 0.

### Phase 3.8: PR3 final regression

- [ ] 3.8.1 Run `vendor/bin/phpunit` (full suite) -> exit 0. Run `pnpm build` -> exit 0. Run `pnpm lint:check` -> exit 0. Run `php artisan test` -> exit 0.
- [ ] 3.8.2 Run all 8 design grep anti-requirements as a single `tests/Unit/DesignSystem/DesignAntiRequirementsTest.php` test method (or extend the existing suite): no `prefers-color-scheme: dark`; no `useTheme|ThemeSelector|design-system.js|MobileNavigation` outside tests; no `Fraunces` / `Instrument_Serif`; no `fonts.googleapis` / `fonts.gstatic`; no `linear-gradient` in `DashboardPage.vue`; no `<style scoped>` in `DashboardPage.vue`; no `h-screen` in `AppLayout.vue`; no `reset_token` in `ResetPasswordModal.vue`; `.surface-glass` emitted exactly once in `tokens.generated.css`; no `backdrop-filter` in `Card.vue`. Exit 0.
- [ ] 3.8.3 Verify byte budget: `git diff --stat <pr2-sha>..HEAD -- 'public/images/**'` shows no single file > 5120 KB; total added <= 110 KB (still 56 + 16 + optional 2000 max). Exit 0.
- [ ] 3.8.4 Commit `tests/Visual/baselines/{login,dashboard,not-found}-pr3.png` as the new visual baselines (replacing pre-PR2 ones). Exit 0.

PR3 changed-line estimate: **~555 LOC** (LoginPage -250 + Dashboard -270 + small positives across the rest, plus asset commits). Budget cap 550 -> 101% used. PR3 is **over cap by ~5 LOC**. Flag this in the apply phase.

If PR3 exceeds 550:
- Move the `useSpring2D` sidebar collapse wiring (3.6.2, +30 LOC) into a follow-up PR (`feat/ui-redesign-sidebar-collapse-spring`).
- OR move `cashStatus*` collapse to `UiStatusPill` (3.4.1, ~-10 LOC net) into a follow-up.
- Decision must be made before apply.

---

## Cross-slice anti-requirement index (one row per grep, one row per PR)

| Anti-requirement | Static check | PR1 | PR2 | PR3 |
|---|---|---|---|---|
| No `prefers-color-scheme: dark` | `grep -rn "prefers-color-scheme: dark" resources/` | 1.1.2 | 2.2.5 (kept) | 3.8.2 (kept) |
| No `useTheme` / `ThemeSelector` / `design-system.js` / `MobileNavigation` | `grep -rn "useTheme\\|ThemeSelector\\|design-system.js\\|MobileNavigation" resources/` | 1.1.1 | - | - |
| No `Fraunces` / `Instrument_Serif` | `grep -rn "Fraunces\\|Instrument_Serif" resources/` | - | 2.2.5 | 3.8.2 |
| No Google Fonts CDN | `grep -rn "fonts.googleapis\\|fonts.gstatic" resources/` | - | 2.1.6 | 3.8.2 |
| No global `* { transition: ... }` | design Testing Strategy grep #1 | 1.3.1 | 2.1.11 | 3.8.2 |
| No `info` ramp + no `*-dark` keys | unit test on `tokens.js` | - | 2.1.2 | - |
| Single `:root` block in generated CSS | `grep -c "^:root" resources/css/tokens.generated.css` | - | 2.1.5 | 3.8.2 |
| `.surface-glass` emitted exactly once | `grep -c "^\.surface-glass" resources/css/tokens.generated.css` | - | 2.1.8 | 3.8.2 |
| No `backdrop-filter` in `Card.vue` | `grep -n "backdrop-filter" resources/js/components/ui/Card.vue` | - | 2.1.9 | 3.8.2 |
| No `backdrop-filter` in other primitives | `grep -rn "backdrop-filter" resources/js/components/ui/ | grep -v Card.vue` | - | 2.1.10 | 3.8.2 |
| No `linear-gradient` in Dashboard | `grep -n "linear-gradient\\|bg-gradient" resources/js/modules/dashboard/DashboardPage.vue` | - | - | 3.1.3 |
| No `<style scoped>` in Dashboard | source-inspection | - | - | 3.1.3 |
| No `h-screen` in AppLayout | `grep -n "h-screen\\|height: 100vh" resources/js/components/layout/AppLayout.vue` | - | - | 3.1.5 |
| No `reset_token` in Reset modal | `grep -rn "reset_token" resources/js/modules/auth/ResetPasswordModal.vue` | - | - | 3.1.6 |
| Only one mp4 tracked (optional) | `git ls-files | grep _v1.mp4` | - | - | 3.1.8 |
| No asset > 5 MB | `git diff --stat` size check | - | - | 3.8.3 |
| No photography in stat cards / appointment rows | DOM check via Playwright | - | - | 3.7.5 |
| No `autoplay` on optional video | DOM check via Playwright | - | - | 3.7.1 (when committed) |

---

## Test summary

| Layer | PR1 | PR2 | PR3 |
|---|---|---|---|
| PHP unit (`TokensModuleTest`, `GeneratedTokensCssTest`, `LoginRenderTest`, design anti-requirements) | 4 new | 12 new | 6 new + 1 aggregator |
| JS / Vue build (`pnpm build`, `pnpm lint:check`) | yes | yes | yes |
| Laravel feature (`php artisan test --filter=AuthTest`) | yes | yes | yes |
| Visual baseline (PNG, <= 200 KB each) | - | 3 pre-PR2 | 8 PR3 + 3 final |
| Playwright 7-step recipe + checkpoint 8 (mobile) | - | - | yes |
| Reduced-motion / reduced-transparency / high-contrast forced media queries | - | - | yes (checkpoints 2, 3, 7) |

---

## Notes / handoffs to `sdd-apply`

- `resources/js/components/MobileNavigation.vue` actually lives at `resources/js/components/MobileNavigation.vue` (NOT `layout/MobileNavigation.vue` as the proposal says). Apply uses the actual path.
- The optional login mp4 commit (3.1.8) is gated on `ffmpeg` availability; if absent or output > 2 MB, skip and document in the PR description.
- The Newsreader woff2 download (2.2.6) is gated on network access in the apply environment; without it, ship a placeholder named identically and flag in the PR description.
- PR2 is at ~99% of its 600-LOC cap; PR3 is at ~101% of its 550-LOC cap (over by ~5 LOC). Apply should consider deferring either the sidebar `useSpring2D` wiring (3.6.2) or the `cashStatus*` collapse (3.4.1) to a follow-up PR before opening the PR3 branch.
- The PR3 branch must be cut from the merged PR2 branch, not from `main`, per `stacked-to-main`.
