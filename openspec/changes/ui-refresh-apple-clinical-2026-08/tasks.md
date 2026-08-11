# Tasks: ui-refresh-apple-clinical-2026-08

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~370 (PR1 ~240 + PR2 ~130) additions + deletions |
| 400-line budget risk | Low |
| Chained PRs recommended | Yes (2 PRs) |
| Suggested split | PR1 (tokens + primitives + chrome + font deletion) -> PR2 (Login + Dashboard + 404 + visual baselines) |
| Delivery strategy | ask-on-risk (already exercised -> 2 chained PRs) |
| Chain strategy | stacked-to-main |

```
Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low
```

### Branch topology (stacked-to-main)

- PR1 branch: `feat/ui-refresh-apple-clinical-2026-08`    <- `feat/ui-redesign-apple-claude-2026-08-p3`
- PR2 branch: `feat/ui-refresh-apple-clinical-2026-08-p2` <- PR1 branch

### Per-slice work units

| Unit | Goal | PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|----|----------------------|-----------------|--------------------|
| 1 | Token swap + primitives + chrome + Newsreader deletion; no page-template change | PR1 | `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` + `pnpm tokens:build` + `pnpm build` | `php artisan serve` + `pnpm dev`; 3 verticals (Login + Dashboard + 404) still render cream + terracotta + Newsreader because page templates land in PR2 | `git revert <sha>` restores `tokens.js` + `tokens.generated.css` + `tailwind.config.js` + primitives + composable + font binary |
| 2 | Login + Dashboard + 404 visual revalue + visual baseline refresh | PR2 | `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` + Playwright 7-step recipe | `php artisan serve` + `pnpm dev`; `adm1n` / `password123`; Playwright checkpoints 1-7 | `git revert <sha>` (PR1 stays merged; tokens stay iOS; 3 pages revert to cream + terracotta + Newsreader serif headlines) |

### Environment / tool prerequisites (apply phase must verify)

- `node` >= 18 available (used by `scripts/build-tokens-css.mjs`).
- `pnpm` available for `pnpm tokens:build` + `pnpm build`.
- Playwright CLI per `C:/Users/chomb/.claude/skills/playwright-cli/SKILL.md`.
- **No network access required** (Newsreader woff2 is deleted, not downloaded).

---

## PR1: Tokens + primitives + chrome + Newsreader cleanup (target: `feat/ui-refresh-apple-clinical-2026-08`)

Goal: replace the previous `terracotta`/`cream`/`ink`/`clinicalTeal` ramp with iOS 13+ system colors. Revalue primitives. Swap chrome rgba from cream-on-cream to white-on-white. Delete Newsreader font + composable. The 3 verticals (Login + Dashboard + 404) still render with cream surfaces, terracotta accents, and Newsreader serif headlines because the page-template changes land in PR2.

PR1 running-total budget cap: **<= 400 LOC changed** (current estimate: ~240).

### Phase 1.1: RED tests first (TDD) on `tokens.js` + generated CSS

- [ ] 1.1.1 Add `tokens_module_exposes_ios_system_color_ramps` to `tests/Unit/DesignSystem/TokensModuleTest.php`: asserts `tokens.colors` contains `systemBlue`, `systemRed`, `systemOrange`, `systemYellow`, `systemGreen`, `systemIndigo`, `systemPurple`, `systemPink`, `systemGray` each at steps `{50, 100, 500, 600, 700}`; plus `background` (`systemBackground`/`secondaryBackground`/`tertiaryBackground`/`groupedBackground`), `label` (`label`/`secondaryLabel`/`tertiaryLabel`/`quaternaryLabel`), `separator.separator`, `fill` (`systemFill`/`secondarySystemFill`/`tertiarySystemFill`). Verify: `vendor/bin/phpunit --filter tokens_module_exposes_ios_system_color_ramps` exits 0 only after 1.2.1. Refs: spec scenarios "systemBlue hex" + "background + label hex".
- [ ] 1.1.2 Add `tokens_module_hex_literals_match_ios_palette`: literal hex checks: `systemBlue['500'] === '#007AFF'`, `systemRed['500'] === '#FF3B30'`, `systemOrange['500'] === '#FF9500'`, `systemYellow['500'] === '#FFCC00'`, `systemGreen['500'] === '#34C759'`, `systemIndigo['500'] === '#5856D6'`, `systemPurple['500'] === '#AF52DE'`, `systemPink['500'] === '#FF2D55'`, `background.systemBackground === '#FFFFFF'`, `label.label === '#000000'`, `separator.separator === '#C6C6C8'`. Refs: spec scenarios "systemBlue hex" + "background + label hex".
- [ ] 1.1.3 Add `tokens_module_radius_ios_and_modal`: asserts `radius.ios === '10px'` AND `radius.modal === '14px'` AND `radius.sm === '4px'` AND `radius.full === '9999px'` AND `radius.lg/2xl/3xl` do NOT exist. Refs: spec scenario "Radius literals".
- [ ] 1.1.4 Add `tokens_module_font_family_sans_only`: asserts `fontFamily.serif` is NOT a key; `fontFamily.sans` is a non-empty array starting with `-apple-system`. Refs: spec scenario "fontFamily.serif absent".
- [ ] 1.1.5 Add `tokens_module_letter_spacing_table`: asserts per-step `letterSpacing`: `xs/sm/base/lg = '0'`, `xl = '-0.01em'`, `2xl = '-0.015em'`, `3xl = '-0.02em'`, `4xl/display/hero = '-0.022em'`. Asserts no `fontSize` key sets `font-optical-sizing`. Refs: spec scenario "Letter spacing tightens with size".
- [ ] 1.1.6 Add `tokens_module_no_newsreader_no_use_fonts_loaded`: shells `git ls-files public/fonts/newsreader-latin.woff2` (asserts non-zero exit) and `git ls-files resources/js/composables/useFontsLoaded.js` (asserts non-zero exit); asserts `grep -rn "Newsreader" resources/` returns 0 matches; `grep -rn "useFontsLoaded" resources/` returns 0 matches; `grep -rn "var(--font-serif)" resources/` returns 0 matches. Refs: spec scenario "Newsreader absence".
- [ ] 1.1.7 Add `tokens_module_no_cream_terracotta_clinical_teal_literals`: shells `grep -rEn "#FAF9F7|#F2EFE9|#E8E3D8|#C96442|#B05432|#2C7A7B" resources/ | grep -v "tokens.js\|tokens.generated.css"` and asserts 0 matches. Refs: spec scenario "No cream/terracotta/clinicalTeal literals".
- [ ] 1.1.8 Add `tokens_module_no_dark_mode_blocks`: shells `grep -rn "prefers-color-scheme: dark" resources/` and asserts 0 matches. Refs: spec scenario "No dark block".
- [ ] 1.1.9 Add `tokens_module_deprecated_aliases_resolve`: asserts `tokens.colors.cream['50'] === '#F2F2F7'` (systemGray-50), `tokens.colors.terracotta['500'] === '#007AFF'` (systemBlue-500), `tokens.colors.clinicalTeal['50'] === '#E5F1FF'` (systemBlue-50), `tokens.colors.info['500'] === '#007AFF'` (systemBlue-500). Refs: spec scenario "Alias regression guard".
- [ ] 1.1.10 Add `generated_css_has_no_font_face_no_font_serif`: parses `resources/css/tokens.generated.css` (post-regen), asserts no `@font-face` block AND no `--font-serif` declaration AND no reference to `newsreader`. Refs: design Decision 6.
- [ ] 1.1.11 Add `generated_css_surface_glass_uses_white_on_white_and_pure_black_shadow`: parses `tokens.generated.css`, asserts `.surface-glass` rgba matches `rgb(255 255 255 / ...)`; asserts `.surface-glass` shadow uses `rgb(0 0 0 / ...)` (not `rgb(20 17 14 / ...)`); asserts the global shadow ramp uses `rgba(0, 0, 0, ...)`. Refs: spec scenarios "surface-glass rgba" + design Decision 5.

### Phase 1.2: GREEN - token rewrite + generator + alias preservation

- [ ] 1.2.1 Rewrite `resources/js/design-system/tokens.js`: replace `terracotta`/`cream`/`ink`/`clinicalTeal` ramps with iOS 13+ system colors (`systemBlue`/`systemRed`/`systemOrange`/`systemYellow`/`systemGreen`/`systemIndigo`/`systemPurple`/`systemPink`/`systemGray` at steps 50/100/500/600/700); add `background` ramp (`systemBackground`/`secondaryBackground`/`tertiaryBackground`/`groupedBackground`); add `label` ramp (`label`/`secondaryLabel`/`tertiaryLabel`/`quaternaryLabel`); add `separator` + `fill` ramps. Preserve deprecated alias keys: `cream: {50:'#F2F2F7', 100:'#E5E5EA', 200:'#D1D1D6'}` (alias of systemGray), `terracotta: {500:'#007AFF', 600:'#0062CC'}` (alias of systemBlue), `clinicalTeal: {50:'#E5F1FF', 500:'#007AFF', 600:'#0062CC'}` (alias of systemBlue), `info: {500:'#007AFF'}` (re-keyed to systemBlue). Verify: tests 1.1.1, 1.1.2, 1.1.7, 1.1.9 pass. LOC: ~+220 / -160.
- [ ] 1.2.2 In `tokens.js`: drop `fontFamily.serif`; keep `fontFamily.sans` as `['-apple-system','BlinkMacSystemFont','Segoe UI','Roboto','Helvetica Neue','Arial','sans-serif']`. Tune `fontSize` per-step `letterSpacing`: `xs/sm/base/lg = '0'`, `xl = '-0.01em'`, `2xl = '-0.015em'`, `3xl = '-0.02em'`, `4xl/display/hero = '-0.022em'`. Remove any `font-optical-sizing` declaration. Verify: tests 1.1.4, 1.1.5 pass. LOC: ~+10 / -20.
- [ ] 1.2.3 In `tokens.js`: replace `radius.lg/2xl/3xl` with `radius.ios = '10px'` (cards, buttons, status chips) + `radius.modal = '14px'` (Modal, Sheet, bottom pickers). Keep `radius.sm = '4px'` (chips), `radius.md = '8px'` (inputs), `radius.full = '9999px'` (pills), `radius.none = '0'`. Verify: test 1.1.3 passes. LOC: ~+5 / -10.
- [ ] 1.2.4 Edit `scripts/build-tokens-css.mjs`: drop `@font-face` block emit; drop `--font-serif` declaration; emit iOS semantic aliases (`--color-accent: var(--color-systemBlue-500)`, `--color-text-primary: var(--color-label)`, `--color-background: var(--color-systemBackground)`, `--color-border: var(--color-separator)`); swap shadow rgba from warm-black `rgba(20, 17, 14, ...)` to pure `rgba(0, 0, 0, ...)` across the global shadow ramp; emit `.surface-glass` rgba with `rgb(255 255 255 / 0.78)` (white-on-white) and `rgb(0 0 0 / 0.06)` border + `rgb(0 0 0 / 0.10)` outer shadow. Verify: tests 1.1.10, 1.1.11 pass. LOC: ~+40 / -20.
- [ ] 1.2.5 Edit `tailwind.config.js`: re-source `theme.extend.colors` from the new `tokens.js` exports (delete `terracotta`/`cream`/`ink`/`clinicalTeal`/`info` ramp entries that conflict with the new shape; preserve deprecated alias keys as mirror entries so Tailwind utilities `bg-cream-50`, `bg-terracotta-500`, `bg-clinicalTeal-50`, `bg-info-500` keep resolving). Drop `rounded-lg/2xl/3xl` utilities; add `rounded-ios` (=10px) and `rounded-modal` (=14px) utilities. Verify: `pnpm build` exits 0; `grep -rn "rounded-lg\|rounded-2xl\|rounded-3xl" resources/js/components/ui/` returns 0 (primitives use the new tokens). LOC: ~+10 / -15.
- [ ] 1.2.6 Run `pnpm tokens:build` to regenerate `resources/css/tokens.generated.css` (committed artifact, not gitignored). Verify: tests 1.1.10, 1.1.11 pass; `git diff --stat tokens.generated.css` shows +300 / -200 LOC. Generated CSS is excluded from the 400-LOC authored-LOC budget but counted toward snapshot identity. LOC: ~+300 / -200 (generated).

### Phase 1.3: GREEN - font binary + composable deletion

- [ ] 1.3.1 Delete `public/fonts/newsreader-latin.woff2`. Verify: `git ls-files public/fonts/newsreader-latin.woff2` exits non-zero; test 1.1.6 passes. LOC: -bin.
- [ ] 1.3.2 Delete `resources/js/composables/useFontsLoaded.js`. Verify: `git ls-files resources/js/composables/useFontsLoaded.js` exits non-zero; `grep -rn "useFontsLoaded" resources/` returns 0; test 1.1.6 passes. LOC: -40.

### Phase 1.4: GREEN - primitive restyle (16 components, class-name revalue only)

- [ ] 1.4.1 Edit `resources/js/components/ui/Button.vue`: primary variant `bg-terracotta-500` -> `bg-systemBlue-500`; focus ring `ring-terracotta-500` -> `ring-systemBlue-500`; `rounded-lg` -> `rounded-ios`. Preserve prop surface (`variant`, `size`, `loading`, `disabled`, `as`, `to`). Verify: `pnpm build` exits 0. LOC: ~+3 / -3.
- [ ] 1.4.2 Edit `resources/js/components/ui/Card.vue`: surface `bg-cream-100` -> `bg-systemBackground`; border `border-ink-200` -> `border-separator`; radius `rounded-xl` -> `rounded-ios`; shadow `rgba(31,27,23,...)` -> `rgba(0,0,0,...)`. Keep `variant="glass"` opaque (no `backdrop-filter`, per previous design). Verify: `pnpm build` exits 0. LOC: ~+6 / -4.
- [ ] 1.4.3 Edit `resources/js/components/ui/Modal.vue`: surface `bg-cream-100` -> `bg-systemBackground`; corners `rounded-2xl` -> `rounded-modal` (14px). LOC: ~+3 / -2.
- [ ] 1.4.4 Edit `resources/js/components/ui/Sheet.vue`: surface `bg-cream-100` -> `bg-systemBackground`; corners `rounded-2xl` -> `rounded-modal` (14px). LOC: ~+3 / -2.
- [ ] 1.4.5 Edit `resources/js/components/ui/Input.vue`: surface `bg-cream-50` -> `bg-secondaryBackground`; border `border-ink-200` -> `border-separator`; focus ring `ring-terracotta-500` -> `ring-systemBlue-500`. LOC: ~+4 / -3.
- [ ] 1.4.6 Edit `resources/js/components/ui/Badge.vue`: `variant="info"` re-keyed to `systemBlue` (filled iOS pattern: `bg-systemBlue-100 text-systemBlue-700`); other variants revalue to `systemGreen`/`systemRed`/`systemOrange`/`systemGray`. LOC: ~+4 / -3.
- [ ] 1.4.7 Edit `resources/js/components/ui/StatusPill.vue`: filled iOS pattern (`bg-system{Color}-100 text-system{Color}-600`); `rounded-lg` -> `rounded-ios` (10px). LOC: ~+3 / -2.
- [ ] 1.4.8 Edit `resources/js/components/ui/Toast.vue`: surface `bg-cream-100` -> `bg-systemBackground`; border `border-ink-200` -> `border-separator`. LOC: ~+3 / -2.
- [ ] 1.4.9 Edit `resources/js/components/ui/Skeleton.vue`: derive from `bg-systemGray-100`. LOC: ~+1 / -1.
- [ ] 1.4.10 Edit `resources/js/components/ui/LoadingSpinner.vue`: `--spinner-color` -> `systemBlue-500`. LOC: ~+1 / -1.
- [ ] 1.4.11 Edit `resources/js/components/ui/EmptyState.vue`: surface `bg-cream-100` -> `bg-systemBackground`. LOC: ~+1 / -1.
- [ ] 1.4.12 Edit `resources/js/components/ui/Avatar.vue`: token swap only (already neutral). LOC: ~+1 / -1.
- [ ] 1.4.13 Edit `resources/js/components/ui/Breadcrumbs.vue`: separator `text-ink-500` -> `text-systemGray-500`. LOC: ~+1 / -1.
- [ ] 1.4.14 Edit `resources/js/components/ui/Tabs.vue`: active indicator `bg-terracotta-500` -> `bg-systemBlue-500`. LOC: ~+2 / -2.
- [ ] 1.4.15 Edit `resources/js/components/ui/ConfirmDialog.vue`: surface `bg-cream-100` -> `bg-systemBackground`. LOC: ~+1 / -1.
- [ ] 1.4.16 Edit `resources/js/components/ui/NotificationToast.vue`: surface `bg-cream-100` -> `bg-systemBackground`. LOC: ~+1 / -1.

### Phase 1.5: GREEN - chrome rgba + AppLayout token swap

- [ ] 1.5.1 (covered by 1.2.4 generator emit) `.surface-glass` rgba uses `rgb(255 255 255 / 0.78)` background; border `rgb(0 0 0 / 0.06)`; shadow `rgb(0 0 0 / 0.10)`. `@media (prefers-reduced-transparency: reduce)` collapses `.surface-glass` to `var(--color-systemBackground)` (solid white) with `backdrop-filter: none` and shadow removed. Verify: test 1.1.11 passes.
- [ ] 1.5.2 Edit `resources/js/components/layout/AppLayout.vue`: page bg `bg-cream-50` -> `bg-systemBackground`; nav text `text-ink-700` -> `text-label`; WS indicator chips `bg-success-100 text-success-700` -> `bg-systemGray-100 text-systemGray-600`. `.surface-glass` consumption unchanged (CSS handles the rgba swap). Verify: `pnpm build` exits 0. LOC: ~+8 / -6.
- [ ] 1.5.3 Edit `resources/js/components/layout/PageHeader.vue` + `FloatingActionButton.vue`: token swap only. Verify: `pnpm build` exits 0. LOC: ~+3 / -3.

### Phase 1.6: PR1 regression gate

- [ ] 1.6.1 Run `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` -> exit 0.
- [ ] 1.6.2 Run `pnpm tokens:build` -> exit 0; `git diff tokens.generated.css` is non-empty (regen confirmed).
- [ ] 1.6.3 Run `pnpm build` -> exit 0.
- [ ] 1.6.4 Run `php artisan serve` (`:8000`) + `pnpm dev` (`:5173`); confirm `GET /login`, `GET /dashboard` (after `adm1n`/`password123` login), `GET /404` still render cream + terracotta + Newsreader serif headlines (page-template changes are PR2 scope; only primitives and chrome changed). Manual visual diff: card surfaces look cleaner (no `cream-on-cream` glass) and no font-flash.
- [ ] 1.6.5 Confirm the 17 un-migrated modules still render correctly via deprecated alias keys: `bg-cream-50` resolves to `bg-systemGray-50`, `bg-terracotta-500` resolves to `bg-systemBlue-500`, `bg-clinicalTeal-50` resolves to `bg-systemBlue-50`, `bg-info-500` resolves to `bg-systemBlue-500`. Manual: navigate to `/pacientes`, `/citas`, `/profesionales` (or any 1-2 un-migrated routes) and confirm colored badges still render.

PR1 changed-line estimate: **~240 LOC**. Budget cap 400 -> 60% used. Comfortable headroom.

PR1 risks that may push over cap:
- Generator script complexity (1.2.4): if the `.surface-glass` block + media queries + semantic aliases exceed 40 LOC of generator additions, split media-query blocks into a sibling generator-emit point.
- Primitive restyle (1.4): 16 components is the largest single phase; if any component reveals unexpected dependencies on `cream-200` or `terracotta-600` that require scoped CSS, split into a follow-up PR.

---

## PR2: Login + Dashboard + 404 + visual baselines (target: `feat/ui-refresh-apple-clinical-2026-08-p2` from PR1 branch)

Goal: ship the iOS clinical read on the three vertical exemplars. Drop Newsreader-serif headlines. Re-skin Login, Dashboard, and 404 on the new primitive layer. Refresh visual baselines.

PR2 running-total budget cap: **<= 400 LOC changed** (current estimate: ~130).

### Phase 2.1: RED tests + visual baseline prep

- [ ] 2.1.1 Add `login_page_drops_var_font_serif`: shells `grep -n "var(--font-serif)" resources/js/modules/auth/LoginPage.vue` and asserts 0 matches. Verify: `vendor/bin/phpunit --filter login_page_drops_var_font_serif` exits 0 only after 2.2.1. Refs: spec scenario "Login card chrome".
- [ ] 2.1.2 Add `dashboard_cash_badge_color_matches_state` to `tests/Unit/Dashboard/DashboardStatusTest.php`: static-grep `resources/js/modules/dashboard/DashboardPage.vue` and assert the cash-status badge class binding resolves to `bg-systemGreen-100 text-systemGreen-600` (Abierta), `bg-systemRed-100 text-systemRed-600` (Cerrada), `bg-systemGray-100 text-systemGray-600` (Sin sesion). Refs: spec scenario "Cash status badge color matches state".
- [ ] 2.1.3 Add `dashboard_stat_number_uses_text_label`: shells `grep -n "text-terracotta-600\|text-terracotta-500" resources/js/modules/dashboard/DashboardPage.vue` and asserts 0 matches on a stat-number binding. Refs: spec scenario "Stat number not colored".
- [ ] 2.1.4 Add `dashboard_no_linear_gradient`: shells `grep -n "linear-gradient\|bg-gradient" resources/js/modules/dashboard/DashboardPage.vue` and asserts 0 matches. Refs: spec anti-requirement.
- [ ] 2.1.5 Add `not_found_page_drops_var_font_serif`: shells `grep -n "var(--font-serif)" resources/js/modules/errors/NotFoundPage.vue` and asserts 0 matches. Refs: spec scenario "404 serif headline gone".
- [ ] 2.1.6 Capture pre-PR2 visual baselines in `tests/Visual/baselines/{login,dashboard,not-found}-pre-pr2.png` (PNG, <= 200 KB each, byte-stable). Verify: files exist; each <= 204800 bytes.

### Phase 2.2: GREEN - Login page

- [ ] 2.2.1 Edit `resources/js/modules/auth/LoginPage.vue`: drop `font-family: var(--font-serif)` on `.welcome-headline`, `.hero-caption-title`, and the `prefers-contrast` block (3 call sites total). Card surface `bg-cream-100` -> `bg-systemBackground`; corners `rounded-xl` -> `rounded-ios` (10px); border `border-ink-200` -> `border-separator`. Icon ring `var(--color-terracotta-500)` -> `var(--color-systemBlue-500)`. Primary button `bg-terracotta-500` -> `bg-systemBlue-500`. Verify: tests 2.1.1 passes; `pnpm build` exits 0; Playwright checkpoint 1 captures a white card + systemBlue button + system-font headline. LOC: ~+20 / -25.
- [ ] 2.2.2 Edit `resources/js/modules/auth/ForgotPasswordModal.vue` + `ResetPasswordModal.vue`: inherit primitive changes from PR1 (no `var(--font-serif)` references exist in these files; just token class swaps where applicable). Verify: `pnpm build` exits 0. LOC: ~+3 / -3 (each).

### Phase 2.3: GREEN - Dashboard

- [ ] 2.3.1 Edit `resources/js/modules/dashboard/DashboardPage.vue`: icon chip backgrounds re-keyed to iOS filled pattern (`bg-systemGreen-100 text-systemGreen-600`, `bg-systemOrange-100 text-systemOrange-600`, `bg-systemGray-100 text-systemGray-600`, `bg-systemBlue-100 text-systemBlue-600`, `bg-systemRed-100 text-systemRed-600`); chip dimensions 32 px rounded-square (10 px radius). Cash status badge: "Abierta" -> `bg-systemGreen-100 text-systemGreen-600`, "Cerrada" -> `bg-systemRed-100 text-systemRed-600`, "Sin sesion" -> `bg-systemGray-100 text-systemGray-600`. "Citas Hoy" stat number `text-terracotta-600` -> `text-label`. Card border `border-ink-200` -> `border-separator`. Verify: tests 2.1.2, 2.1.3, 2.1.4 pass; `pnpm build` exits 0; Playwright checkpoint 5 captures the iOS-clinical dashboard. LOC: ~+35 / -50.
- [ ] 2.3.2 The 300 ms WS debounce at `DashboardPage.vue:882` is preserved (load-bearing for `motion does not fight motion`). No data-flow change. Verify: `grep -n "debounce" resources/js/modules/dashboard/DashboardPage.vue` returns at least 1 match (sanity). LOC: 0.

### Phase 2.4: GREEN - 404 page

- [ ] 2.4.1 Edit `resources/js/modules/errors/NotFoundPage.vue`: drop `font-family: var(--font-serif)` on `.not-found-headline` (1 call site). Image border `border-ink-200` -> `border-separator`. Shadow `rgba(31, 27, 23, ...)` -> `rgba(0, 0, 0, ...)` (iOS lighter pure-black). Verify: tests 2.1.5 passes; `pnpm build` exits 0; Playwright checkpoint 6 captures the system-font headline + hairline border. LOC: ~+6 / -8.

### Phase 2.5: PR2 regression gate - Playwright 7-step recipe

Pre-check: `php artisan serve` (`:8000`) + `pnpm dev` (`:5173`); log in with username `adm1n` / password `password123`.

- [ ] 2.5.1 Checkpoint 1 - Login default: `playwright-cli open http://localhost:8000/login --filename=login-light.png`. Visual: white card, systemBlue primary button, system-font headline (NOT serif). Exit 0.
- [ ] 2.5.2 Checkpoint 2 - Login reduced motion: `playwright-cli open http://localhost:8000/login --filename=login-reduced-motion.png --emulate-media='{"reducedMotion":"reduce"}'`. Visual: no entrance translation; opacity cross-fade only. Exit 0.
- [ ] 2.5.3 Checkpoint 3 - Login reduced transparency: `--emulate-media='{"reducedTransparency":"reduce"}'` -> `login-reduced-transparency.png`. Visual: chrome solid white (`bg-systemBackground`), no `backdrop-filter`. Exit 0.
- [ ] 2.5.4 Checkpoint 4 - Login flow: `playwright-cli fill e1 "adm1n"` + `fill e2 "password123"` + `click e3` (use `snapshot` first to resolve element ids). Expect URL `/dashboard`. Screenshot `after-login.png`. Exit 0.
- [ ] 2.5.5 Checkpoint 5 - Dashboard default: `playwright-cli open http://localhost:8000/dashboard --filename=dashboard.png`. Visual: 5 stat cards, 5 quick actions, status icon chips in iOS filled pattern, "Citas Hoy" big number is pure black (`text-label`), cash status badge is iOS filled pattern. Exit 0.
- [ ] 2.5.6 Checkpoint 6 - 404: `playwright-cli open http://localhost:8000/this-route-does-not-exist --filename=not-found.png`. Visual: system-font headline (NOT serif), image with hairline `border-separator`. Exit 0.
- [ ] 2.5.7 Checkpoint 7 - High contrast: `--emulate-media='{"contrast":"more"}'` on dashboard -> `dashboard-high-contrast.png`. Visual: text pure black (`#000000`), borders `label #3C3C43`. Exit 0.

### Phase 2.6: PR2 final regression + visual baseline refresh

- [ ] 2.6.1 Run `vendor/bin/phpunit` (full suite) -> exit 0. Run `pnpm build` -> exit 0. Run `pnpm lint:check` -> exit 0. Run `php artisan test` -> exit 0.
- [ ] 2.6.2 Run all 9 PR1 grep anti-requirements as a single `tests/Unit/DesignSystem/DesignAntiRequirementsTest.php` test method (or extend the existing suite): no `prefers-color-scheme: dark`; no `Newsreader`; no `useFontsLoaded`; no `var(--font-serif)`; no cream/terracotta/clinicalTeal hex literals outside `tokens.js` + `tokens.generated.css`; no `bg-terracotta-` on stat numbers; no `linear-gradient` in `DashboardPage.vue`; `.surface-glass` uses white-on-white rgba; no `fontFamily.serif` in `tokens.js`. Exit 0.
- [ ] 2.6.3 Replace pre-PR2 visual baselines with iOS-clinical baselines: commit `tests/Visual/baselines/{login-light,login-reduced-motion,login-reduced-transparency,after-login,dashboard,not-found,dashboard-high-contrast}.png` (7 screenshots from Playwright recipe 2.5.1-2.5.7). Exit 0.

PR2 changed-line estimate: **~130 LOC**. Budget cap 400 -> 33% used. Comfortable headroom.

PR2 risks that may push over cap:
- Dashboard icon chip restyle (2.3.1): if chip-32-px layout requires `<style scoped>` additions or template refactor beyond pure class-name swaps, split into a follow-up PR.
- Login card restyle (2.2.1): if removing `var(--font-serif)` cascades into layout shift on the welcome headline (since Newsreader has a different x-height than the system font), the headline may need `lineHeight` tuning that adds ~10 LOC.

---

## Cross-slice anti-requirement index (one row per grep, one row per PR)

| Anti-requirement | Static check | PR1 | PR2 |
|---|---|---|---|
| No `prefers-color-scheme: dark` | `grep -rn "prefers-color-scheme: dark" resources/` | 1.1.8 | 2.6.2 |
| No `Newsreader` | `grep -rn "Newsreader" resources/` | 1.1.6 | 2.6.2 |
| No `useFontsLoaded` | `grep -rn "useFontsLoaded" resources/` | 1.1.6 | 2.6.2 |
| No `var(--font-serif)` | `grep -rn "var(--font-serif)" resources/` | 1.1.6 | 2.1.1, 2.1.5, 2.6.2 |
| No cream/terracotta/clinicalTeal literals outside SoT | forbidden hex set | 1.1.7 | 2.6.2 |
| No `bg-terracotta-` on stat numbers | grep in DashboardPage.vue | - | 2.1.3 |
| No `linear-gradient` in Dashboard | grep in DashboardPage.vue | - | 2.1.4, 2.6.2 |
| No `fontFamily.serif` in `tokens.js` | unit test on tokens.js | 1.1.4 | 2.6.2 |
| `.surface-glass` uses white-on-white rgba | parse generated CSS | 1.1.11 | 2.6.2 |
| Shadow uses `rgba(0, 0, 0, ...)` | parse generated CSS | 1.1.11 | 2.6.2 |
| Deprecated alias keys present | unit test on tokens.js | 1.1.9 | - |
| `radius.ios === '10px'` + `radius.modal === '14px'` | unit test on tokens.js | 1.1.3 | - |

---

## Test summary

| Layer | PR1 | PR2 |
|---|---|---|
| PHP unit (`TokensModuleTest`, `DashboardStatusTest`, design anti-requirements) | 11 new | 5 new + 1 aggregator |
| JS / Vue build (`pnpm build`, `pnpm tokens:build`, `pnpm lint:check`) | yes | yes |
| Laravel feature (`php artisan test`) | - | yes |
| Visual baseline (PNG, <= 200 KB each) | - | 7 PR2 (replace pre-PR1) |
| Playwright 7-step recipe | - | yes |
| Reduced-motion / reduced-transparency / high-contrast forced media queries | - | yes (checkpoints 2, 3, 7) |

---

## Notes / handoffs to `sdd-apply`

- PR1 branch base = `feat/ui-redesign-apple-claude-2026-08-p3` (NOT `main`). Apply uses that tip.
- PR2 branch base = PR1 branch tip, per `stacked-to-main`. PR2 must be cut AFTER PR1 merges.
- No network access required (Newsreader woff2 is deleted, not downloaded). All token hex values are iOS 13+ canonical.
- The `info` ramp re-key to `systemBlue` (test 1.1.9) covers `Badge.vue` `variant="info"` and `AppLayout.vue` WS indicator `bg-info-100 text-info-700`. Both consumers inherit cleanly via the deprecated alias.
- The 17 un-migrated modules auto-recolor via deprecated alias keys (`bg-cream-50` -> `bg-systemGray-50`, `bg-terracotta-500` -> `bg-systemBlue-500`, `bg-clinicalTeal-50` -> `bg-systemBlue-50`, `bg-info-500` -> `bg-systemBlue-500`); zero template edits in those modules.
- Visual baselines are excluded from the 400-LOC authored-LOC budget but counted toward snapshot identity and receipt validation.
- Threat matrix is N/A: the change touches CSS, Vue 3 templates, one Vue composable (deletion), and one font binary (deletion). It does not touch routing, shell commands, subprocesses, VCS/PR automation, or process integration.
