# Proposal: ui-refresh-apple-clinical-2026-08

## Intent

Replace the "Apple chassis + Claude soul" language shipped in `feat/ui-redesign-apple-claude-2026-08-p3` with a pure iOS clinical aesthetic — system font only, white surfaces, iOS system colors, 10 px radius, hairline separators, vibrant status icon chips. The previous design is the starting point, not the foundation we keep: Newsreader serif, cream `#FAF9F7`, terracotta `#C96442`, and clinical teal `#2C7A7B` all retire. The chrome (Liquid-Glass `.surface-glass`), spring runtime (`useSpring` / `useSpring2D` / `useSpringMath`), and `prefers-*` contracts stay — those are the Apple chassis and they hold.

Vertical scope: **login + dashboard + 404 + primitives + tokens**. The other 17 modules inherit the new palette via deprecated token alias keys but are not visually retouched. The branch is `feat/ui-refresh-apple-clinical-2026-08` from the tip of `feat/ui-redesign-apple-claude-2026-08-p3`; the diff against that branch is what reviewers see.

This is a token swap + primitive revalue + 3-page vertical retouch. Information architecture, content, routes, and data contracts stay.

## Design Read

> Reading this as: clinical admin app for dental staff, light-only, calm/trust-first, leaning toward native iOS — SF system font, white surfaces, iOS gray grouped background, system blue accent, hairline separators, 10 px corner radius, status chips that read at a glance. Dials: `VARIANCE 5`, `MOTION 5`, `DENSITY 6`.

Note on palette shift: `design-taste-frontend` §4.2 bans the cream + terracotta + espresso family as the AI default for premium-consumer briefs. OdontoSuite is not premium-consumer — it is a clinical admin tool — but the previous Claude-soul reading still carries residual "warm craft" connotation that doesn't fit a dental SaaS. The iOS clinical read is more honest: it tells dental staff "this is a serious tool" the way every native Apple app does. Trade-off accepted: less personality, more credibility.

## Capabilities

### New Capabilities

- `ios-clinical-tokens`: iOS 13+ system color family (`systemBlue`/`Red`/`Orange`/`Yellow`/`Green`/`Indigo`/`Purple`/`Pink`/`Gray`, each 50/100/500/600/700), iOS background ramp (`systemBackground`/`secondaryBackground`/`tertiaryBackground`/`groupedBackground`), iOS label ramp (`label`/`secondaryLabel`/`tertiaryLabel`/`quaternaryLabel`), iOS separator (`separator`/`opaqueSeparator`), iOS fill (`systemFill`/`secondarySystemFill`/`tertiarySystemFill`).
- `ios-typography`: system font only, no serif family. `fontSize` `letterSpacing` table tuned for SF/system (less aggressive negative tracking than the Newsreader table — SF Pro Display does not need `-0.03em` to feel display-grade).
- `ios-radius-scale`: 10 px iOS standard replaces 12/16/24/full; modal 14 px. Pills stay full.
- `ios-status-chip`: 32 px rounded-square status icon chips filled with `bg-system{Color}-100` + `text-system{Color}-600`. Replaces Dashboard's hand-rolled `bg-success-50` / `bg-cream-200` icon chips.

### Modified Capabilities

- `design-system-palette`: `terracotta` → `systemBlue`; `cream` → `background` + `secondaryBackground` (white + `#F2F2F7`); `ink` → `label` (iOS label colors, `#000000` body); `clinicalTeal` → iOS system color family per semantic. Deprecated alias keys (`bg-cream-50`, `bg-terracotta-500`, `bg-clinicalTeal-50`) preserved so the 17 un-migrated modules' Tailwind classes still resolve.
- `motion-runtime`: unchanged. `useSpring`/`useSpring2D`/`useSpringMath` + `.surface-glass` + `prefers-reduced-motion`/`prefers-reduced-transparency`/`prefers-contrast` contracts stay.
- `font-loading`: `useFontsLoaded.js` deleted (was already dead, served the Newsreader FOUT mitigation). No replacement — system font has zero FOUT risk.
- `dashboard-status`: "Estado de Caja" badge and appointment status chips re-keyed to iOS system colors (`systemGreen` open, `systemRed` closed, `systemGray` no session, etc.).

## Scope

### In Scope (2 chained PRs, ≤400 LOC each)

**PR1 — Tokens + primitives + chrome (~370 LOC)**

- `tokens.js` rewrite: replace `terracotta`/`cream`/`ink`/`clinicalTeal` ramps with `systemBlue`/`background`/`label`/`separator`/`fill` + iOS system colors; remove `fontFamily.serif`; tune `fontSize` `letterSpacing`; replace `radius.lg/2xl/3xl` with `radius.ios` (10 px) and `radius.modal` (14 px); keep deprecated alias keys for the 17 un-migrated modules.
- `scripts/build-tokens-css.mjs`: drop the `@font-face` block emit; drop `--font-serif`; emit iOS semantic aliases (`--color-accent` → `--color-systemBlue-500`, `--color-text-primary` → `--color-label`, `--color-background` → `--color-systemBackground`, `--color-border` → `--color-separator`); swap shadow rgba tints from warm-black `rgba(20, 17, 14, ...)` to pure `rgba(0, 0, 0, ...)`; swap `.surface-glass` rgba to white-on-white `rgb(255 255 255 / 0.78)`.
- `resources/css/tokens.generated.css`: regenerated. Full-file rewrite; no hand-edits.
- `tailwind.config.js`: re-source from new tokens (automatic via existing pipeline).
- `resources/js/composables/useFontsLoaded.js`: DELETE (dead, no consumers).
- `public/fonts/newsreader-latin.woff2`: DELETE.
- All `resources/js/components/ui/*` primitives: revalue to iOS tokens — class names preserved, rendered values change. `Card.vue`: surface → `bg-systemBackground`, border → `border-separator`, radius → 10 px. `Modal.vue`/`Sheet.vue`: 14 px corners. `Input.vue`: focus ring → `systemBlue`. `Button.vue`: primary → `bg-systemBlue`. `Badge.vue`/`StatusPill.vue`: filled iOS chip pattern.
- `resources/js/components/layout/AppLayout.vue`: page bg → `bg-systemBackground`, nav text → `text-label`, WS indicator chips → `bg-systemGray-100 text-systemGray-600` default, `.surface-glass` rgba → white-on-white.
- `TokensModuleTest.php`: extend with new-ramp assertions + Newsreader absence + `useFontsLoaded` absence + cream/terracotta/clinicalTeal literal absence + iOS system color hex verification + `radius.ios`/`radius.modal` assertions.
- Touches: ~14 source files + 1 test + 1 font binary deleted + 1 dead composable deleted. No user-visible change until PR2.

**PR2 — Login + Dashboard + 404 (~350 LOC)**

- `LoginPage.vue`: drop `font-family: var(--font-serif)` on `.welcome-headline` and `.hero-caption-title`; swap all `var(--color-...)` refs to new token names (`--color-systemBackground`, `--color-systemBlue-500`, `--color-label`, `--color-separator`); card surface → white + 10 px corners + hairline separator; icon ring → `systemBlue`; entrance spring timings unchanged.
- `DashboardPage.vue`: icon chip backgrounds → `bg-systemGreen-100`/`bg-systemOrange-100`/`bg-systemGray-100`/`bg-systemBlue-100`/`bg-systemRed-100`; cash status badge → `systemGreen`/`systemRed`/`systemGray`; "Citas Hoy" stat number `text-terracotta-600` → `text-label` (no colored big numbers on iOS clinical); card border → `border-separator`; 300 ms WS debounce unchanged.
- `NotFoundPage.vue`: drop `font-family: var(--font-serif)` on `.not-found-headline`; image border → `border-separator`; shadow → iOS lighter pure-black rgba.
- `ForgotPasswordModal.vue` + `ResetPasswordModal.vue`: inherit primitive changes from PR1; no class churn.
- `tests/Visual/baselines/*.png`: replace pre-PR2 baselines with iOS-clinical baselines (Playwright 7-step recipe).
- Touches: 3 page files + 2 modal files (inheritance only) + 1 visual baseline refresh.

### Out of Scope (deferred)

- **The other 17 modules** (calendar, patients, appointments, billing, BI, cash-register, environments, appointment-types, procedure-catalog, professionals, my-procedures, settings, reception-procedures, medical-records, branches, payment-methods, ai-analysis). They inherit the new token palette via deprecated alias keys but are not visually retouched. This is a hard scope boundary; module-by-module retouch is a separate future change.
- Dark mode (permanently removed — not preserved, no shim).
- Sound design (matches previous design's deferral; no Phase 1 user research).
- Native iOS / Android wrapper.
- 401 hard-reload in `useApi.handleResponse` → still deferred ticket `auth-session-expiry-toast-2026-08`.
- mp4 login video (matches previous design's deferral).
- Cookie / consent banner.
- Internationalization (Spanish copy stays).
- New icons (Phosphor / HugeIcons / Heroicons already in use; just recolor).
- Login hero image replacement (Pexels shot kept; contrast evaluated at PR-time only if it reads harshly against new white chrome).
- Design tokens for status icon chips not used in the 3 verticals (only `DashboardPage.vue` consumes the chips today; over-tokenization is waste).
- Newsreader re-introduction. If a future contributor wants serif, they re-introduce it explicitly with their own OFL font and metrics — no opt-in fallback ships.

## Approach

**B** — token swap + primitive revalue + 3-page vertical retouch in 2 chained PRs. Rationale: `tokens.js` SoT + generator pipeline is the leverage; class-name churn on the 17 un-migrated modules is avoided by preserving deprecated alias keys; vertical-sample-first delivery (Login + Dashboard + 404) is the same strategy that proved out the previous design and is the smallest surface that lets reviewers judge the new language.

## Concrete decisions (locked)

| Question | Decision |
|---|---|
| **Branch + base** | `feat/ui-refresh-apple-clinical-2026-08` from tip of `feat/ui-redesign-apple-claude-2026-08-p3`. Diff against that branch is the reviewable candidate. Final merge to `main` after both PRs land. |
| **Slice boundaries** | PR1 ≤400, PR2 ≤400 LOC. Chained PRs retarget per Section E of `sdd-phase-common.md`. PR1 targets the new branch; PR2 rebases on PR1's tip and targets the new branch. |
| **Token strategy** | `tokens.js` is SoT; `tailwind.config.js` imports it; `scripts/build-tokens-css.mjs` emits `resources/css/tokens.generated.css` (no hand-edits); `TokensModuleTest.php` enforces parity. |
| **Palette rename** | `terracotta` → `systemBlue` (`#007AFF` family); `cream` → `background` (`systemBackground` `#FFFFFF`, `secondaryBackground` `#F2F2F7`); `ink` → `label` (`label` `#000000`, `secondaryLabel` `#3C3C43`, `tertiaryLabel` `#3C3C43`, `quaternaryLabel` `#3C3C43`); `clinicalTeal` → iOS system color family (Red/Orange/Yellow/Green/Indigo/Purple/Pink/Gray); add `separator` (`#C6C6C8`) and `fill` (`#787880` at 20/16/12 % opacity). |
| **Deprecated aliases (for the 17 un-migrated modules)** | `bg-cream-50` → `bg-systemGray-50`, `bg-cream-100` → `bg-systemGray-100`, `bg-cream-200` → `bg-systemGray-200`; `bg-terracotta-500` → `bg-systemBlue-500`, `bg-terracotta-600` → `bg-systemBlue-600`; `bg-clinicalTeal-50` → `bg-systemBlue-50`, `bg-clinicalTeal-500` → `bg-systemBlue-500`, `bg-clinicalTeal-600` → `bg-systemBlue-600`. Aliases are kept in `tokens.js` so the 17 modules' Tailwind classes still resolve. |
| **`info` ramp re-key** | `info` re-keyed to `systemBlue` (iOS convention: blue = info). `bg-info-100` → `bg-systemBlue-100`, `text-info-700` → `text-systemBlue-700`. Badge.vue `variant="info"` and AppLayout WS indicator both inherit. |
| **Typography** | `fontFamily.serif` REMOVED from `tokens.js`. `--font-serif` REMOVED from generated CSS. `--font-sans` only. Per-step `letterSpacing` tuned for system font: small/body 0, title `-0.01em`, headline `-0.015em`, display `-0.022em` (less aggressive than Newsreader's table — SF/system doesn't need it). `font-optical-sizing` REMOVED (system font has no `opsz` axis). |
| **Newsreader deletion** | DELETE `public/fonts/newsreader-latin.woff2`. REMOVE `@font-face` block emit in generator. DELETE `resources/js/composables/useFontsLoaded.js` (dead code, served FOUT for Newsreader). REMOVE `var(--font-serif)` assignments in `LoginPage.vue` (3 call sites: `.welcome-headline`, `.hero-caption-title`, `prefers-contrast` block) and `NotFoundPage.vue` (1 call site: `.not-found-headline`). |
| **Radius** | `radius.ios = 10px` (cards, buttons, inputs, status chips). `radius.modal = 14px` (Modal, Sheet, bottom pickers). `radius.sm = 4px` (small chips). `radius.full = 9999px` (pills). `radius.lg/2xl/3xl` retire. |
| **Shadow tint** | `tokens.js` shadow ramp stays `rgba(0, 0, 0, ...)` — already neutral; previous design's warm-black tint `rgba(20, 17, 14, ...)` is replaced in `tokens.generated.css` emission to pure-black. |
| **Chrome (`.surface-glass`)** | KEEP the class. Change inner rgba from `rgb(250 249 247 / 0.78)` to `rgb(255 255 255 / 0.78)`. `@media (prefers-reduced-transparency: reduce)` collapses to `var(--color-systemBackground)` solid (white, not cream). Consumed only by `AppLayout.vue` (sidebar, mobile header, top bar). |
| **Status icon chips (Dashboard)** | 32 px rounded-square (10 px radius). `bg-systemGreen-100 text-systemGreen-600` for success; same pattern for warning/orange, error/red, info/blue, neutral/gray. "Estado de Caja" "Abierta" → `bg-systemGreen-100 text-systemGreen-600`; "Cerrada" → `bg-systemRed-100 text-systemRed-600`; "Sin sesión" → `bg-systemGray-100 text-systemGray-600`. |
| **Contrast contract** | Body uses `label` `#000000` on `systemBackground` `#FFFFFF` (~21:1, AAA). Accent `systemBlue-500` `#007AFF` reserved for: primary CTA bg (white text), links, focus rings, active tab indicator, progress bars. Large display uses `label` `#000000` for max presence. Error states use `systemRed-600` `#FF3B30`, not blue. |
| **Motion runtime** | `useSpring`/`useSpring2D`/`useSpringMath` UNCHANGED. Timings unchanged (`response 0.35 damping 1.0` entrance, `response 0.3 damping 0.8` momentum, `response 0.2 damping 1.0` opacity cross-fade). `prefers-reduced-motion` short-circuit unchanged. |
| **LoginPage entrance** | Card spring + opacity spring UNCHANGED. Same `--spring-card-o` and `--spring-card-opacity` CSS variables. |
| **Dashboard WS debounce** | 300 ms trailing debounce UNCHANGED (line 882 in `DashboardPage.vue`). |
| **Sound** | OUT (matches previous design). |
| **Dark mode** | OUT (permanently removed, no shim). `localStorage.theme` reads as no-op on next access. |
| **401 hard-reload** | OUT. Deferred ticket `auth-session-expiry-toast-2026-08`. |
| **`prefers-color-scheme: dark`** | OUT. `tokens.generated.css` emits no `@media (prefers-color-scheme: dark)` block. |
| **`prefers-reduced-transparency`** | KEEP. `.surface-glass` collapses to `var(--color-systemBackground)` solid (white, not cream). |
| **`prefers-contrast: more`** | KEEP. Lifts text to `label` `#000000`, borders to `label` `#3C3C43`, removes tints on badges. |
| **Asset shipping** | `public/images/ui/login-hero.jpg` KEPT initially (Pexels dental shot); evaluated at PR2 time only if contrast against new white chrome reads harsh. `public/images/ui/not-found.jpg` KEPT. mp4 OUT. No new commits. |

## Verification

Per-slice Playwright recipe + PHPUnit + greps:

```bash
php artisan serve    # :8000
pnpm dev             # :5173
```

**PR1 (tokens + primitives + chrome):**

- `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` exits 0 with extended assertions.
- `pnpm build` exits 0.
- `pnpm tokens:build` exits 0 and regenerates `tokens.generated.css` byte-stable.
- `grep -r "Newsreader" resources/` returns nothing (excluding `tokens.generated.css` if any test fixture references it — none expected).
- `grep -r "useFontsLoaded" resources/` returns nothing.
- `grep -r "#FAF9F7\|#F2EFE9\|#E8E3D8\|#C96442\|#B05432\|#2C7A7B" resources/` returns nothing outside `tokens.js` and `tokens.generated.css` (the SoT files; outside them = forbidden literal).
- `grep -r "prefers-color-scheme: dark" resources/` returns nothing.
- Manual: app loads identically in light mode; chrome is white-on-white Liquid-Glass; no Newsreader font is fetched.

**PR2 (Login + Dashboard + 404) Playwright 7-step recipe:**

1. `playwright-cli open http://localhost:8000/login` → screenshot `login-light.png`. Card center, hero image right, systemBlue primary button visible.
2. `prefers-reduced-motion: reduce` context → screenshot `login-reduced-motion.png`. No entrance translation; opacity settles instantly.
3. `prefers-reduced-transparency: reduce` context → screenshot `login-reduced-transparency.png`. Sidebar/top bar solid white, no blur.
4. `fill e1 "adm1n"` + `fill e2 "password123"` + `click e3` → expect `/dashboard`. Screenshot `after-login.png`.
5. `goto http://localhost:8000/dashboard` → screenshot `dashboard.png`. 5 stat cards, 5 quick actions, today's appointments visible; status icon chips use iOS system color fills.
6. `goto http://localhost:8000/404` (or any unmatched route) → screenshot `not-found.png`. 404 image + back-to-login button visible; serif headline gone, system font headline.
7. `prefers-contrast: more` on dashboard → screenshot `dashboard-high-contrast.png`. Text pure black, borders pure `label` `#3C3C43`.

Visual-regression baseline: commit pre-PR2 screenshots into `tests/Visual/baselines/` (PNG, ≤200 KB each, byte-stable via `pnpm vitest --update`). PR1 must match baseline (tokens changed but no page logic did); PR2 replaces baseline with new iOS baselines.

TDD coverage — `tests/Unit/DesignSystem/TokensModuleTest.php` extended with:
- All new iOS ramps asserted (systemBlue, systemRed, systemOrange, systemYellow, systemGreen, systemIndigo, systemPurple, systemPink, systemGray, background, label, separator, fill).
- `colors.systemBlue['500'] === '#007AFF'` literal check.
- `colors.background.systemBackground === '#FFFFFF'` literal check.
- `colors.label.label === '#000000'` literal check.
- `colors.separator.separator === '#C6C6C8'` literal check.
- Newsreader **absence**: `assertArrayNotHasKey('serif', $tokens['fontFamily'])`.
- `@font-face` block **absence** in `tokens.generated.css`.
- `newsreader-latin.woff2` file **absence** in `public/fonts/`.
- `useFontsLoaded.js` file **absence** in `resources/js/composables/`.
- `var(--font-serif)` **absence** across `resources/`.
- Cream/terracotta/clinicalTeal hex **absence** in `resources/` outside `tokens.js` + `tokens.generated.css`.
- `radius.ios === '10px'` and `radius.modal === '14px'` literal checks.
- Shadow rgba uses pure black `rgba(0, 0, 0, ...)` regex match in generated CSS.
- `.surface-glass` rgba uses `rgb(255 255 255 / ...)` not `rgb(250 249 247 / ...)` regex match.
- Deprecated alias keys present (regression guard for the 17 un-migrated modules): `bg-cream-50` → `bg-systemGray-50`, `bg-terracotta-500` → `bg-systemBlue-500`, `bg-clinicalTeal-50` → `bg-systemBlue-50`.
- `info` ramp re-keyed: `bg-info-500` → `bg-systemBlue-500`.
- Single `:root` block in generated CSS; no `@media (prefers-color-scheme: dark)` block.
- Generated CSS hex-set equals `tokens.colors` hex-set (existing 2.1.12 parity test, must hold under rename).

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `openspec/changes/ui-refresh-apple-clinical-2026-08/proposal.md` | New | This file. |
| `resources/js/design-system/tokens.js` | Modified | New iOS ramps; remove `fontFamily.serif`; tune `letterSpacing`; replace `radius.lg/2xl/3xl` with `radius.ios`/`radius.modal`; keep deprecated alias keys. |
| `scripts/build-tokens-css.mjs` | Modified | Drop `@font-face` emit; drop `--font-serif`; emit iOS semantic aliases; swap shadow rgba to pure black; swap `.surface-glass` rgba to white-on-white. |
| `resources/css/tokens.generated.css` | Modified | Full regen. Not hand-edited. |
| `tailwind.config.js` | Modified | Re-source from new tokens (automatic via pipeline). |
| `resources/js/composables/useFontsLoaded.js` | Removed | Dead code; deletion is safe (no consumers). |
| `public/fonts/newsreader-latin.woff2` | Removed | Font binary deleted. |
| `resources/js/components/ui/Button.vue` | Modified | Primary → `bg-systemBlue`; focus ring → `systemBlue`; radius 10 px. |
| `resources/js/components/ui/Card.vue` | Modified | Surface → `bg-systemBackground`; border → `border-separator`; radius 10 px; lighter shadow. |
| `resources/js/components/ui/Modal.vue` | Modified | Surface → `bg-systemBackground`; corners 14 px. |
| `resources/js/components/ui/Sheet.vue` | Modified | Surface → `bg-systemBackground`; corners 14 px. |
| `resources/js/components/ui/Input.vue` | Modified | Surface → `bg-tertiaryBackground`; border → `border-separator`; focus ring → `systemBlue`. |
| `resources/js/components/ui/Badge.vue` | Modified | iOS filled status pill; `variant="info"` re-keyed to `systemBlue`. |
| `resources/js/components/ui/StatusPill.vue` | Modified | iOS filled pill; 10 px radius. |
| `resources/js/components/ui/Toast.vue` | Modified | Surface → `bg-systemBackground` + `border-separator` + shadow. |
| `resources/js/components/ui/Skeleton.vue` | Modified | Derive from `bg-systemGray-100`. |
| `resources/js/components/ui/LoadingSpinner.vue` | Modified | `--spinner-color` → `systemBlue`. |
| `resources/js/components/ui/EmptyState.vue` | Modified | Surface → `bg-systemBackground`. |
| `resources/js/components/ui/Avatar.vue` | Modified | Token swap only (already neutral). |
| `resources/js/components/ui/Breadcrumbs.vue` | Modified | Separator → `seam` iOS style. |
| `resources/js/components/ui/Tabs.vue` | Modified | Active indicator → `systemBlue`. |
| `resources/js/components/ui/ConfirmDialog.vue` | Modified | Surface → `bg-systemBackground`. |
| `resources/js/components/ui/NotificationToast.vue` | Modified | Surface → `bg-systemBackground`. |
| `resources/js/components/layout/AppLayout.vue` | Modified | Page bg → `bg-systemBackground`; nav text → `text-label`; WS indicator chips → `bg-systemGray-100`; `.surface-glass` rgba → white-on-white. |
| `resources/js/components/layout/PageHeader.vue` | Modified | Token swap only. |
| `resources/js/components/layout/FloatingActionButton.vue` | Modified | Token swap only. |
| `resources/js/modules/auth/LoginPage.vue` | Modified (PR2) | Drop `var(--font-serif)` on 2 call sites; swap `var(--color-...)` to new token names; card surface → white + 10 px + hairline; icon ring → `systemBlue`. |
| `resources/js/modules/auth/ForgotPasswordModal.vue` | Modified (PR2) | Inherits primitive changes from PR1. |
| `resources/js/modules/auth/ResetPasswordModal.vue` | Modified (PR2) | Inherits primitive changes from PR1. |
| `resources/js/modules/dashboard/DashboardPage.vue` | Modified (PR2) | Icon chip backgrounds → `system{Color}-100`; cash status badge → semantic-system; "Citas Hoy" big number → `text-label`; card border → `border-separator`. |
| `resources/js/modules/errors/NotFoundPage.vue` | Modified (PR2) | Drop `var(--font-serif)` on `.not-found-headline`; image border → `border-separator`; shadow → iOS lighter. |
| `tests/Unit/DesignSystem/TokensModuleTest.php` | Modified | New ramp assertions + Newsreader absence + `useFontsLoaded` absence + cream/terracotta/clinicalTeal literal absence + iOS hex verification + `radius.ios`/`radius.modal` + alias regression guard + `info` re-key. |
| `tests/Visual/baselines/*.png` | New (PR2) | Pre-PR1 visual baseline + PR2 iOS-clinical baselines. |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| 17 un-migrated modules visually shift when ramps are swapped | High | PR1 keeps the SAME Tailwind class names (`bg-cream-50`, `bg-terracotta-500`, `bg-clinicalTeal-50`, etc.); `tokens.js` carries deprecated alias keys (`bg-cream-50` → `bg-systemGray-50`, `bg-terracotta-500` → `bg-systemBlue-500`, `bg-clinicalTeal-50` → `bg-systemBlue-50`) so the 17 modules continue to resolve, just to the new iOS values. Diff is CSS-variable re-definitions, not class churn. Reviewer can verify by diffing the 17 module files against `feat/ui-redesign-apple-claude-2026-08-p3` and confirming zero class-name edits. |
| Token parity drift between `tokens.js` and `tokens.generated.css` (already happened with iCloud blue in the previous design) | Medium | `TokensModuleTest` parses both files and asserts set-equality on every hex value (existing 2.1.12 test extended to all new ramps). Generator is idempotent (`pnpm tokens:build` is byte-stable). |
| `tokens.generated.css` regenerate diff is large | Medium | Full-file rewrite; acceptable. Generator is the only writer. No hand-edits ever. |
| Newsreader orphan — any inline `font-family: 'Newsreader'` outside `tokens.js` / `tokens.generated.css` would orphan | Low | Grep audit found exactly 3 call sites (LoginPage x2, NotFoundPage x1), all in PR2 scope. `useFontsLoaded.js` references `.font-serif` in comments but the file is deleted in PR1. `public/fonts/newsreader-latin.woff2` is deleted in PR1. |
| `info` ramp re-key breaks a consumer outside the 3 verticals | Low | Audit: `Badge.vue` `variant="info"` and `AppLayout.vue` WS indicator `bg-info-100 text-info-700` are the only consumers. Both inherit the alias and render `systemBlue`. No other module uses `info` per the previous design's `info` removal. |
| iOS system color hex values drift from Apple HIG over time | Low | iOS 13+ system colors have been stable since 2019; values are sourced from the iOS Human Interface Guidelines color reference. `TokensModuleTest` literal-checks the canonical hex values so any drift fails the build. |
| Vertical retouch in PR2 exceeds 400 LOC | Low | Estimated ~350 LOC (Login ~120, Dashboard ~180, NotFoundPage ~50). Buffer under budget. If actual LOC exceeds 400, split PR2 into PR2a (Dashboard only, the largest) + PR2b (Login + 404). |
| `useFontsLoaded.js` orphan causes a console error on first paint | Very Low | Already dead — never imported. Verified by grep audit. Deletion is a no-op behavior change. |
| Visual regression on the 17 un-migrated modules alarms reviewers ("everything looks different now") | Medium | Communicate in PR1 description: token swap is intentional; visuals shift because palette shifted; the 17 modules are not in scope for visual retouch in this change; module-by-module retouch is the next tranche. Visual baseline is replaced only in PR2 (3 verticals), not for the 17 modules. |

## Rollback Plan

Per PR (each is independently revertible):

- **PR1** — `git revert <sha>`. `tokens.js` reverts to terracotta/cream/ink/clinicalTeal ramps; `tokens.generated.css` reverts; `useFontsLoaded.js` is restored (was deleted); Newsreader woff2 is restored (was deleted); all primitives revert to cream/terracotta values; `AppLayout.vue` chrome rgba reverts to cream-on-cream. `TokensModuleTest` reverts to old assertions; must revert the test in the same commit. App returns to "Apple chassis + Claude soul" state from `feat/ui-redesign-apple-claude-2026-08-p3`.
- **PR2** — `git revert <sha>`. Login + Dashboard + 404 revert to old visuals. Primitives stay on iOS tokens (PR1 already merged); the revert cleanly re-chromes the 3 pages to cream/terracotta/Newsreader without touching tokens. Visual baselines revert.

If both PRs are merged and a roll-back of the whole change is needed: `git revert <pr2-sha> <pr1-sha>` (newest-first). Both PRs are independently revertible so a partial roll-back is also possible.

## Dependencies

- None (no new npm or composer packages).
- `pnpm tokens:build` is the only build-time invocation; it is already wired and idempotent.
- Playwright CLI recipe is local dev tool, not a runtime dep.

## Success Criteria

- [ ] PR1 and PR2 each merge with `vendor/bin/phpunit` exit 0 and `pnpm build` exit 0.
- [ ] `TokensModuleTest` extended assertions pass (all new iOS ramps, Newsreader absence, `useFontsLoaded` absence, cream/terracotta/clinicalTeal literal absence, iOS hex verification, alias regression guard, `info` re-key, radius assertions).
- [ ] Playwright PR2 checkpoints 1-7 produce the screenshots described.
- [ ] WCAG AAA contrast verified for `label` `#000000` on `systemBackground` `#FFFFFF` (~21:1) and `systemBlue-500` `#007AFF` on `systemBackground` `#FFFFFF` for large button text (~4.5:1 minimum).
- [ ] `grep -r "Newsreader" resources/` returns nothing.
- [ ] `grep -r "useFontsLoaded" resources/` returns nothing.
- [ ] `grep -r "#FAF9F7|#F2EFE9|#E8E3D8|#C96442|#B05432|#2C7A7B" resources/` returns nothing outside `tokens.js` + `tokens.generated.css`.
- [ ] `grep -r "prefers-color-scheme: dark" resources/` returns nothing.
- [ ] `grep -r "var(--font-serif)" resources/` returns nothing.
- [ ] `ls public/fonts/newsreader-latin.woff2` returns not-found.
- [ ] `ls resources/js/composables/useFontsLoaded.js` returns not-found.
- [ ] `pnpm lint:check` exit 0.
- [ ] No `git ls-files | grep _v1.mp4` (mp4 deferral preserved).
- [ ] Branch `feat/ui-refresh-apple-clinical-2026-08` carries a clean diff against `feat/ui-redesign-apple-claude-2026-08-p3` (no accidental edits to the 17 un-migrated module files).
