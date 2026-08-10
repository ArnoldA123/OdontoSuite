# Proposal: ui-redesign-apple-claude-2026-08

## Intent

Establish a single design language ("Apple chassis + Claude soul") for OdontoSuite by shipping the first vertical exemplar end-to-end (Login + Dashboard + 404) and the primitive layer it stands on. The current layer has drifted: an iCloud-blue accent (`#0066CC`) hardcoded in `themes.css` lives next to a `tokens.js` whose `primary-500` carries the same value via an unrenamed key. Five CSS files duplicate keyframes; `useTheme.js` writes localStorage that nothing reads; `ThemeSelector.vue` is dead code commented out in `AppLayout`. Phase 1 fixes the foundation and proves the language on one flow before propagating to 18 modules.

This is a redesign-overhaul: visuals rebuild from scratch, IA and content stay.

## Design Read

> Reading this as: clinical admin app for dental staff, calm/trust-first, leaning toward Apple chassis (translucent chrome, spring motion, optical type) + Claude soul (warm cream + terracotta). Dials: `VARIANCE 6`, `MOTION 6`, `DENSITY 5`.

Note on palette risk: `design-taste-frontend` §4.2 explicitly bans the cream+terracotta+espresso family as the AI default for premium-consumer briefs. OdontoSuite is not premium-consumer — it is a clinical admin tool, and the user picked this palette deliberately. The proposal defends it: warm cream reads as "calm clinical" rather than "craft luxury," terracotta differentiates from the iCloud blue of every other dental SaaS, and clinical teal carries medical-state semantics. The risk is real (warm palette can drift into craft-cookware); mitigated by restraint — terracotta is used only on buttons/links/badges/focus rings, never on body copy.

## Capabilities

### New Capabilities

- `design-system-palette`: cream/terracotta/clinical-teal/ink ramp + serif display family + semantic state colors locked across `tokens.js`, `tailwind.config.js`, `themes.css`, and `TokensModuleTest`.
- `motion-runtime`: tiny `useSpring` Vue composable on top of Web Animations API + CSS variables. Single source of motion tokens (`response`, `damping`, named easings).
- `reduced-motion-contract`: `prefers-reduced-motion`, `prefers-reduced-transparency`, `prefers-contrast` honored at every primitive.

### Modified Capabilities

- `theme-machinery`: dark-mode code paths removed (useTheme, ThemeSelector, themes.css dark blocks, Avatar.vue residue). `theme` localStorage keys migrated to no-op read.
- `asset-pipeline`: 314 MB of Pexels media moves from "untracked on disk" to "selectively committed, rest gitignored."

## Scope

### In Scope (Phase 1)

PR1 — Debt cleanup (visually inert, ≤250 lines)
- Delete `resources/js/components/ui/ThemeSelector.vue`.
- Delete `resources/js/components/layout/MobileNavigation.vue`.
- Collapse `useTheme.js` to a one-line no-op (delete the file).
- Delete `resources/js/utils/design-system.js`.
- Remove `* { transition: ... }` from `themes.css`; remove dark-mode blocks from `themes.css` and `Avatar.vue`.
- Migrate localStorage `theme` key → read-once, ignore value. Add a `TokensModuleTest` migration assertion that no new tokens are added under `dark` suffix.
- Touches: 5 files deleted, 3 files edited, 1 test extended. No user-visible change.

PR2 — Token layer + primitive restyle (≤600 lines)
- Replace palette in `tokens.js`: rename `primary` → `terracotta`, add `cream`, `ink`, `clinical-teal` ramps; keep `success/warning/error/info` semantic ramps with required 50/100/500/600/700 steps.
- Mirror CSS variables in `themes.css` (cream surface, ink text, terracotta accent, clinical-teal for medical states).
- Update `tailwind.config.js` to source from new `tokens.js`.
- Collapse `design-tokens.css` + `utilities.css` + `animations.css` into one `tokens.css` + one `utilities.css`. Net deletion of duplicate `@keyframes shimmer`, `.focus-ring`, `.slide-up`.
- Add `useSpring` composable + motion tokens (`response 0.3`, `damping 1.0`, `damping 0.8` for momentum).
- Restyle primitives: `Button`, `Card`, `Modal`, `Sheet`, `Input`, `Badge`, `Toast`, `Skeleton`, `LoadingSpinner`, `EmptyState`. Variants gain `variant="glass"` on Card. Default shape scale: 12/16/full.
- Touches: 4 CSS files, 1 JS config, 16 primitives, 1 new composable, 1 test extension.

PR3 — Login + Dashboard + 404 (≤550 lines)
- `LoginPage.vue` full rebuild: editorial split hero (left copy, right video still fallback), brand mark, single primary CTA, inline error, optical-size serif headline.
- `LoginCard.vue` deleted; folded into `Card variant="glass"`.
- `ForgotPasswordModal.vue` + `ResetPasswordModal.vue` restyled with new tokens; dev `reset_token` removed from UI flow (kept on the API surface for tests).
- `DashboardPage.vue` restyle: 5 stats + 5 quick actions + today's appointments rebuilt on new `Card` variants; inline `<style scoped>` block deleted; gradient classes replaced with flat tinted backgrounds.
- `NotFoundPage.vue` adopts `4439425_page-404_p3.jpg` and a single entrance spring.
- `AppLayout.vue` chrome restyle: translucent sidebar + top bar (Liquid-Glass web approximation, `prefers-reduced-transparency` fallback to solid); WS indicator, user menu, mobile sheet on new tokens. Inert visual elements (sidebar collapse, hamburger) preserved.
- New 404 image committed; login hero image (still fallback) committed; login hero mp4 video is OPT-IN (see asset policy).
- Touches: 6 page/component files, 1 deleted wrapper, 3 committed assets.

### Out of Scope (Phase 2+)

- Dark mode (permanently removed, not preserved).
- Sound design.
- Native iOS/Android wrapper.
- The other 17 modules (calendar, patients, appointments, billing, BI, etc.) — they inherit the new primitive layer and get restyled module-by-module after Phase 1.
- 401 hard-reload in `useApi.handleResponse` → new ticket `auth-session-expiry-toast`, explicitly deferred.
- Cookie/consent banner.
- Internationalization (Spanish copy stays).

## Approach

**A + filtered C** — rebuild tokens in place (Approach A) with Liquid-Glass web approximation only on chrome (Approach C, restricted). Rationale: existing `tokens.js` + `TokensModuleTest` is the leverage; primitive-level changes are unavoidable because Phase 1 must establish the language; web glass on chrome gives the Apple depth without breaking dense data legibility.

## Concrete decisions (locked)

| Question | Decision |
|---|---|
| **Slice boundaries** | PR1 ≤250, PR2 ≤600, PR3 ≤550 LOC. Chained PRs retarget per Section E. |
| **Dark-mode removal** | Delete `ThemeSelector.vue`, `useTheme.js`, `MobileNavigation.vue`, `design-system.js`. Remove `* { transition }` global. Remove `@media (prefers-color-scheme: dark)` blocks in `themes.css` and `Avatar.vue`. `localStorage.theme` reads as no-op on next access (no migration shim — ignore value). |
| **Token strategy** | `tokens.js` is SoT; `tailwind.config.js` imports it; `themes.css` mirrors CSS variables via a generated comment block; `TokensModuleTest` enforces parity for `cream/terracotta/clinical-teal/ink + success/warning/error/info` ramps. |
| **Palette (deliberate hex)** | `cream-50 #FAF9F7`, `cream-100 #F2EFE9`, `cream-200 #E8E3D8` (surface ramp). `ink-700 #2A2622`, `ink-800 #1F1B17`, `ink-900 #14110E` (text; AA against cream-50). `terracotta-500 #C96442`, `-600 #B05432` (accent; AA on cream-50 for large text and on cream-100 for body). `clinical-teal-500 #2C7A7B`, `-600 #226466` (medical states: appointment-confirmed, in-consultation, prescription-sent). `success/warning/error/info` ramps stay; `info` shifts from blue to clinical teal. |
| **Serif (free, optical sizing)** | `Newsreader` (Google Fonts, OFL, variable font with `opsz` axis). NOT `Fraunces` or `Instrument_Serif` (banned by `design-taste-frontend` §4.1). Fallback to `ui-serif, Georgia`. Sans stays the system stack. |
| **Contrast contract** | Body copy uses `ink-800` on `cream-50` (≈13.6:1, AAA). `terracotta-500` reserved for: primary CTA bg (white text), secondary CTA border + text, links on cream, focus rings, badges. **Never** body copy. Large display uses `ink-900` for max presence. Error states use `error-600` (`#DC2626`), not terracotta. |
| **Motion runtime** | Hand-rolled `useSpring` Vue composable on WAAPI + CSS variables. No new npm deps. `cubic-bezier(0.32, 0.72, 0, 1)` mapped to `damping 1.0`; `cubic-bezier(0.5, 1.5, 0.5, 1)` to `damping 0.8`. Entrance: spring `response 0.35, damping 1.0`. Momentum (sheet snap, toast dismiss): `response 0.3, damping 0.8`. Global `* { transition }` removed; explicit transitions only on hover/focus/active. |
| **Sound** | OUT for Phase 1. WebAudio unlock + opt-in affordance adds surface area with no Phase 1 user research. |
| **LoginCard vs Card** | Delete `LoginCard.vue`. Add `variant="glass"` to `Card.vue` (already has `variant="default"` + others); reuse for the login surface. |
| **401 hard-reload** | OUT of this change. Document as deferred ticket. New ticket: `auth-session-expiry-toast-2026-08` — soft `router.push('/login')` + `useToast.error('Sesión expirada')`, no `window.location.href` reload. |
| **Asset shipping (committed)** | `public/images/pexels/auth/login/6812463_modern-dental_p2.jpg` (56 KB, hero still fallback). `public/images/pexels/errors-404/4439425_page-404_p3.jpg` (16 KB). Login mp4 (`6528789_dental_v1.mp4` 7.0 MB or `6763242_clinic_v1.mp4` 6.1 MB) — **NOT committed by default**; only committed if Phase 1 needs video, and only one, after `ffmpeg` re-encode to H.264 720p ≤ 2 MB + JPEG poster frame extracted. The 71.7 MB `10189094_closeup_v1.mp4` and all other > 5 MB mp4s stay gitignored. Add `.gitignore` entries: `public/images/pexels/**/_v*.mp4` except the chosen login candidate. |
| **Reduced motion** | `useSpring` checks `window.matchMedia('(prefers-reduced-motion: reduce)').matches`; degrades to instant set. CSS `@media (prefers-reduced-motion: reduce)` removes spring entrance transforms, keeps opacity. |
| **Reduced transparency** | `backdrop-filter: none; background: cream-100;` on chrome surfaces. Tested in Safari + Firefox. |
| **High contrast** | `@media (prefers-contrast: more)` lifts text to `ink-900`, borders to `ink-700`, removes tints on badges. |

## Verification

Per-slice Playwright recipe (`C:/Users/chomb/.claude/skills/playwright-cli/SKILL.md`):

```bash
php artisan serve    # :8000
pnpm dev             # :5173 (or built static via php artisan view:cache)
```

PR1: `php artisan serve`, `pnpm dev`, manual: app loads identically. `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` exits 0. `grep -r "prefers-color-scheme: dark" resources/` returns nothing.

PR2: `playwright-cli open http://localhost:8000/login`, `snapshot`, `screenshot --filename=after-tokens.png`. Visual diff vs. baseline (pre-PR2 commit) — must be near-identical because tokens changed but no page logic did. `pnpm build` exits 0. New `TokensModuleTest` assertions pass: `cream`, `terracotta`, `clinical-teal`, `ink` ramps each have all required steps; parity with tailwind matches.

PR3 checkpoints:
1. `playwright-cli open http://localhost:8000/login` → screenshot `login-light.png`. Login card center, hero image right, terracotta primary button visible.
2. `prefers-reduced-motion: reduce` Playwright context → screenshot `login-reduced-motion.png`. No floating shapes; entrance is instant.
3. `prefers-reduced-transparency: reduce` → screenshot `login-reduced-transparency.png`. Chrome is solid cream-100, no blur.
4. `fill e1 "adm1n"` + `fill e2 "password123"` + `click e3` → expect `/dashboard`. Screenshot `after-login.png`.
5. `goto http://localhost:8000/dashboard` → screenshot `dashboard.png`. 5 stat cards, 5 quick actions, 3 appointments visible.
6. `goto http://localhost:8000/404` (or any unmatched route) → screenshot `not-found.png`. 404 image + back-to-login button visible.
7. `prefers-contrast: more` on dashboard → screenshot `dashboard-high-contrast.png`. Text ink-900, borders ink-700.

Visual-regression baseline: commit pre-PR2 commit screenshots into `tests/Visual/baselines/` (PNG, ≤200 KB each, byte-stable via `pnpm vitest --update`). PR2 must match baseline; PR3 replaces it with new baselines.

TDD coverage: `tests/Unit/DesignSystem/TokensModuleTest.php` extended with: cream ramp steps, terracotta ramp steps, clinical-teal ramp steps, ink ramp steps, hex format check, parity check vs `tailwind.config.js`, dark-suffix absence check. Source-inspection check: `resources/css/themes.css` must contain exactly one `:root` block, no `@media (prefers-color-scheme: dark)` blocks.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `openspec/changes/ui-redesign-apple-claude-2026-08/{exploration,proposal}.md` | New | This file + mirrored exploration. |
| `resources/css/themes.css` | Modified | New palette CSS vars; remove dark blocks; remove `* { transition }`. |
| `resources/css/{design-tokens,utilities,animations}.css` | Modified | Collapse into one tokens + one utilities file. |
| `resources/css/app.css` | Modified | Imports updated. |
| `tailwind.config.js` | Modified | Re-source from `tokens.js`. |
| `resources/js/design-system/tokens.js` | Modified | New ramps: cream, terracotta, clinical-teal, ink. |
| `resources/js/composables/useSpring.js` | New | Vue composable on WAAPI + CSS variables. |
| `resources/js/composables/useTheme.js` | Removed | Dark-mode machinery deleted. |
| `resources/js/components/ui/ThemeSelector.vue` | Removed | Dead UI. |
| `resources/js/components/layout/MobileNavigation.vue` | Removed | Dead nav. |
| `resources/js/components/auth/LoginCard.vue` | Removed | Folded into Card variant. |
| `resources/js/utils/design-system.js` | Removed | Stale duplicate. |
| `resources/js/components/ui/{Button,Card,Modal,Sheet,Input,Badge,Toast,Skeleton,LoadingSpinner,EmptyState,Avatar,Breadcrumbs,Tabs,ConfirmDialog,NotificationToast}.vue` | Modified | New palette + tokens; reduced-motion wiring. |
| `resources/js/components/layout/{AppLayout,PageHeader,MobileMenu,FloatingActionButton}.vue` | Modified | Chrome restyle. |
| `resources/js/components/{NotificationCenter,ToastContainer}.vue` | Modified | New tokens. |
| `resources/js/modules/auth/{LoginPage,ForgotPasswordModal,ResetPasswordModal}.vue` | Modified | Login full rebuild; modals restyled. |
| `resources/js/modules/dashboard/DashboardPage.vue` | Modified | Stats + quick actions + appointments on new primitives. |
| `resources/js/modules/errors/NotFoundPage.vue` | Modified | 404 image + entrance. |
| `public/images/pexels/auth/login/6812463_modern-dental_p2.jpg` | New (committed) | Login hero fallback still. |
| `public/images/pexels/errors-404/4439425_page-404_p3.jpg` | New (committed) | 404 image. |
| `public/images/pexels/auth/login/6763242_clinic_v1.mp4` | New (committed, optional) | Login hero video, re-encoded. |
| `public/images/pexels/**/_v*.mp4` | gitignored | Heavy mp4s never ship. |
| `tests/Unit/DesignSystem/TokensModuleTest.php` | Modified | Extended assertions for new ramps + dark-suffix absence. |
| `tests/Visual/baselines/*.png` | New | Pre-PR2 visual baseline. |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Primitive restyle visual diff alarms reviewers on the 17 un-migrated modules | High | PR2 keeps the SAME class names (`bg-accent`, `bg-theme-surface-elevated`, etc.); only the rendered values change. Diff is CSS variable re-definitions, not class churn. |
| Token parity drift between `tokens.js` and `themes.css` (already happened with iCloud blue) | Medium | `TokensModuleTest` parses both files and asserts equality on every step. |
| `prefers-reduced-transparency` uneven browser support | Medium | Solid fallback (`cream-100`) is the same contrast class, not a degraded class. Manual test in Firefox + Safari. |
| Cream + terracotta reads as craft-cookware, not clinical | Medium | Restraint: terracotta is button/link/badge only. Body copy uses ink ramp. The visual identity is "calm + warm" not "rustic." |
| Login mp4 chosen then re-encoded poorly | Low | Phase 1 commits only the still fallback. Video is opt-in, gated on PR3 review. |
| 401 hard-reload not addressed → motion interrupted on session expiry | Medium | Documented as deferred ticket; not in this change. |
| `* { transition: ... }` removal causes visible color flicker on first paint | Low | Same global is replaced by scoped transitions on Button/Card/Input hover. |
| Print styles in `app.css` regress | Low | `app.css` imports only updated; print block untouched in PR2/PR3. |

## Rollback Plan

Per PR (each is independently revertible):
- **PR1** — `git revert <sha>`. Five files restored; no DB; no API contract change. App returns to dead-code-bearing state.
- **PR2** — `git revert <sha>`. CSS variables, tokens, primitive styles all revert together. `TokensModuleTest` reverts to old assertions; must revert the test in the same commit.
- **PR3** — `git revert <sha>`. Login + Dashboard + 404 revert to old visuals. Primitives stay on new tokens (PR2 already merged); reverts cleanly because primitives are token-driven, not hardcoded.

## Dependencies

- None (no new npm or composer packages).
- `ffmpeg` available locally for optional login mp4 re-encode (dev tool only; not a runtime dep).

## Success Criteria

- [ ] PR1, PR2, PR3 each merge with `vendor/bin/phpunit` exit 0 and `pnpm build` exit 0.
- [ ] `TokensModuleTest` extended assertions pass.
- [ ] Playwright PR3 checkpoints 1-7 produce the screenshots described.
- [ ] WCAG AA contrast verified for `ink-800` on `cream-50` and `terracotta-500` on `cream-100` (body button text).
- [ ] `grep -r "prefers-color-scheme: dark" resources/` returns nothing.
- [ ] `grep -r "ThemeSelector\|useTheme\|design-system.js" resources/` returns nothing (excluding test fixtures).
- [ ] `pnpm lint:check` exit 0.
- [ ] No `git ls-files | grep _v1.mp4` outside the chosen login candidate (if committed).
