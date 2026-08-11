# Delta Spec: ui-refresh-apple-clinical-2026-08

Vertical: **login + dashboard + 404 + primitives + tokens**. The other 17 modules inherit the new palette via deprecated alias keys but are NOT visually retouched. Chrome, spring runtime, and `prefers-*` contracts stay — only inner rgba swaps.

Slice mapping: **PR1** (tokens + primitives + chrome, ~370 LOC), **PR2** (Login + Dashboard + 404, ~350 LOC). Branch `feat/ui-refresh-apple-clinical-2026-08` from tip of `feat/ui-redesign-apple-claude-2026-08-p3`.

Verification legend: **UT** = `tests/Unit/DesignSystem/TokensModuleTest.php` assertion; **PW** = Playwright checkpoint against `php artisan serve` + `pnpm dev`; **G** = static grep / `git ls-files` assertion.

Scenario format: **WHEN** (trigger) / **DO** (action) / **THEN** (outcome) / **AND** (additional assertion). Each scenario references a UT, PW step, or G.

---

## 1. ios-clinical-tokens  (PR1) — NEW

### Requirement: iOS 13+ system color ramps

The system SHALL expose iOS 13+ system colors (`systemBlue`/`systemRed`/`systemOrange`/`systemYellow`/`systemGreen`/`systemIndigo`/`systemPurple`/`systemPink`/`systemGray`) at steps `{50, 100, 500, 600, 700}`, iOS background ramp (`systemBackground`/`secondaryBackground`/`tertiaryBackground`/`groupedBackground`), iOS label ramp (`label`/`secondaryLabel`/`tertiaryLabel`/`quaternaryLabel`), `separator`/`opaqueSeparator`, and `fill`/`secondarySystemFill`/`tertiarySystemFill`. Canonical SoT: `resources/js/design-system/tokens.js`. **UT** `TokensModuleTest::testIosSystemColorRamps()`.

#### Scenario: systemBlue hex

- **WHEN** `colors.systemBlue['500']` is read
- **DO** assert the hex value
- **THEN** the value is `'#007AFF'`
- **AND** Tailwind's `theme.extend.colors.systemBlue[500]` is `'#007AFF'`
- **PW** PR2 step 1 (login button bg = systemBlue).

#### Scenario: background + label hex

- **WHEN** background and label ramps are read
- **DO** assert canonical hex values
- **THEN** `colors.background.systemBackground === '#FFFFFF'` AND `colors.label.label === '#000000'` AND `colors.separator.separator === '#C6C6C8'`
- **AND** body contrast (`label` on `systemBackground`) is ≥ 21:1 (AAA)
- **UT** `TokensModuleTest::testBackgroundLabelHex()` + `testContrastContract()`.

### Requirement: Anti-requirements (tokens)

The system MUST NOT ship a `@media (prefers-color-scheme: dark)` block. MUST NOT contain cream/terracotta/clinicalTeal hex literals (`#FAF9F7`, `#F2EFE9`, `#E8E3D8`, `#C96442`, `#B05432`, `#2C7A7B`) outside `tokens.js` + `tokens.generated.css`.

#### Scenario: No dark block

- **WHEN** `grep -r "prefers-color-scheme: dark" resources/` is run
- **DO** assert zero matches
- **THEN** exit code is 1, zero rows
- **G** PR1 verification script.

#### Scenario: No cream/terracotta/clinicalTeal literals

- **WHEN** the forbidden hex set is grepped across `resources/`
- **DO** exclude `tokens.js` and `tokens.generated.css`
- **THEN** zero matches remain
- **G** PR1 verification script.

---

## 2. ios-typography  (PR1) — NEW

### Requirement: System font only, no serif

The system SHALL expose `fontFamily.sans` only. `fontFamily.serif` SHALL be removed from `tokens.js`. The generator SHALL NOT emit `--font-serif` or any `@font-face` block. **UT** `TokensModuleTest::testFontFamilySansOnly()`.

#### Scenario: fontFamily.serif absent

- **WHEN** `tokens.fontFamily` is enumerated
- **DO** assert key set membership
- **THEN** `'serif'` is NOT a key
- **AND** `tokens.generated.css` contains no `--font-serif` declaration
- **UT** `TokensModuleTest::testFontFamilySansOnly()` + `testGeneratedCssHasNoFontSerif()`.

### Requirement: Size-specific letterSpacing table for SF/system

The system SHALL define `letterSpacing` per step tuned for SF/system (less aggressive negative tracking than the Newsreader table): small/body `0`, title `-0.01em`, headline `-0.015em`, display `-0.022em`. The `font-optical-sizing` declaration SHALL NOT be present (system font has no `opsz` axis).

#### Scenario: Letter spacing tightens with size

- **WHEN** `tokens.fontSize` is read
- **DO** assert per-step `letterSpacing`
- **THEN** body = `0`, display ≤ `-0.02em`
- **AND** no key sets `font-optical-sizing`
- **UT** `TokensModuleTest::testLetterSpacingTable()`.

---

## 3. ios-radius-scale  (PR1) — NEW

### Requirement: iOS standard radius tokens

The system SHALL expose `radius.ios = '10px'` (cards, buttons, inputs, status chips), `radius.modal = '14px'` (Modal, Sheet, bottom pickers), `radius.sm = '4px'` (small chips), `radius.full = '9999px'` (pills). `radius.lg/2xl/3xl` SHALL be removed.

#### Scenario: Radius literals

- **WHEN** `tokens.radius` is read
- **DO** assert exact values
- **THEN** `radius.ios === '10px'` AND `radius.modal === '14px'`
- **AND** `radius.lg/2xl/3xl` do NOT exist
- **UT** `TokensModuleTest::testRadiusIosAndModal()`.

---

## 4. ios-status-chip  (PR1) — NEW

### Requirement: 32 px rounded-square filled status icon chips

Dashboard icon chips SHALL be 32 px rounded-square (10 px radius), filled with `bg-system{Color}-100` + `text-system{Color}-600`. The pattern replaces the hand-rolled `bg-success-50` / `bg-cream-200` chips. **PW** PR2 step 5.

#### Scenario: Icon chip color tokens

- **WHEN** a Dashboard success card icon chip is rendered
- **DO** inspect computed class names
- **THEN** background is `bg-systemGreen-100` AND text is `text-systemGreen-600`
- **AND** chip dimensions are 32×32 with 10 px radius
- **PW** PR2 step 5; **UT** `TokensModuleTest::testStatusChipClassesResolve()`.

---

## 5. design-system-palette  (PR1) — MODIFIED

(Previously: cream/terracotta/clinicalTeal/ink ramps. Locked rename: terracotta → systemBlue; cream → background + secondaryBackground; ink → label; clinicalTeal → iOS system family.)

### Requirement: Deprecated alias keys preserve 17 un-migrated modules

`tokens.js` SHALL expose deprecated Tailwind alias keys so the 17 un-migrated modules' classes still resolve: `bg-cream-50` → `bg-systemGray-50`, `bg-cream-100` → `bg-systemGray-100`, `bg-cream-200` → `bg-systemGray-200`, `bg-terracotta-500` → `bg-systemBlue-500`, `bg-terracotta-600` → `bg-systemBlue-600`, `bg-clinicalTeal-50` → `bg-systemBlue-50`, `bg-clinicalTeal-500` → `bg-systemBlue-500`, `bg-clinicalTeal-600` → `bg-systemBlue-600`. `info` ramp SHALL be re-keyed to `systemBlue` (`bg-info-500` → `bg-systemBlue-500`).

#### Scenario: Alias regression guard

- **WHEN** deprecated class names appear in any of the 17 un-migrated modules
- **DO** assert the alias resolves to the iOS ramp value at build time
- **THEN** `bg-cream-50` renders as `bg-systemGray-50` AND `bg-terracotta-500` renders as `bg-systemBlue-500` AND `bg-clinicalTeal-50` renders as `bg-systemBlue-50` AND `bg-info-500` renders as `bg-systemBlue-500`
- **AND** no class-name edits are needed in the 17 modules
- **UT** `TokensModuleTest::testDeprecatedAliasesResolve()`.

---

## 6. motion-runtime  (PR1 + PR2) — MODIFIED (unchanged in practice)

(Previously: cream-on-cream `.surface-glass` rgba, warm-black `rgba(20, 17, 14, ...)` shadow tint.)

### Requirement: Chrome rgba swap to white-on-white; pure-black shadows

`resources/css/tokens.generated.css` SHALL emit `.surface-glass` with `rgb(255 255 255 / 0.78)` background (white-on-white). Shadow ramp SHALL use `rgba(0, 0, 0, ...)` (pure black), NOT warm-black `rgba(20, 17, 14, ...)`. `useSpring` / `useSpring2D` / `useSpringMath` composables and timings (`response 0.35 damping 1.0` entrance, `response 0.3 damping 0.8` momentum, `response 0.2 damping 1.0` opacity cross-fade) SHALL be unchanged.

#### Scenario: surface-glass rgba

- **WHEN** `.surface-glass` rgba is regex-extracted from generated CSS
- **DO** match `rgb(255 255 255 / ...)` pattern
- **THEN** match succeeds AND no `rgb(250 249 247 / ...)` match exists
- **AND** shadow rgba uses `rgba(0, 0, 0, ...)` not `rgba(20, 17, 14, ...)`
- **UT** `TokensModuleTest::testSurfaceGlassRgba()` + `testShadowRgbaIsPureBlack()`.

#### Scenario: Reduced transparency solidifies chrome

- **WHEN** `prefers-reduced-transparency: reduce` matches
- **DO** read computed styles on `.sidebar` / `.topbar`
- **THEN** `backdrop-filter` is `none` AND `background-color` resolves to `rgb(255, 255, 255)` (`systemBackground`, not cream)
- **PW** PR2 step 3.

### Requirement: Anti-requirements (motion)

The system MUST NOT animate `width`/`height`/`top`/`left`. MUST NOT contain a universal `* { transition: ... }` rule.

---

## 7. font-loading  (PR1) — MODIFIED

(Previously: Newsreader self-hosted woff2, `@font-face` + `font-display: swap`, `useFontsLoaded` composable.)

### Requirement: Newsreader fully removed

`public/fonts/newsreader-latin.woff2` SHALL NOT exist. `resources/js/composables/useFontsLoaded.js` SHALL NOT exist. No `@font-face` block SHALL be emitted in `tokens.generated.css`. No Vue file SHALL reference `var(--font-serif)`. The system font has zero FOUT risk; no replacement composable ships.

#### Scenario: Newsreader absence

- **WHEN** the absence set is asserted
- **DO** run greps + `git ls-files` checks
- **THEN** `grep -r "Newsreader" resources/` returns nothing AND `grep -r "useFontsLoaded" resources/` returns nothing AND `grep -r "var(--font-serif)" resources/` returns nothing AND `git ls-files public/fonts/newsreader-latin.woff2` exits non-zero AND `git ls-files resources/js/composables/useFontsLoaded.js` exits non-zero
- **G** PR1 verification script; **UT** `TokensModuleTest::testNewsreaderAbsent()` + `testUseFontsLoadedAbsent()`.

---

## 8. dashboard-status  (PR2) — MODIFIED

### Requirement: Status chips re-keyed to iOS system colors

`DashboardPage.vue` icon chip backgrounds SHALL use `bg-systemGreen-100`/`bg-systemOrange-100`/`bg-systemGray-100`/`bg-systemBlue-100`/`bg-systemRed-100` with matching `-600` text. "Estado de Caja" badge SHALL be: "Abierta" → `bg-systemGreen-100 text-systemGreen-600`; "Cerrada" → `bg-systemRed-100 text-systemRed-600`; "Sin sesión" → `bg-systemGray-100 text-systemGray-600`. "Citas Hoy" stat number SHALL be `text-label` (no colored big numbers on iOS clinical). Card borders SHALL be `border-separator`. **PW** PR2 step 5.

#### Scenario: Cash status badge color matches state

- **WHEN** the dashboard renders with cash-register state = "Abierta"
- **DO** inspect the badge computed classes
- **THEN** background is `bg-systemGreen-100` AND text is `text-systemGreen-600`
- **AND** switching state to "Cerrada" yields `bg-systemRed-100 text-systemRed-600`
- **AND** "Sin sesión" yields `bg-systemGray-100 text-systemGray-600`
- **PW** PR2 step 5; **UT** `DashboardStatusTest::testCashBadgeColor()`.

#### Scenario: Stat number not colored

- **WHEN** the "Citas Hoy" stat card renders
- **DO** inspect the value text
- **THEN** class is `text-label` (NOT `text-terracotta-600` or any color ramp)
- **PW** PR2 step 5.

### Requirement: Login + 404 visual revalue

`LoginPage.vue` SHALL drop `font-family: var(--font-serif)` on `.welcome-headline` and `.hero-caption-title`; card surface SHALL be white with 10 px corners and hairline `border-separator`; icon ring SHALL be `systemBlue`. Entrance spring timings SHALL be unchanged. `NotFoundPage.vue` SHALL drop `var(--font-serif)` on `.not-found-headline`; image border SHALL be `border-separator`; shadow SHALL use pure-black rgba.

#### Scenario: Login card chrome

- **WHEN** Login renders at `/login`
- **DO** inspect card computed styles
- **THEN** card background is `bg-systemBackground` AND corner radius is 10 px AND border uses `border-separator` AND headline font-family is system font (NOT serif)
- **AND** primary button bg is `bg-systemBlue`
- **PW** PR2 step 1.

#### Scenario: 404 serif headline gone

- **WHEN** the 404 page renders
- **DO** inspect the headline computed font-family
- **THEN** font-family is the system stack (NOT `var(--font-serif)`)
- **AND** image border is `border-separator`
- **PW** PR2 step 6.

### Requirement: Anti-requirements (dashboard / pages)

The system MUST NOT contain `linear-gradient` or inline gradient backgrounds in `DashboardPage.vue`. MUST NOT contain a `bg-terracotta-*` class on a stat number. MUST NOT ship `prefers-color-scheme: dark` blocks.

---

## Cross-cutting: verification summary per slice

| Slice | Grep assertions | Unit tests | Playwright checkpoints |
|---|---|---|---|
| **PR1** | no Newsreader, no useFontsLoaded, no var(--font-serif), no cream/terracotta/clinicalTeal literals, no dark block, no warm-black rgba, no fontFamily.serif, no Newsreader woff2 | `TokensModuleTest` extended: iOS ramps, hex literals, radius, alias regression guard, surface-glass rgba, contrast contract, Newsreader absence, useFontsLoaded absence | manual: app loads in light mode, white-on-white chrome, no Newsreader fetched |
| **PR2** | no linear-gradient in Dashboard, no bg-terracotta-* on stat numbers, no `prefers-color-scheme: dark` | `DashboardStatusTest::testCashBadgeColor()`; visual baseline iOS-clinical | checkpoints 1-7 (login, reduced-motion, reduced-transparency, after-login, dashboard, 404, high-contrast) |

## Cross-cutting: anti-requirements index

- MUST NOT include `fontFamily.serif` in `tokens.js`.
- MUST NOT emit `@font-face` block in `tokens.generated.css`.
- MUST NOT ship `newsreader-latin.woff2`.
- MUST NOT ship `useFontsLoaded.js`.
- MUST NOT reference `var(--font-serif)` in any Vue file.
- MUST NOT contain `prefers-color-scheme: dark` block anywhere.
- MUST NOT contain cream/terracotta/clinicalTeal hex literals outside `tokens.js` + `tokens.generated.css`.
- MUST NOT animate `width`/`height`/`top`/`left`.
- MUST NOT use a global `* { transition: ... }` rule.
- MUST NOT use `bg-terracotta-*` on a stat number.
- MUST NOT use `linear-gradient` / inline gradient backgrounds in `DashboardPage.vue`.
- MUST NOT place photography inside stat cards or appointment rows.
- MUST NOT use warm-black `rgba(20, 17, 14, ...)` in shadow ramp.
- MUST NOT re-introduce Newsreader (no opt-in fallback).