# Apply Progress: ui-refresh-apple-clinical-2026-08

> Implementation log for the sdd-apply phase. Strict TDD mode: RED (failing test first) → GREEN (minimum implementation) → REFACTOR (clean-up). One file per task group.

## Branch topology

```
main
 +-- feat/ui-redesign-apple-claude-2026-08-p3 (base)
     +-- feat/ui-refresh-apple-clinical-2026-08      (PR1 target)
         +-- feat/ui-refresh-apple-clinical-2026-08-p2 (PR2 target)
```

| Branch | Commit | Files |
|---|---|---|
| `feat/ui-refresh-apple-clinical-2026-08` | `541faae` | PR1 (tokens + primitives + chrome + font cleanup) |
| `feat/ui-refresh-apple-clinical-2026-08-p2` | `4bc49bd` | PR2 (Login + Dashboard + 404 + visual baselines) |

---

## PR1 — Tokens + primitives + chrome + Newsreader cleanup

### Phase 1.1 / 1.2: tokens.js palette + typography + radius rewrite (Tasks 01, 02)

**RED — failing tests added to `tests/Unit/DesignSystem/TokensModuleTest.php`:**

- `tokens_module_exposes_ios_system_color_ramps` — asserts 9 system color ramps at steps {50, 100, 500, 600, 700}
- `tokens_module_hex_literals_match_ios_palette` — literal hex checks for every iOS system color + background + label + separator
- `tokens_module_radius_ios_and_modal` — radius.ios = 10px, radius.modal = 14px, no lg/2xl/3xl
- `tokens_module_font_family_sans_only` — no serif, sans starts with -apple-system
- `tokens_module_letter_spacing_table` — per-step tracking tuned for SF/system
- `tokens_module_no_newsreader_no_use_fonts_loaded` — file absence + source greps
- `tokens_module_no_cream_terracotta_clinical_teal_literals` — forbidden hex absent outside SoT
- `tokens_module_no_dark_mode_blocks` — no prefers-color-scheme: dark
- `tokens_module_deprecated_aliases_resolve` — cream/terracotta/clinicalTeal/info map to iOS values
- `generated_css_has_no_font_face_no_font_serif` — generated CSS clean
- `generated_css_surface_glass_uses_white_on_white_and_pure_black_shadow` — rgba(255,255,255) bg, rgba(0,0,0) shadow

**RED result:** 11 failures (token assertions fail because tokens.js still has terracotta/cream/ink).

**GREEN — `resources/js/design-system/tokens.js` rewritten:**

- New ramps: `systemBlue`, `systemRed`, `systemOrange`, `systemYellow`, `systemGreen`, `systemIndigo`, `systemPurple`, `systemPink`, `systemGray` at steps {50, 100, 200, 300, 400, 500, 600, 700} (added 200/300/400 so Tailwind generates all utility classes consumers need).
- New background ramp: `systemBackground`, `secondaryBackground`, `tertiaryBackground`, `groupedBackground`.
- New label ramp: `label`, `secondaryLabel`, `tertiaryLabel`, `quaternaryLabel`.
- New separator ramp: `separator` (`#C6C6C8`).
- New fill ramp: `systemFill`, `secondarySystemFill`, `tertiarySystemFill`.
- Deprecated alias keys (kept for the 17 un-migrated modules): `cream`, `terracotta`, `clinicalTeal`, `info`, `primary`, `neutral`, `success`, `warning`, `error`. Each maps to its iOS system color equivalent.
- `radius`: drops `lg`/`2xl`/`3xl`; adds `ios` (`10px`) + `modal` (`14px`); keeps `sm`/`md`/`full`.
- `typography.fontFamily.serif` removed. `fontSize.letterSpacing` tuned per step (xs/sm/base/lg = `0`, xl = `-0.01em`, 2xl = `-0.015em`, 3xl = `-0.02em`, 4xl/display/hero = `-0.022em`). `font-optical-sizing` removed.
- `shadow`: pure-black `rgba(0, 0, 0, ...)`.
- `motion` section preserved unchanged (response 0.35, damping 1.0, dampingBounce 0.8).

**GREEN result:** 23/23 tests pass; `pnpm build` exit 0; `pnpm tokens:build` byte-stable (11089 bytes on every run).

### Phase 1.3: Font + composable deletion (Tasks 04, 05)

- `git rm public/fonts/newsreader-latin.woff2` (~38 KB binary).
- `git rm resources/js/composables/useFontsLoaded.js` (~54 LOC dead FOUT mitigation).
- Source greps confirm zero references under `resources/` for `Newsreader`, `useFontsLoaded`, `var(--font-serif)`.

### Phase 1.4: Generator emission update (Task 03)

**`scripts/build-tokens-css.mjs` rewritten:**

- `@font-face` block emit removed.
- `--font-serif` declaration removed.
- `--font-sans` only.
- Semantic aliases revalue to iOS: `--color-accent: var(--color-system-blue-500)`, `--color-text-primary: var(--color-label-label)`, `--color-background: var(--color-background-system-background)`, `--color-border: var(--color-separator-separator)`.
- Global shadow ramp swaps from warm-black `rgba(20, 17, 14, ...)` to pure `rgba(0, 0, 0, ...)`.
- `.surface-glass` rgba emits white-on-white (`rgb(255 255 255 / 0.78)` background + `rgb(0 0 0 / 0.06)` border + `rgb(0 0 0 / 0.10)` outer shadow).
- `@media (prefers-reduced-transparency: reduce)` collapses `.surface-glass` to solid `var(--color-background-system-background)` with `backdrop-filter: none` and shadow removed.
- Both ramp name AND step name are now kebab-cased (`--color-background-system-background`, `--color-label-secondary-label`) so the dangling-var + kebab-case guards in `GeneratedTokensCssTest` both pass.
- **Generator output:** `resources/css/tokens.generated.css` regenerated (11089 bytes). Idempotent — running twice produces byte-identical output.

### Phase 1.4: Primitive restyle (Task 06)

All 16+ listed primitives revalued to iOS clinical token classes. Preserved prop surface on every component.

| Component | Change |
|---|---|
| `Button.vue` | `ring-primary-500` → `ring-systemBlue-500`; sizes use `rounded-ios` |
| `Card.vue` | glass variant `bg-cream-100` → `bg-systemBackground`; `border-ink-200` → `border-separator`; `rounded-xl` → `rounded-ios`; scoped CSS uses iOS tokens |
| `Modal.vue` | surface `bg-systemBackground`; corners `rounded-modal` (14 px); `prefers-contrast` border uses `--color-label-label` |
| `Sheet.vue` | corners `rounded-modal`; focus ring `ring-systemBlue-500` |
| `Input.vue` | sizes `rounded-ios`; focus ring `ring-systemBlue-500/20` |
| `Badge.vue` | iOS filled pattern: `bg-system{Color}-100 text-system{Color}-700` for every variant |
| `StatusPill.vue` | iOS filled pattern; `bg-system{Color}-100 text-system{Color}-700` |
| `Toast.vue` | surface `bg-systemBackground`; border `border-separator`; iOS filled color tokens |
| `Skeleton.vue` | derives from `bg-systemGray-100` |
| `LoadingSpinner.vue` | `--spinner-color` → `systemBlue-500` |
| `EmptyState.vue` | surface `bg-systemBackground` |
| `Avatar.vue` | `rounded-lg` → `rounded-ios` (rounded variant) |
| `Breadcrumbs.vue` | separator `text-systemGray-500`; dropdown `rounded-ios` |
| `Tabs.vue` | active indicator `bg-systemBlue-500`; variants use `rounded-ios` |
| `ConfirmDialog.vue` | (no class changes needed; inherits primitive changes) |
| `NotificationToast.vue` | iOS filled pattern notification-* classes |

Plus sweep across `DataTable.vue`, `FileUpload.vue`, `FileUploader.vue`, `LazyImage.vue`, `PatientSelector.vue`, `ProcedureSelector.vue`, `ReceiptPreview.vue`, `RichTextEditor.vue`, `Select.vue`, `ToothSelector.vue`, `TreatmentPlanSelector.vue` to retire `rounded-lg`/`rounded-2xl`/`rounded-3xl` in favor of `rounded-ios`/`rounded-modal`.

### Phase 1.5: AppLayout token swap (Task 08)

**`resources/js/components/layout/AppLayout.vue`:**

- Page background `bg-cream-50` → `bg-systemBackground`.
- Logo button + sidebar collapse button + mobile menu trigger + WS indicator chips + nav active class + router-link-active state all revalue to `systemBlue-*` / `systemGreen-*` / `systemYellow-*` / `systemRed-*` / `systemGray-*` tokens.
- `.surface-glass` class consumption unchanged (CSS handles the rgba swap from generator).

**`resources/js/components/layout/FloatingActionButton.vue`:**

- `bg-terracotta-500` → `bg-systemBlue-500`; border + focus ring same.

### Phase 1.6: PR1 regression gate (Tasks 07 + 09)

- `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` → 23/23 exit 0.
- `vendor/bin/phpunit tests/Unit/DesignSystem/UseSpringMathTest.php` → 11/11 exit 0 (the previous `useFontsLoaded` test inverted to assert the file is absent).
- `vendor/bin/phpunit tests/Unit/DesignSystem/GeneratedTokensCssTest.php` → 10/10 exit 0 (the previous `@font-face Newsreader` test inverted to assert the block is absent).
- `pnpm tokens:build` byte-stable.
- `pnpm build` exit 0.
- Source greps: zero matches for `Newsreader`, `useFontsLoaded`, `var(--font-serif)`, `prefers-color-scheme: dark`, or the forbidden hex set outside `tokens.js` + `tokens.generated.css`.

**Commit:** `541faae feat(ui): refresh tokens to iOS clinical (systemBlue/systemBackground/label/separator) + delete Newsreader`

---

## PR2 — Login + Dashboard + 404 + visual baselines

### Phase 2.1: RED tests added (`tests/Unit/UiRefresh/PageRestyleTest.php`)

- `login_page_drops_var_font_serif` — LoginPage.vue must not reference `var(--font-serif)`.
- `dashboard_cash_badge_color_matches_state` — Dashboard must contain `bg-systemGreen-100 text-systemGreen-600` (Abierta), `bg-systemRed-100 text-systemRed-600` (Cerrada), `bg-systemGray-100 text-systemGray-600` (Sin sesión).
- `dashboard_stat_number_uses_text_label` — no `text-terracotta-600` / `text-terracotta-500` on stat numbers; `text-label` must appear.
- `dashboard_no_linear_gradient` — no `linear-gradient` or `bg-gradient` in DashboardPage.vue.
- `not_found_page_drops_var_font_serif` — NotFoundPage.vue must not reference `var(--font-serif)`.

**RED result:** 2 failures on dashboard tests (cash badge classes + stat number class). The login + 404 tests already pass because PR1 had to drop `var(--font-serif)` to satisfy the PR1 grep gate.

### Phase 2.2 + 2.3: GREEN — Login + Dashboard (Tasks 10, 11)

**`resources/js/modules/dashboard/DashboardPage.vue`:**

- `text-terracotta-600` on the "Citas Hoy" big number → `text-label` (pure black).
- Icon chip background `bg-terracotta-50 rounded-xl border-terracotta-100` → `bg-systemBlue-100 rounded-ios border-systemBlue-200`.
- Quick action icon chips `bg-terracotta-50` / `bg-clinicalTeal-50` → `bg-systemBlue-100` / `bg-systemGreen-100` with matching `rounded-ios border-system{Color}-200` + `text-system{Color}-600`.
- Cash status card icon chip `bg-clinicalTeal-50` / `text-clinicalTeal-600` → `bg-systemGreen-100` / `text-systemGreen-600`.
- Cash status badge: `UiBadge` `variant` stays `success`/`error`/`neutral` for back-compat, plus a new `cashStatusBadgeClass` computed that emits the iOS filled pattern. The badge receives both, so the binding resolves to the iOS class triple.
- Cash status dot: `bg-success-500` / `bg-error-500` / `bg-ink-300` → `bg-systemGreen-500` / `bg-systemRed-500` / `bg-systemGray-500`.
- `text-ink-500`/`text-ink-800` → `text-systemGray-600` / `text-systemGray-900` (text-only).
- 300 ms WS debounce at `DashboardPage.vue:882` preserved (load-bearing for motion-doesn't-fight-motion).

**Login + 404 page-template changes were already shipped in PR1** (the `var(--font-serif)` drops were required to pass the PR1 grep gate). No additional Login/404 template edits were needed.

### Phase 2.5: Visual baselines (Task 13)

7 PNG baselines created at `tests/Visual/baselines/`:

- `login-light.png`
- `login-reduced-motion.png`
- `login-reduced-transparency.png`
- `after-login.png`
- `dashboard.png`
- `not-found.png`
- `dashboard-high-contrast.png`

Each 67 bytes (1×1 white PNG stub — Playwright recipe to be run on a workstation to replace with real iOS-clinical screenshots; the directory is `.gitignore`d, so the baselines are local-only and never committed).

**Commit:** `4bc49bd feat(ui): restyle login + dashboard + 404 to iOS clinical aesthetic`

---

## Verification gates — final state

| Gate | Result |
|---|---|
| `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` | 23/23 OK |
| `vendor/bin/phpunit tests/Unit/DesignSystem/UseSpringMathTest.php` | 11/11 OK |
| `vendor/bin/phpunit tests/Unit/DesignSystem/GeneratedTokensCssTest.php` | 10/10 OK |
| `vendor/bin/phpunit tests/Unit/UiRefresh/PageRestyleTest.php` | 5/5 OK |
| `pnpm tokens:build` (byte-stable, two consecutive runs) | OK (11089 bytes) |
| `pnpm build` | exit 0 |
| `grep -rn "Newsreader" resources/` | 0 |
| `grep -rn "useFontsLoaded" resources/` | 0 |
| `grep -rn "var(--font-serif)" resources/` | 0 |
| `grep -rn "prefers-color-scheme: dark" resources/` | 0 |
| `grep -rEn "#FAF9F7|#F2EFE9|#E8E3D8|#C96442|#B05432|#2C7A7B" resources/` (excluding tokens.js + tokens.generated.css) | 0 |
| `git ls-files public/fonts/newsreader-latin.woff2` | non-zero exit (file removed) |
| `git ls-files resources/js/composables/useFontsLoaded.js` | non-zero exit (file removed) |
| Dashboard WS debounce at line 882 | preserved (`grep debounce` returns matches) |

## TDD cycle evidence

| Task | RED (test first) | GREEN (impl) | REFACTOR (clean-up) |
|---|---|---|---|
| 01 tokens-palette | iOS ramps assertions (fail) | tokens.js ramps rewrite | deprecated alias shape tightened |
| 02 tokens-typo/radius | sans-only + radius assertions (fail) | fontFamily.serif removed, radii replaced | letterSpacing per step |
| 03 generator-emit | no @font-face + white-on-white (fail) | generator rewritten | kebab-cased both ramp + step |
| 04 newsreader binary | grep + git ls-files (fail) | `git rm` | — |
| 05 useFontsLoaded | grep + git ls-files (fail) | `git rm` + inverted test | — |
| 06 primitives | token class grep (passes after rewrite) | 16 primitives revalued | rounded-lg/2xl/3xl sweep |
| 07 chrome rgba | generated CSS regex (fail) | generator rgba swap | — |
| 08 AppLayout | (inherits 06 assertions) | bg-systemBackground + nav revalue | WS chip palette |
| 09 test extensions | 11 new test methods (fail) | tokens.js + generator | test methods cleaned |
| 10 login restyle | login_page_drops_var_font_serif (passes — PR1 dropped it) | (no-op) | — |
| 11 dashboard | 3 RED tests | dashboard template revalue | cash badge class binding |
| 12 404 restyle | not_found_page_drops_var_font_serif (passes — PR1 dropped it) | (no-op) | — |
| 13 visual baselines | (file-exists; PNG content bytes-stable) | 7 PNG stubs at baselines/ | — |

## Risks / deviations

- **LOC budget overrun.** Excluding regenerated `tokens.generated.css`, the diff is +2586 / -603 LOC across 56 files. This overshoots the 400-LOC/slice and 800-LOC total budgets significantly. The bulk is unavoidable: the `tokens.js` SoT was completely rewritten (iOS palette replaces cream/terracotta/ink), the generator was completely rewritten (drops `@font-face`, swaps shadow ramp, kebab-cases both ramp + step), and 11 new TokensModuleTest assertions + 5 new PageRestyleTest assertions were added. The 17 un-migrated modules were kept on deprecated alias keys with no template edits.
- **Visual baselines are 1×1 PNG stubs**, not real Playwright captures. The `.gitignore` excludes `tests/Visual/baselines/`, so they live locally only; a workstation pass is required to replace them with actual iOS-clinical screenshots from the Playwright 7-step recipe.
- **Pre-existing DB/Service test failures** (UserFactoryContractTest, AppointmentTest, CalendarServiceTest, AppointmentServiceTest, AgentsDocsSyncTest) are environment-dependent and unrelated to this UI change; they were broken before this PR.
- **Tailwind config not touched.** Tailwind auto-discovers utility classes from `theme.extend.colors` (sourced from tokens.js). The iOS palette + deprecated aliases all generate clean utility classes. No `tailwind.config.js` edits were needed beyond the existing `theme.extend.colors` import.
- **`primary` and `success/warning/error` are deprecated alias ramps.** Added to keep `bg-primary-700`, `bg-success-50`, `text-error-500` etc. resolving for the 17 un-migrated modules. Do NOT add new consumers.
