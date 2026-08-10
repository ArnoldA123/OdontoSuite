# Exploration: ui-redesign-apple-claude-2026-08

SDD phase: `sdd-explore`. Project: `odontosuite`. Repo: Laravel 12 + Vue 3 SPA + Tailwind 3 + vue-router + Vite + Reverb/pusher. Goal: light-only redesign with "Apple chassis + Claude soul" — cream `#FAF9F7` backgrounds, warm near-black ink, terracotta `#C96442` accent, clinical teal for medical states, serif display + system sans body. Phase 1 scope: **Login + Dashboard end-to-end** as the design-language exemplar before propagating to 18 more modules.

## Current State

### Design layer — five CSS files, one JS source-of-truth, one stale duplicate

- `resources/css/design-tokens.css` (265 LOC) — legacy iCloud-style CSS variables. Defines `.ds-card`, `.ds-button`, `.ds-input`, `.ds-fade-in`, `@keyframes shimmer`.
- `resources/css/themes.css` (300 LOC) — semantic CSS variables (`--color-surface-elevated`, `--color-text-primary`, `--color-accent`, `--glass-bg`, `--shadow-md`). Runtime source every Vue component reads. Contains a hand-rolled `* { transition: background-color, border-color, color, box-shadow; }` global rule that owns every color change.
- `resources/css/utilities.css` (272 LOC) — z-index scale, glass utilities, `.skeleton`, `.ripple`, `.apple-shadow`, `.shimmer`. Duplicates `@keyframes shimmer` and `.focus-ring`.
- `resources/css/animations.css` (296 LOC) — `@keyframes fadeIn/slide/scale/bounce`, Vue `<Transition>` classes, `.hover-lift`, `.loading-shimmer`. `.slide-up` duplicated in `themes.css`.
- `resources/css/app.css` (186 LOC) — entry file (`@import` of the three above), `@layer utilities` for `.apple-shadow`, `.grid-responsive`, mobile `@media`, print styles.
- `resources/js/design-system/tokens.js` (175 LOC) — canonical SoT. Exports `colors/spacing/radius/typography/shadow/breakpoint`. Parity enforced by `tests/Unit/DesignSystem/TokensModuleTest.php`.
- `tailwind.config.js` (267 LOC) — imports from `tokens.js`. Defines `boxShadow`, `transitionTimingFunction`, `animation`/`keyframes`, `backdropBlur`, plus `plugins/addUtilities` for `.bg-theme-*`, `.text-theme-*`, `.bg-accent*`.
- `resources/js/utils/design-system.js` (394 LOC) — stale duplicate. `DESIGN_TOKENS` constant, `generateClasses`, `componentUtils.*`, `animationUtils`. Drifted from `tokens.js`. Nobody imports it.

### Theme machinery — already disabled, partially dead

- `resources/js/composables/useTheme.js` (87 LOC) — single hardcoded theme; `setTheme()` ignores the argument; `isDarkMode` returns `false`; localStorage is written but never read.
- `resources/js/components/ui/ThemeSelector.vue` (306 LOC) — dead UI; commented out in `AppLayout.vue`.
- `AppLayout.vue` still imports `useTheme`, `setTheme`, `getThemeOptions`, `getThemeIcon`, `getThemeLabel` — none used.

### Login vertical

- `resources/js/modules/auth/LoginPage.vue` (530 LOC) — three animated `<div class="shape">` blobs (blue/orange/green) with `blur-3xl` and `animation: float 20s`. `<LoginCard>` at `resources/js/components/auth/LoginCard.vue` (42 LOC) is a frosted-glass wrapper with hover lift. Welcome copy is Spanish. Login field is `username`.
- `ForgotPasswordModal.vue` (232 LOC), `ResetPasswordModal.vue` (423 LOC) — `UiModal` based. Dev `reset_token` exposed in UI flow.
- `useAuth.js` (84 LOC), `router/auth.js` (32 LOC).

### Dashboard vertical

- `DashboardPage.vue` (750 LOC) — three sections inside `<AppLayout>`: 5 stat cards, 5 quick-action cards (variant="flat"), today's appointments list. Inline `<style scoped>` (149 LOC) defines `gradient-primary`, `gradient-success`, `gradient-warning`, `gradient-info`, `glass-effect`, keyframes.
- Composables: `useApi`, `useAuth`, `usePermissions`, `useCashRegister`, `useEcho`. No Chart.js in this view.
- Weakness: no visual hierarchy, gradient backgrounds on hover, hand-rolled inline SVGs duplicated per card.

### App shell

- `AppLayout.vue` (969 LOC) — sidebar, mobile header, top bar, `<slot>`, global overlays. WS indicator inline in top bar. User menu manually pop-positioned.
- `PageHeader.vue` (80 LOC), `MobileMenu.vue` (78 LOC), `FloatingActionButton.vue` (38 LOC), `MobileNavigation.vue` (177 LOC, dead), `NotificationCenter.vue` (298 LOC), `ToastContainer.vue` (134 LOC), `NotificationToast.vue` (116 LOC), `NotFoundPage.vue` (71 LOC).

### Shared primitives — blast radius

Login + Dashboard consume these primitives. Restyling any changes the other 17 modules.

| Primitive | LOC | Used by Login | Used by Dashboard |
|---|---|---|---|
| Button.vue | 230 | yes | yes |
| Input.vue | 353 | yes | no |
| Card.vue | 208 | no | yes (5+5+3) |
| Modal.vue | 294 | yes | yes |
| Sheet.vue | 419 | no | AppLayout |
| Toast.vue | 285 | no | no |
| Skeleton.vue | 176 | no | no |
| Badge.vue | 168 | no | yes |
| StatusPill.vue | 95 | no | no |
| Avatar.vue | 274 | no | AppLayout |
| LoadingSpinner.vue | 176 | no | yes |
| EmptyState.vue | 238 | no | no |
| Breadcrumbs.vue | 350 | no | no |
| Tabs.vue | 309 | no | no |
| NotificationToast.vue | 116 | no | via AppLayout |
| ConfirmDialog.vue | 121 | no | via AppLayout |
| ThemeSelector.vue | 306 | no | no |

### Assets

`public/images/pexels/` (314 MB, currently untracked). `INDEX.md` catalogs 322 resources across 25 interfaces. All Pexels-licensed.

**Login hero candidates.** The exploration's claim that `auth/login/` has no `.mp4` is wrong — there are two:
- `public/images/pexels/auth/login/6528789_dental_v1.mp4` (~7.0 MB)
- `public/images/pexels/auth/login/6763242_clinic_v1.mp4` (~6.1 MB)

Still fallbacks: `305567_modern-dental_p1.jpg` (32 KB), `6812463_modern-dental_p2.jpg` (56 KB), `33881127_modern-dental_p3.jpg` (33 KB). Sub-60 KB each.

**404:** `4439425_page-404_p3.jpg` (16 KB), `38072744_page-404_p1.jpg` (27 KB).

**Heavy files to flag.** `10189094_closeup_v1.mp4` (71.7 MB) — DO NOT ship. Others > 5 MB need poster + lazy-load.

### Motion — feasibility

No animation library installed. No motion deps in `package.json`. Current animations: `@keyframes` + Vue `<Transition>` + CSS `transition`.

**Option A** — hand-rolled + Web Animations API. Zero new deps; full control. **Recommended for Phase 1.**
**Option B** — `motion-v` for Vue (community port). Less mature; bundle growth.

### Sound — feasibility

No audio assets. Two paths: (1) generate WAV blobs at runtime via `OfflineAudioContext`; (2) bundle ~5 KB WAVs. Requires user-gesture to unlock AudioContext.

**Recommendation.** Defer. High-context-perceived, low-effort-to-feel-wrong. Out of scope for Phase 1.

## Affected Areas

- `resources/css/{app,themes,animations,utilities,design-tokens}.css` — collapse to one token file + one utilities file.
- `tailwind.config.js` — re-source from `tokens.js` for new palette.
- `resources/js/design-system/tokens.js` — extend with new palette.
- `resources/js/utils/design-system.js` — **delete** (stale duplicate).
- `resources/js/composables/useTheme.js` — collapse to indirection or delete.
- `resources/js/components/ui/ThemeSelector.vue` — **delete**.
- `resources/js/components/layout/{AppLayout,PageHeader,MobileMenu,FloatingActionButton}.vue` — restyle chrome.
- `resources/js/components/{NotificationCenter,ToastContainer,MobileNavigation}.vue` — `MobileNavigation` is dead; delete.
- `resources/js/components/ui/{Button,Input,Card,Modal,Sheet,Toast,Badge,StatusPill,Avatar,Skeleton,LoadingSpinner,EmptyState,Breadcrumbs,Tabs,ConfirmDialog,NotificationToast}.vue` — full primitive library.
- `resources/js/components/auth/LoginCard.vue` — delete (fold into Card variant).
- `resources/js/modules/auth/{LoginPage,ForgotPasswordModal,ResetPasswordModal}.vue` — Login is the visual flagship.
- `resources/js/modules/dashboard/DashboardPage.vue` — restyle.
- `resources/js/modules/errors/NotFoundPage.vue` — adopt new 404 image.
- `tests/Unit/DesignSystem/TokensModuleTest.php` — extend with new palette steps.

## Approaches

- **A. Rebuild tokens in place, primitives first** (recommended).
- **B. Adopt upstream design system** — high effort, sparse Vue 3 ecosystem, custom palette anyway.
- **C. Apple Liquid Glass web approximation** — chrome only, not content cards.
- **D. Decorate-only** — preserves blast radius but the design language never propagates.

## Recommendation

**Approach A + filtered C** — Rebuild tokens in place, collapse the five CSS files to two, restyle the primitives once, then the two verticals. Liquid-Glass web approximation only on chrome (sidebar, top bar, sheet). Hand-roll motion via WAAPI + CSS variables. Defer sound. **Three chained PRs** (PR1 debt cleanup, PR2 tokens + primitives, PR3 Login + Dashboard + 404).

## Risks

- Primitive restyle affects all 17 modules outside Phase 1. `400-line budget risk: High`.
- Dark mode residue: `useTheme.js`, `ThemeSelector.vue`, `Avatar.vue` `@media (prefers-color-scheme: dark)`, `themes.css` `@media (prefers-color-scheme: dark)`.
- Token drift between `tokens.js` (JS) and `themes.css` (CSS) happened before (iCloud-blue hardcoded in CSS while tokens.js carried same value via `primary-500`).
- `design-system.js` dead duplicate. Anyone refactoring could reach for it.
- `MobileNavigation.vue` dead.
- Print styles in `app.css` used by `cash-report-pdf`, `quotation`, `receipt` blades — must keep.
- Gradient classes baked into LoginPage button override (`linear-gradient(135deg, ...)`); removing is a design choice.
- 401 hard-reload in `useApi.handleResponse` incompatible with entry motion.
- Terracotta `#C96442` on cream `#FAF9F7` is ~4.0:1 — below WCAG AA for body. Reserve accent for buttons/links/badges.
- Heavy mp4 assets; if video chosen, optimize with poster + lazy-load.

## Ready for Proposal

**Yes.** Orchestrator should launch `sdd-propose` to lock slice boundaries, dark-mode removal scope, contrast contract, motion runtime, sound deferral, asset picks, and verification plan.
