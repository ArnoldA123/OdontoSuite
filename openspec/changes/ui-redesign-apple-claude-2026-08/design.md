# Design: ui-redesign-apple-claude-2026-08

> Change: `ui-redesign-apple-claude-2026-08`. Phase: `sdd-design`. Read alongside `proposal.md`, `exploration.md`, and the user-added constraint that `Newsreader` is **self-hosted, OFL, variable with the `opsz` axis**, fallback `ui-serif, 'New York', Georgia, serif`. No Google Fonts CDN. ~40 KB budget. A parallel `sdd-spec` agent owns `spec.md`; this document owns the architectural HOW and the data-flow, layout, and token plumbing.

## Technical Approach

Phase 1 ships a "language" not a "theme": one source of truth (`tokens.js`), one CSS variable surface, 16 primitives, two vertical exemplars (Login + Dashboard + 404), and one motion runtime. The contract the proposal locks — a redesign-overhaul in place, light-only, Apple chassis + Claude soul, terracotta as accent and clinical teal for medical state semantics, three chained PRs — drives the architecture below.

The four load-bearing decisions, in the order they constrain everything else, are: (1) `tokens.js` SoT with a build-time CSS-var emitter so `themes.css` cannot drift, (2) WAAPI keyframe regeneration is rejected in favor of an rAF loop driving CSS custom properties for spring motion, (3) class names on primitives are preserved and only rendered values change, (4) one `fonts/` directory with self-hosted Newsreader variable `.woff2` and a `font-display: swap` + metric-compatible fallback to keep the existing 17 un-migrated modules visually intact until they get touched.

## Architecture Decisions

### Decision 1 — Token pipeline: `tokens.js` is SoT, CSS vars generated, no human-written `:root` mirror

**Choice.** `resources/js/design-system/tokens.js` remains the single source of truth. Tailwind continues to import named exports from it (`colors`, `spacing`, `radius`, `fontFamily`, `fontSize`, `shadow`). A new `scripts/build-tokens-css.mjs` (Node, no Vite plugin) reads `tokens.js` via dynamic import, walks the same surface, and **emits** `resources/css/tokens.generated.css` containing exactly one `:root { ... }` block, one `@media (prefers-reduced-transparency)`, one `@media (prefers-contrast: more)`, and the semantic-variable aliases (`--color-accent`, `--color-surface-elevated`, `--glass-bg`, `--motion-damping-100`, etc.) consumed by Vue `<style>` blocks and `tailwind.config.js` `addUtilities`. The generated file is **not hand-edited** and is checked into git (reviewable diff, no runtime dependency on Node). The CI guard is `tests/Unit/DesignSystem/TokensModuleTest.php` extended to:
- Load the generated CSS, extract the `:root` block, parse `--color-*` declarations, and assert each maps to a `tokens.colors.*.*` entry (current parity check widened from `tailwind.config.js` to also include the generated CSS).
- Assert exactly **one** `:root` block in `resources/css/tokens.generated.css`.
- Assert absence of `--*-dark` / `@media (prefers-color-scheme: dark)` anywhere under `resources/css/` and `resources/js/`.
- Assert absence of any hex literal in `resources/css/tokens.generated.css` that is not also in `tokens.js` (catches the iCloud-blue drift the exploration found).

`resources/css/themes.css` is **deleted**; its 300 LOC of semantic aliases move into the generator's output, names preserved (`--color-accent`, `--color-surface-elevated`, `--glass-bg`, `--shadow-md`, etc.). No alias name changes: Vue files that read `var(--color-accent)` keep working.

**Alternatives considered.**
- *Hand-written `themes.css` mirror, tested by the unit suite.* Rejected: the iCloud-blue bug already happened exactly this way. The unit test is a fail-late check, not a prevent. Drift returns the moment a contributor hand-edits one value and forgets to run the test.
- *Tailwind plugin (`@layer base { :root { ... } }` in `tailwind.config.js`).* Rejected: forces Tailwind at runtime; makes the generated CSS invisible to `<style>` blocks and to `addUtilities` consumers that already pattern-match raw `--color-*` names. Also makes the `npm run build` output the only canonical artifact, which means a `git diff` cannot show the token change.
- *CSS `@property` registration for typed custom props.* Rejected: nice-to-have, not Phase 1. Will land with the spring runtime (Decision 2) since `@property` gives rAF-driven CSS vars a typed value, but the generator can be plain `string` today and the consumer stays the same.

**Rationale.** Emitting the CSS file from the same module the build already imports guarantees `tokens.js` is the only place a hex value lives. The unit test becomes a parity check between two derived outputs (the `tailwind.config.js` view and the `tokens.generated.css` view), not a hand-rolled mirror that diverges the moment humans touch it.

### Decision 2 — Motion runtime: rAF loop driving CSS custom properties (rejected WAAPI keyframe sampling)

**Choice.** `resources/js/composables/useSpring.js` is a Vue 3 composable that maintains one or more per-axis `Ref<{ value, velocity }>` and drives a single `requestAnimationFrame` loop that updates `--spring-x` and `--spring-y` custom properties on the bound element. The element's `transform: translate3d(var(--spring-x, 0), var(--spring-y, 0), 0)` and `opacity: var(--spring-o, 1)` read those variables — both compositor-friendly, so the work is paint-only after setup. The API:

```js
// Single-axis (one DOMRect axis, opacity, scale):
const spring = useSpring({ response: 0.35, damping: 1.0, from: 0 })
spring.set(120)                       // -> animate to 120
spring.set(120, { velocity: 800 })    // -> handoff from a flick
spring.stop()                        // -> hard stop, keeps current value

// 2D motion: returns independent X and Y springs, no 2D-distance math.
const { x, y } = useSpring2D({ response: 0.35, damping: 1.0 })
x.set(120, { velocity: vx }); y.set(-40, { velocity: vy })
x.set(nearestSnap(projectedTarget), { velocity: x.velocity.value })
```

Internals: fixed-step 1/60 s Euler-ish integration of a damped harmonic oscillator (`a = -k·(x - target) - c·v`), with `k` and `c` derived from `response` and `damping` via the same transform Motion/Framer uses (`omega0 = 2π / response`, `zeta = damping`; `bounce < 1` → underdamped). Reading the live presentation value is implemented with `el.getBoundingClientRect()` and the rect delta, exactly once on interrupt — not on every frame.

**WAAPI mapping rejected.** The proposal notes two runtimes and asks for the choice defended. WAAPI (`element.animate({ transform: [...] }, { duration, easing, fill })`) cannot be re-targeted mid-flight without calling `animate()` again, which the Web Animations spec treats as a *new* animation; in Chromium the new animation inherits the old one's `currentTime`, but in Safari and Firefox it can either restart from frame 0 or jump to the new end. That is the brick-wall moment the Apple contract calls out. Re-targeting needs: (a) cancel the running animation, (b) compute the live `getComputedStyle` value as the new `from`, (c) build a new keyframe array, (d) `animate()` again. Per interrupt, this is ~0.4–1.2 ms of main-thread work plus a paint. For a sheet the user grabs mid-flight, it is the correct cost. For a 60 Hz dashboard stat that fires from a WebSocket, the cost is fine. **But** for 16+ simultaneous springs (e.g. several stat cards animating in after a debounced WS burst), each interrupt regenerates keyframes; the centralization on the main thread is the same as the rAF loop. The decisive reason to choose rAF is the velocity handoff: rAF reads `velocity` from the integrator's last frame, WAAPI has to query `getComputedStyle` and finite-difference two frames to recover velocity. rAF is one line; WAAPI is a state machine.

The rAF loop costs ~0.05–0.15 ms per spring per frame (one multiply-add for the integrator, one `style.setProperty`); even with 20 active springs in the worst case (sheet + cards + toast dismiss) that is 1–3 ms per frame, well under the 16.7 ms budget on a mid-tier laptop. The loop is shared across all spring instances on the same page via a module-scoped subscription set, so we do not start 20 separate rAF intervals.

**Reduced motion / reduced transparency / high contrast.** A `useSpring` instance calls `window.matchMedia('(prefers-reduced-motion: reduce)').matches` once on construction; if true, `set(target)` writes the target value directly with no animation (one frame, one paint). For entries that pair a slide + opacity, the reduced-motion path sets the final transform and a one-frame opacity cross-fade. The CSS side of the spring is gated by `@media (prefers-reduced-motion: reduce) { [data-spring] { transition: opacity 120ms ease; } }` so any `transform` writes inside the loop become no-ops while opacity still tweens. A separate media query is mounted on the glass chrome (`@media (prefers-reduced-transparency: reduce)` → `backdrop-filter: none; background: var(--color-cream-100);`) and on the high-contrast variant (`@media (prefers-contrast: more)` → borders jump to `ink-700`, text to `ink-900`). All three media queries live in the generator output so they are part of the token contract, not scattered.

**Momentum projection.** `useSpring2D` exposes `projectAndSnap(velocity, snapPoints, decelerationRate = 0.998)`:

```js
const projected = current + (velocity / 1000) * decelerationRate / (1 - decelerationRate)
const target = snapPoints.reduce((a, b) => Math.abs(b - projected) < Math.abs(a - projected) ? b : a)
return target
```

**Why this is the central technical decision of the change.** Every gesture-driven surface the change touches — the bottom sheet, the toast swipe-to-dismiss, the LoginPage's card entrance, the 404 page's image entry, the sidebar's collapse animation — funnels through `useSpring`. The composable's design (rAF + CSS vars + per-axis independence + momentum projection) is the difference between a system that feels "fine" and one that feels Apple-native. All other motion in Phase 1 is CSS-only (entrance keyframes, hover transitions) so a regression in `useSpring` does not silently break the rest of the design.

### Decision 3 — Token architecture: ramp shape, font surface, motion surface

**Choice.** The new `tokens.js` keeps the existing `colors/spacing/radius/typography/shadow/breakpoint` shape (it is the public surface; the Tailwind import path and the unit test both read those names). The color ramps rename and extend:

| Ramp | Steps | Hex (light) | Where it lands |
|---|---|---|---|
| `terracotta` (was `primary`) | 50/100/200/300/400/500/600/700/800/900 | 50 `#FBEEE7`, 100 `#F4D9C7`, 200 `#E9B89E`, 300 `#DD9775`, 400 `#D27A52`, **500 `#C96442`**, 600 `#B05432`, 700 `#8C3F25`, 800 `#652C1B`, 900 `#3F1A11` | CTA bg, link, focus ring, badge accent, button border on cream-50. Never body. |
| `cream` | 50/100/200/300 | 50 `#FAF9F7`, 100 `#F2EFE9`, 200 `#E8E3D8`, 300 `#D8D1C0` | Surface ramp. `bg-cream-50` is the page background. `bg-cream-100` is card surface. `bg-cream-200` is divider. `bg-cream-300` is decorative. |
| `ink` | 50/100/200/300/500/600/700/800/900 | 50 `#F7F5F2`, 100 `#DAD5CD`, 200 `#B0A99D`, 300 `#847C6E`, 500 `#5A5247`, 600 `#423A30`, **700 `#2A2622`**, **800 `#1F1B17`**, **900 `#14110E`** | Text ramp. Body uses `ink-800` on `cream-50` (~13.6:1, AAA). Display uses `ink-900`. Borders use `ink-200` (subtle) and `ink-300` (strong). |
| `clinicalTeal` | 50/100/300/500/600/700 | 50 `#E6F2F2`, 100 `#C8E0E0`, 300 `#74B4B4`, **500 `#2C7A7B`**, 600 `#226466`, 700 `#1A4F51` | Medical-state semantics: appointment-confirmed, in-consultation, prescription-sent. Never body. |
| `success` / `warning` / `error` | keep 50/100/300/500/600/700/900 | unchanged from existing | Semantic non-medical states. `info` collapsed into `clinicalTeal-500` (the proposal decision). |

The `info` ramp is deleted from `colors.info`; the unit test stops asserting it, and any code that referenced `colors.info.500` is updated to `colors.clinicalTeal.500`. Audit grep: `info-500` / `--color-info` shows up in `tokens.js` 0 times today but 3 times in `themes.css`; both clear out under the regenerated CSS.

The `typography` object gains a `fontFamily.serif` and a `fontSize` table with **per-step `letterSpacing` and `lineHeight`** inside the existing `fontSize[x] = [size, { lineHeight, letterSpacing }]` tuple shape Tailwind already supports:

| Key | Size | Line height | Tracking | `font-optical-sizing` |
|---|---|---|---|---|
| `xs` | 12 px | 16 | 0.01em | `auto` |
| `sm` | 13 px | 18 | 0 | `auto` |
| `base` | 15 px | 22 | 0 | `auto` |
| `lg` | 17 px | 24 | 0 | `auto` |
| `xl` | 20 px | 28 | -0.01em | `auto` |
| `2xl` | 24 px | 32 | -0.015em | `auto` |
| `3xl` | 30 px | 36 | -0.02em | `auto` |
| `4xl` | 36 px | 40 | -0.025em | `auto` |
| `display` | 48 px | 48 | -0.03em | `auto` |
| `hero` | 64 px | 64 | -0.035em | `auto` |

`fontFamily.sans` is unchanged (system stack, per the proposal). `fontFamily.serif` is `['Newsreader', 'ui-serif', 'New York', 'Georgia', 'serif']`. The opt-in class `.font-serif` on a heading element reads it; nothing else does.

A new top-level `motion` section on the tokens module exports `damping`, `response`, `stiffness`, `easings` and is consumed only by `useSpring` and by the generator (which emits `--motion-damping-1: 1.0;`, `--motion-damping-bounce: 0.8;`, `--motion-response-default: 0.35;`, etc.). This is what unblocks the unit test from also asserting motion-token presence.

**Rationale.** Negative tracking on large display is the Apple rule the proposal commits to; positive tracking on small text keeps it legible. A single `letter-spacing` value is wrong somewhere; the table above is the single value-per-size. The `font-optical-sizing: auto` declaration is only meaningful on a variable font with the `opsz` axis — Newsreader has it. Without the opt-in, the variable font ships at `opsz=16` and never re-shapes for 64 px headlines.

### Decision 4 — CSS file consolidation: five files → two

**Choice.** Phase 1 collapses the five `resources/css/*.css` files into two and adds the generator output. Final import graph, top to bottom in `app.css`:

```css
/* resources/css/app.css */
@import './tokens.generated.css';     /* generated from tokens.js — :root vars, semantic aliases, motion vars, media queries */
@import './utilities.css';            /* hand-maintained: z-index, ripple, focus, scrollbar, safe-area, reduced-motion overrides */

@tailwind base;
@tailwind components;
@tailwind utilities;

@layer utilities { .apple-shadow, .container-responsive, .grid-responsive, .btn-touch, .glass-card } /* kept from current app.css */

@media print { ... } /* preserved verbatim from current app.css */
```

Moves and deletes:

| File | Action | Where it goes |
|---|---|---|
| `design-tokens.css` (265 LOC) | **Delete** | The `:root` block overlaps `themes.css`; the `.ds-*` classes are unused outside the file. Unit test asserts 0 references. |
| `themes.css` (300 LOC) | **Delete** | Generated. The semantic aliases (`--color-accent`, `--color-surface-elevated`, `--glass-bg`, `--shadow-md`, `--motion-*`, etc.) live in `tokens.generated.css` from the same module that emits Tailwind. |
| `animations.css` (296 LOC) | **Delete** | `@keyframes` and Vue transition classes duplicate what `useSpring` and the new entrance class set in `utilities.css` produce. The two genuine non-duplicates (`@keyframes spin` for `LoadingSpinner`, `@keyframes pulse-subtle` for the WS indicator) move to `utilities.css`. |
| `utilities.css` (272 LOC) | **Keep, slim** | Drop `@keyframes shimmer` (now lives in the spinner-only section), drop `@keyframes slideUp` (only used by LoginPage's deleted `slide-up` entrance; LoginPage redesign owns its own keyframe), drop `.ds-*` duplicates. Net ~150 LOC. |
| `app.css` (186 LOC) | **Keep, update imports** | The print block is untouched. The `apple-shadow` utilities inside `@layer utilities` are kept. |

**Print block preservation.** The `@media print` block in the current `app.css` (which `cash-report-pdf`, `quotation`, `receipt` blades consume) stays byte-identical. The unit test asserts `app.css` contains the `summary-box` and `cash-reports` selectors after the change. The `theme`, `animations`, and `design-tokens` files do not contain print rules.

**Rationale.** Five files with duplicate `@keyframes` is a tax with no benefit. Two files (one generated, one hand-maintained) plus Tailwind layers is the minimum surface that keeps the primitives working. The print block and the `apple-shadow` utilities are the only two hand-rolled clusters worth keeping; everything else is generated or already covered by Tailwind utilities.

### Decision 5 — Liquid-Glass chrome: web approximation on chrome only; data cards stay opaque

**Choice.** Only three surfaces get the Liquid-Glass web approximation: `AppLayout` desktop sidebar, `AppLayout` top bar, and the mobile `Sheet` wrapper around the mobile menu. Everywhere else — including the `UiCard` data surface used by 76 call sites across 21 module files (verified `grep -rn 'variant="glass"' resources/js --include='*.vue' | wc -l` = 76 across `ai-analysis`, `appointment-types`, `appointments/CalendarPage`, `business-intelligence`, `cash-register`, `dashboard`, `environments`, `my-procedures`, `patients`, `procedure-catalog`, `professionals`, `reception-procedures`, `settings/branches`, `settings/payment-methods`) — stays **opaque**.

**The naming tension, resolved by option 1.** Today, `UiCard variant="glass"` is the default elevated data-card look. Renaming the variant or migrating 76 call sites would blow the PR2 budget and contradict the "byte-identical class names" compat strategy. So `variant="glass"` keeps its current role and the chrome gets a new, distinct class name. The `glass` qualifier on the data card becomes a historical misnomer (it once meant iCloud-blue translucent in the previous design); a `// Historical name: kept for compat. See surface-glass for chrome translucent surfaces.` comment in `Card.vue` absorbs the confusion. The unit test asserts that no `backdrop-filter` declaration exists in `Card.vue`.

**Chrome class (new, chrome-only).** The generated CSS ships a `.surface-glass` class (with the matching `--glass-*` vars it reads from the generator). The class is a well-labeled web approximation of Apple's Liquid Glass material; the comment above the class explicitly states "web approximation, not official Apple Liquid Glass — see design-taste-frontend §Appendix C". Values:

```css
/* Generated. Do not hand-edit. Web approximation, not official Apple Liquid Glass. */
.surface-glass {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  background:
    linear-gradient(135deg, rgb(250 249 247 / 0.78), rgb(250 249 247 / 0.62));
  backdrop-filter: blur(20px) saturate(180%) contrast(1.04);
  -webkit-backdrop-filter: blur(20px) saturate(180%) contrast(1.04);
  border-right: 1px solid rgb(31 27 23 / 0.06);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.55),     /* top highlight */
    inset 0 -1px 0 rgb(31 27 23 / 0.04),       /* bottom shadow */
    0 18px 40px -16px rgb(31 27 23 / 0.10);    /* depth shadow */
}
.surface-glass::after {                       /* inner edge refraction */
  content: ""; position: absolute; inset: 0; pointer-events: none;
  border-radius: inherit;
  border: 1px solid rgb(255 255 255 / 0.18);
  mix-blend-mode: overlay;
}
@media (prefers-reduced-transparency: reduce) {
  .surface-glass { background: var(--color-cream-100); backdrop-filter: none; -webkit-backdrop-filter: none; box-shadow: none; }
}
```

**Data card `variant="glass"` (redefined, opaque).** `UiCard variant="glass"` becomes an opaque surface — `bg-cream-100`, `1px` `border-ink-200` hairline, `shadow-medium`, `rounded-xl`, no `backdrop-filter`. The other variants stay as they are (`default` = elevated cream, `flat` = no-shadow surface, `elevated` = heavy shadow, `outlined` = 2 px border). Concretely, the new `glass` variant paints the same as a data card is supposed to look in the redesign: warm cream surface, hairline border, soft shadow, dense content (medical-record tables, cash amounts, patient counts) reads cleanly because the background is not translucent.

**Scroll-edge treatment.** Where the floating chrome meets scrolling content (the bottom of the top bar, the right edge of the sidebar), a `mask-image: linear-gradient(to bottom, #000 calc(100% - 24px), transparent)` fades the chrome's edge into the page rather than drawing a 1 px border. The `mask` is on the chrome's first content wrapper, not on the chrome itself, so it does not affect hit-testing.

**What stays solid.** All `UiCard` variants (default, glass, flat, elevated, outlined — all opaque), table rows, modals over content (`UiModal` background is `cream-50` solid + a dimmed scrim), all `EmptyState` illustrations, every `StatusPill`, every toast, every `LoadingSpinner`. The proposal's "A + filtered C" claim is preserved: glass on chrome, solid on data.

**Rationale.** Stacking glass on glass is the failure mode the design-taste-frontend skill calls out specifically, and putting `backdrop-filter: blur(12px)` on a data card that displays a cash balance would make the text behind it (e.g. a calendar event from a previous module bleeding through) unreadable. By splitting into `.surface-glass` (chrome-only, opt-in) and `variant="glass"` on `Card.vue` (opaque data surface, 76 callers), the design gets the Apple depth on chrome where it pays off, keeps dense data legibility everywhere else, and preserves the 21-file compat strategy. A future contributor who wants to add a new glass surface must opt in to the `.surface-glass` class and read the comment that explains the approximation. A future contributor who thinks the data card should blur must read the `Card.vue` comment explaining why it does not.

### Decision 6 — Typography: self-hosted Newsreader variable, FOUT strategy

**Choice.** One variable `Newsreader-VariableFont_OPSZ,wght.woff2` (Newsreader Variable, OFL) is committed to `public/fonts/`. Two CSS files declare it:

- `resources/css/tokens.generated.css` emits a `@font-face` block at the top of the file so the face ships in the same CSS as the tokens it serves. The `src` references `/fonts/Newsreader-VariableFont_OPSZ,wght.woff2` (single file, two axes). `font-display: swap` is the explicit value (not the default) so the FOUT behavior is documented in code.
- The fallback chain `'ui-serif', 'New York', Georgia, serif` matches the metrics of Newsreader's `opsz=16` axis reasonably: same x-height, similar cap-height, similar advance widths. The `--font-serif` and `--font-sans` CSS vars are emitted as `Newsreader, ui-serif, ...` and `-apple-system, BlinkMacSystemFont, ...` respectively.

`font-feature-settings: "kern" 1, "liga" 1;` is on the base `<body>` rule, plus `font-optical-sizing: auto` on every serif surface. The element-level class `.font-serif` is the only opt-in. No file other than the generator, the typography test, and a few heading elements set it.

**Sizing + tracking + leading.** Decision 3's per-step tracking table is the source. The generator emits one Tailwind utility per step: `.text-xs { font-size: 12px; line-height: 16px; letter-spacing: 0.01em; }`, `.text-hero { font-size: 64px; line-height: 64px; letter-spacing: -0.035em; }`, etc. The existing `text-3xl` etc. utilities are replaced. (Tailwind's defaults are kept for anything not in our table; the unit test asserts no `letter-spacing` literal exists in any Vue file that is not in the generator.)

**FOUT mitigation.** Three layers, in order:
1. `font-display: swap` so the fallback text paints immediately.
2. Metric-matched fallback (`ui-serif` / New York / Georgia / serif) so layout shift is minimal.
3. A `useFontsLoaded` composable (in `composables/useFontsLoaded.js`) returns a `ref<boolean>` that flips true on `document.fonts.ready`. The `<html>` element gets `data-fonts-loaded="true"` once it resolves, and a one-line CSS rule on `[data-fonts-loaded="true"] .font-serif` adjusts the fallback `font-size` and `line-height` by `-0.5%` each — a small adjustment that prevents the visible jump if Newsreader ends up slightly wider than the system serif. The unit test asserts the data attribute is set after `document.fonts` resolves.

**Rationale.** Clinical app, must work offline, no CDN, no Google Fonts request. OFL means redistribution is fine. Variable `opsz` is what the proposal commits to; without it, the headline shape is identical to the body shape and the serif looks generic.

## Data Flow

Three flows matter for Phase 1: login, dashboard WebSocket burst, and reset-password.

### Login flow

`LoginPage.vue` → `useAuth().login(form)` → `useApi().post('/api/auth/login', { username, password, remember })` → `AuthController::login` → token returned. State machine on the page (kept explicit so the entrance choreography has stable timing):

```
IDLE  ──submit──► VALIDATING ──ok──► SUBMITTING ──ok──► SUCCESS ──push /dashboard──► [DASHBOARD]
                  │                                       │
                  │                                       └─err──► ERROR (form re-enables, error msg cross-fades in)
                  └──invalid──► IDLE (inline field errors)
```

The `* { transition: ... }` global is gone, so the cross-fade on error and the entrance spring on the card are both driven by Vue `<Transition>` and the new `useSpring` entrance. The shape of the form is unchanged (username + password + remember + forgot link), but the `LoginCard` wrapper is replaced with `<UiCard variant="glass" padding="lg" class="login-card">` and the three animated `div.shape` blobs in the current `LoginPage` are deleted (the calm/clinical reading does not need them).

### Dashboard WebSocket flow

```
Reverb                          useEcho (singleton)                   DashboardPage
── appointment.created ──►  channel('appointments')                .listen('.appointment.created', e => { if same-day -> debouncedLoadDashboardData() })
── cash-session.opened ──► channel('cash-register')                 .listen('.cash-session.opened',    () => { loadCurrentSession(); debouncedLoadDashboardData() })
── payment.registered ──►  channel('cash-register')                 .listen('.payment.registered',    () => { loadCurrentSession(); debouncedLoadDashboardData() })
                                                                            │
                                                                            ▼
                                                                     debounce 300 ms
                                                                            │
                                                                            ▼
                                                                     GET /api/dashboard/stats
                                                                     GET /api/dashboard/appointments-today
                                                                            │
                                                                            ▼
                                                                     Reactively update stats / todayAppointments
                                                                     stat cards spring from current value to new value
```

Critical: the 300 ms debounce is preserved (the existing code at `DashboardPage.vue:477-486` already does it) and the stat cards enter with `useSpring({ response: 0.35, damping: 1.0 })` only on **first paint**; subsequent value changes use `spring.set(newValue, { velocity: 0 })` which is a tween, not an entrance. This is what the Apple contract means by "the entrance motion does not fight the update motion."

The `cashStatusClass` / `cashStatusIconClass` / `cashStatusText` / `cashStatusIconColor` quadruple computed in the current `DashboardPage.vue:374-396` collapses to one `<UiStatusPill :status="cashStatusPillStatus" :show-dot="true" />` where `cashStatusPillStatus` is a single `ref('open' | 'closed' | 'no_session')`. `UiStatusPill` already maps these to a `variant` + `label` internally. The proposal's "replace the triplet with a proper primitive" is implemented as a 4-line computed + one component tag.

### Reset-password flow (with the dev-token removed from UI)

`LoginPage` → ForgotPasswordModal → `POST /api/auth/forgot-password` → server returns `{ data, meta, debug?: { token } }` (token only when `APP_DEBUG=true`). The modal currently shows the token inline. The fix: when `data?.token` is present, emit `success` with the email only; do not display the token, do not auto-open the reset modal. The reset modal is opened by the user clicking a separate link in the success state ("¿Ya tienes el código? Restablecer contraseña"). The dev token stays on the API surface for test fixtures; it just does not reach the UI. Backend is unchanged.

## File Changes

| File | Action | Description |
|---|---|---|
| `resources/js/design-system/tokens.js` | Modify | New ramps (`terracotta`, `cream`, `ink`, `clinicalTeal`), `info` deleted, `typography.fontFamily.serif` + per-step `letterSpacing`, new `motion` section. Export surface unchanged. |
| `tailwind.config.js` | Modify | Re-import from the new `tokens.js`. Remove `info` references. Wire `addUtilities` to the generated `--color-*` aliases. |
| `scripts/build-tokens-css.mjs` | **Create** | Node script: imports `tokens.js`, emits `resources/css/tokens.generated.css`. No Vite plugin. Documented in `package.json` as `pnpm tokens:build`. CI runs it in a pre-test step. |
| `resources/css/tokens.generated.css` | **Create (generated)** | Emitted by the script. Hand-edits forbidden. |
| `resources/css/design-tokens.css` | **Delete** | Drift-cleanup. Unit test asserts 0 references. |
| `resources/css/themes.css` | **Delete** | Replaced by generated CSS. |
| `resources/css/animations.css` | **Delete** | Duplicates collapsed. |
| `resources/css/utilities.css` | Modify | Drop duplicates; keep `.spinner-ring` keyframes, `.pulse-subtle` keyframes, `.surface-glass` is NOT here (it lives in generated). |
| `resources/css/app.css` | Modify | Update imports to `tokens.generated.css` + `utilities.css`. `@media print` byte-identical. |
| `resources/js/composables/useSpring.js` | **Create** | Decision 2. ~180 LOC. |
| `resources/js/composables/useSpring2D.js` | **Create** | Independent X/Y springs + momentum projection. ~60 LOC. |
| `resources/js/composables/useTheme.js` | **Delete (PR1)** | Already no-op; replaced by a one-line no-op file `useTheme.js` that returns `{ theme: 'light' }`. |
| `resources/js/components/ui/ThemeSelector.vue` | **Delete (PR1)** | Dead. |
| `resources/js/components/layout/MobileNavigation.vue` | **Delete (PR1)** | Dead. |
| `resources/js/components/auth/LoginCard.vue` | **Delete (PR3)** | Replaced by `<UiCard variant="glass" ...>` in `LoginPage`. |
| `resources/js/utils/design-system.js` | **Delete (PR1)** | Stale duplicate. |
| `resources/js/composables/useFontsLoaded.js` | **Create** | `useFontsLoaded()` returns `ref<boolean>`, flips on `document.fonts.ready`. |
| `resources/js/components/ui/Card.vue` | Modify | Redefine `variant="glass"` as an **opaque** cream-100 surface with a 1 px `ink-200` hairline border and `shadow-medium`; no `backdrop-filter`. Name kept for compat with the 76 `variant="glass"` call sites across 21 module files. A `// Historical name: kept for compat. See surface-glass for chrome translucent surfaces.` comment in `<script setup>` prevents future "let's rename it" churn. The `default`, `flat`, `elevated`, `outlined` variants are unchanged in semantics; only the rendered values (`cream-100` / `ink-200` / `shadow-medium`) change. |
| `resources/js/components/ui/{Button,Modal,Sheet,Input,Badge,Toast,Skeleton,LoadingSpinner,EmptyState,Avatar,Breadcrumbs,Tabs,ConfirmDialog,NotificationToast}.vue` | Modify | Token-driven, no class-name renames. Detail in section "Primitive component contracts" below. |
| `resources/js/components/layout/{AppLayout,MobileMenu,PageHeader,FloatingActionButton}.vue` | Modify | Sidebar + top bar use `.surface-glass`. Mobile sheet keeps `Sheet` primitive. Sidebar collapse animation uses `useSpring`. |
| `resources/js/components/NotificationCenter.vue` + `ToastContainer.vue` + `NotificationToast.vue` | Modify | Same primitives, new tokens. |
| `resources/js/modules/auth/{LoginPage,ForgotPasswordModal,ResetPasswordModal}.vue` | Modify | Decision 6 (Login), dev-token removal (Forgot), restyle (Reset). |
| `resources/js/modules/dashboard/DashboardPage.vue` | Modify | 5 stat cards on new `Card` variants, stat-1 ("Citas Hoy") is primary (largest, terracotta accent, top-left). Quick actions as 5-tile flat grid. `cashStatus*` collapsed to `<UiStatusPill :status="cashStatusPillStatus" />`. Inline `<style>` deleted; gradients replaced with `bg-cream-100` tinted backgrounds. Verified labels (from `DashboardPage.vue`): stat cards Citas Hoy / Pacientes (Total registrados) / Profesionales (Equipo médico) / Total Citas (Este mes) / Estado de Caja; quick actions Pacientes / Nueva Cita / Profesionales / Ambientes / Reportes. |
| `resources/js/modules/errors/NotFoundPage.vue` | Modify | Adopt `4439425_page-404_p3.jpg` (committed in PR3). Spring entrance. |
| `public/fonts/Newsreader-VariableFont_OPSZ,wght.woff2` | **Create (committed)** | OFL, ~38 KB, single variable file. |
| `public/images/pexels/auth/login/6812463_modern-dental_p2.jpg` | **Commit** | 56 KB hero still. |
| `public/images/pexels/errors-404/4439425_page-404_p3.jpg` | **Commit** | 16 KB. |
| `public/images/pexels/auth/login/6763242_clinic_v1.mp4` | **Commit (optional, gated on PR3 review)** | Re-encoded to H.264 720p ≤ 2 MB + poster. |
| `.gitignore` | Modify | `public/images/pexels/**/_v*.mp4` except the chosen candidate. |
| `tests/Unit/DesignSystem/TokensModuleTest.php` | Modify | Hex-parity for new ramps; generated-CSS parity; `info` absence; `:root` block count; `prefers-color-scheme: dark` absence; `letter-spacing` literal absence in non-generated CSS; `app.css` print block byte-presence. |
| `tests/visual/baselines/` | **Create** | Pre-PR2 PNG baselines (≤ 200 KB each, byte-stable). |

## Interfaces / Contracts

### `useSpring` — single-axis

```js
// resources/js/composables/useSpring.js
import { ref, onUnmounted } from 'vue'

export function useSpring(options = {}) {
  const {
    response = 0.35,    // seconds to reach target (Apple's "response")
    damping = 1.0,      // 1.0 = critically damped; < 1.0 = underdamped
    from = 0,           // starting value
    to = null,          // optional initial target
    onSettle = null,    // called once when |velocity| < epsilon and |value - target| < epsilon
  } = options

  // Reduced-motion: if true, all `set` calls are instant.
  const reduce = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

  const value = ref(to ?? from)
  const velocity = ref(0)
  const target = ref(to ?? from)
  let rafId = null
  let lastTs = 0
  const epsilon = 0.5

  function omega0() { return 2 * Math.PI / response }
  function zeta()   { return damping }
  function step(now) {
    const dt = Math.min(0.064, (now - lastTs) / 1000 || 1 / 60)
    lastTs = now
    const k = omega0() ** 2
    const c = 2 * zeta() * omega0()
    const dx = value.value - target.value
    const a = -k * dx - c * velocity.value
    velocity.value += a * dt
    value.value += velocity.value * dt
    if (Math.abs(velocity.value) < epsilon && Math.abs(dx) < epsilon) {
      value.value = target.value
      velocity.value = 0
      rafId = null
      onSettle?.()
      return
    }
    rafId = requestAnimationFrame(step)
  }

  function ensureRunning() {
    if (rafId == null && !reduce) {
      lastTs = 0
      rafId = requestAnimationFrame(step)
    }
  }

  function set(next, opts = {}) {
    if (reduce) { value.value = next; target.value = next; velocity.value = 0; return }
    if (opts.velocity != null) velocity.value = opts.velocity
    target.value = next
    ensureRunning()
  }

  function stop() {
    if (rafId != null) { cancelAnimationFrame(rafId); rafId = null }
    target.value = value.value
    velocity.value = 0
  }

  onUnmounted(stop)

  return { value, velocity, target, set, stop }
}
```

### `useSpring2D` — independent X and Y

```js
import { useSpring } from './useSpring.js'

export function useSpring2D(options = {}) {
  const x = useSpring(options)
  const y = useSpring(options)
  return { x, y }
}

export function projectAndSnap(current, velocity, snapPoints, d = 0.998) {
  const projected = current + (velocity / 1000) * d / (1 - d)
  return snapPoints.reduce((a, b) =>
    Math.abs(b - projected) < Math.abs(a - projected) ? b : a, snapPoints[0])
}
```

### `useFontsLoaded`

```js
import { ref, onMounted } from 'vue'

export function useFontsLoaded() {
  const loaded = ref(false)
  onMounted(() => {
    if (typeof document === 'undefined' || !document.fonts) { loaded.value = true; return }
    document.fonts.ready.then(() => {
      loaded.value = true
      if (typeof document.documentElement !== 'undefined') {
        document.documentElement.dataset.fontsLoaded = 'true'
      }
    })
  })
  return loaded
}
```

### Primitive prop surface after redesign (compat matrix)

| Primitive | Existing props (used) | New in PR2 | Removed in PR2 | Compat risk |
|---|---|---|---|---|
| `UiButton` | `variant={primary,secondary,ghost,danger,success,warning,icon}`, `size={xs,sm,md,lg,xl}`, `loading`, `disabled`, `fullWidth`, `ripple`, `ariaLabel` | — | — | None. Class names preserved. |
| `UiCard` | `variant={default,glass,flat,elevated,outlined}`, `padding`, `hover`, `clickable`, `loading` | — | — | None. `variant="glass"` keeps its role as the default elevated data-card surface used by 76 call sites across 21 module files; only rendered values change (`cream-100` bg, `ink-200` hairline, `shadow-medium`, no `backdrop-filter`). A `// Historical name: kept for compat` comment in `Card.vue` prevents future "let's rename it" churn. Chrome glass is a separate `.surface-glass` class generated by `tokens.generated.css` and used only in `AppLayout` / `Sheet`. |
| `UiInput` | `type`, `label`, `modelValue`, `placeholder`, `disabled`, `readonly`, `error`, `hint`, `size={sm,md,lg}`, `variant={default,filled,outlined}`, `clearable`, `floatingLabel` | — | — | None. |
| `UiModal` | `modelValue`, `title`, `size={sm,md,lg,xl,full}`, `variant={default,centered,top,bottom}`, `closable`, `closeOnBackdrop`, `closeOnEscape`, `persistent`, `role` | — | — | None. |
| `UiSheet` | `modelValue`, `title`, `position={top,bottom,left,right}`, `size={sm,md,lg,xl,full}`, `closable`, `closeOnBackdrop`, `closeOnEscape`, `persistent`, `showHandle`, `role` | — | — | None. AppLayout mobile menu reuses the existing primitive. |
| `UiBadge` | `variant={default,primary,success,warning,error,info,neutral}`, `size={sm,md,lg}`, `shape={rounded,pill,square}`, `dismissible`, `dot` | `variant="info"` now aliases to `clinicalTeal-500` (semantic alias, no breaking rename) | — | The shape `dismissible` is unused across the codebase (grep returns 0 callers); kept for API stability, no removal. |
| `UiStatusPill` | `status` (required), `variant`, `size`, `showDot` | — | — | None. This is the primitive that absorbs the `cashStatus*` triplet. |
| `UiToast` | `type={success,error,warning,info}`, `title`, `duration`, `dismissible`, `persistent`, `position={6 positions}` | — | — | None. `ToastContainer.vue` is the one consumer; not changed. |
| `UiSkeleton` | `variant={text,rectangular,circular,card,table,list}`, `width`, `height`, `count`, `animation={pulse,wave,none}`, `rounded`, `ariaLabel` | — | — | None. |
| `UiLoadingSpinner` | `size={xs,sm,md,lg,xl}`, `variant={primary,secondary,white,gray}`, `text`, `centered`, `ariaLabel` | — | — | None. The `text` prop is read by the current code; the design does not remove it. |
| `UiEmptyState` | `title`, `description`, `icon`, `illustration`, `actionText`, `actionVariant`, `actionSize`, `size`, `variant`, `centered` | — | — | None. |
| `UiAvatar` | `src`, `alt`, `size={xs,sm,md,lg,xl,2xl}`, `variant={circle,square,rounded}`, `initials`, `status`, `online`, `loading`, `clickable` | — | — | None. |
| `UiBreadcrumbs` | `items`, `separator`, `maxItems`, `showHome`, `homeTo`, `homeLabel`, `homeIcon`, `size`, `variant`, `ariaLabel` | — | — | None. |
| `UiTabs` | `modelValue`, `tabs`, `variant={default,pills,underline,cards}`, `orientation`, `size`, `fullWidth`, `ariaLabel` | — | — | None. |
| `UiConfirmDialog` | `modelValue`, `title`, `message`, `confirmText`, `cancelText`, `variant={default,danger}`, `loading` | — | — | None. |
| `UiNotificationToast` | (no props; uses `useNotifications()`) | — | — | None. |

**Confirmed regression candidates.** None on the prop surface. The proposal's "keep the same class names, change only the rendered values" claim holds for the 17 un-migrated modules because Tailwind class names (`bg-accent`, `bg-primary-50`, `text-theme-primary`, `border-theme`, `shadow-soft`, `rounded-xl`, `rounded-full`, etc.) are preserved — they now resolve to the new ramp values. The single visual risk is the iCloud-blue → terracotta accent, which is the entire point of the change. The reviewer's eye will catch it, but no prop rename is a regression. The one verifiable break is `LoginCard.vue` deletion, and its only caller is `LoginPage.vue`, which is touched in PR3.

**Compat risk in `themes.css` deletion.** Five hand-rolled `--color-primary*` aliases (`--color-primary`, `--color-primary-hover`, `--color-primary-active`, `--color-primary-light`, `--color-primary-dark`) are referenced in `LoginPage.vue:432, 449, 452, 478, 504` and `AppLayout.vue:940, 950`. The generator emits these as semantic aliases that point to the new `terracotta-500` value, so the references stay valid. The unit test asserts presence.

## Testing Strategy

| Layer | What | How |
|---|---|---|
| Unit (PHP) | Token parity: `tokens.js` ↔ `tailwind.config.js` ↔ `tokens.generated.css`; ramp completeness (50/100/200/300/400/500/600/700/800/900 for `terracotta`, 50/100/200/300 for `cream`, 50/100/200/300/500/600/700/800/900 for `ink`, 50/100/100/300/500/600/700 for `clinicalTeal`); hex format on all values; `info` ramp absence; `prefers-color-scheme: dark` absence under `resources/`; single `:root` block in generated CSS; `letter-spacing` literal absence in non-generated CSS; print block byte-presence in `app.css`; Newsreader `@font-face` declaration presence in generated CSS; `font-display: swap` value; `.surface-glass` class emitted exactly once in `tokens.generated.css`; `backdrop-filter` absence in `resources/js/components/ui/Card.vue` (data-card variants never blur) | `tests/Unit/DesignSystem/TokensModuleTest.php` extended; new `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` |
| Unit (JS / Playwright) | `useSpring` API contract: critically-damped `damping 1.0` settles without overshoot, `damping 0.8` overshoots once, `prefers-reduced-motion` reduces to instant, 2D X/Y are independent (perturbing X does not move Y) | `tests/visual/useSpring.spec.mjs` (Playwright Test) |
| Visual (Playwright) | PR3 checkpoint screenshots, baselines in `tests/Visual/baselines/`, byte-stable (PNG) | `playwright-cli open http://localhost:8000/login --filename=login-light.png` and the 7-step recipe in `proposal.md` |
| E2E (Playwright, pre-existing) | Login → Dashboard → 404 nav still works; reset password flow works; WS indicator on dashboard shows `connected` after Reverb warm-up | `playwright-cli` recipes; existing `tests/Feature/Api/AuthTest.php` and the smoke recipe |

**Static grep assertions (the anti-requirements the proposal locks).** All five run as PHPUnit source-inspection tests against `resources/` (skipping generated files):

1. `grep -r "prefers-color-scheme: dark" resources/` returns 0 matches.
2. `grep -r "useTheme\|ThemeSelector\|design-system.js" resources/` returns 0 matches (excludes test fixtures).
3. `grep -rE "#[0-9A-Fa-f]{6}" resources/css/tokens.generated.css` returns the same set as `tokens.js` palette.
4. `grep -rE "letter-spacing\s*:" resources/js/**/*.vue` returns 0 matches (the only allowed `letter-spacing` is in the generated CSS).
5. `grep -r "_v1.mp4" public/images/pexels/` returns 0 matches outside the chosen login candidate (if any).
6. `git ls-files | grep _v1.mp4` returns 0 entries outside the chosen login candidate.
7. `grep -r "backdrop-filter" resources/js/components/ui/Card.vue` returns 0 matches — the data-card variant `"glass"` is opaque by construction; blur only ever lives in `.surface-glass` in the generated CSS. Also asserts `grep -c "backdrop-filter" resources/css/tokens.generated.css` returns exactly 2 (one for `.surface-glass`, one for its `-webkit-` prefix), with no other selector carrying a blur.
8. `grep -rn "backdrop-filter" resources/js/components/ui/ | grep -v Card.vue` returns 0 matches — no other primitive component declares its own blur; the chrome blur lives only in the generated CSS and only the three chrome surfaces consume it (`AppLayout` sidebar, `AppLayout` top bar, `Sheet` mobile).

**Playwright media-query forcing.** Each visual checkpoint that needs a media-query variant uses `page.emulateMedia({ reducedMotion: 'reduce' | null, colorScheme: null, contrast: 'more' | null, reducedTransparency: 'reduce' | null })` from the Playwright Test context. The base checkpoint uses the default (no media query forced). The reduced-transparency variant uses `--force-prefers-reduced-transparency` via `playwright-cli open ... --viewport-arg` plus a `page.addInitScript` that sets the `forcedColors`/`prefers-reduced-transparency` media query on `window.matchMedia`. A small `tests/visual/setup-media.mjs` helper wraps this so the recipe in the proposal becomes a one-liner.

## Threat Matrix

N/A — the change touches CSS, Vue 3 templates, and a Vue composable. It does not touch routing, shell commands, subprocesses, VCS/PR automation, executable-file classification, or process integration. The closest the change comes is `php artisan view:cache` / `pnpm build` which are existing CI gates already covered by `tests/Unit/Polish/ApiAndSeedersPolishTest.php` and the CI `frontend-build` job. No new shell or process surface.

## Migration / Rollout

The three PRs are retargeted in the feature-branch chain pattern from the proposal:

```
main
 └─ feat/ui-redesign-apple-claude-2026-08 (PR1 target)
     └─ feat/ui-redesign-apple-claude-2026-08-p2 (PR2 target)
         └─ feat/ui-redesign-apple-claude-2026-08-p3 (PR3 target)
```

- **PR1** ≤ 250 LOC, visually inert. `pnpm build` + `phpunit` exit 0; the unit test on the token surface (still iCloud-blue) passes; the dark-mode grep returns 0. Revertible: `git revert <sha>`.
- **PR2** ≤ 600 LOC. New tokens + 16 primitives + generator + fonts + `useSpring` + `useSpring2D` + `useFontsLoaded` + two CSS files. Visually all modules are repainted, but the diff at the template layer is small because class names are preserved. `pnpm build` + `phpunit` exit 0; new `TokensModuleTest` assertions pass; visual baselines committed. Revertible: `git revert <sha>` (test must revert in the same commit to keep the parity check honest).
- **PR3** ≤ 550 LOC. `LoginPage` rebuild, `LoginCard` deletion, `ForgotPasswordModal` (dev-token UI removed), `ResetPasswordModal` restyle, `DashboardPage` rebuild, `NotFoundPage` 404 image + entrance, `AppLayout` chrome restyle, `DashboardPage` `cashStatus*` collapse to `<UiStatusPill>`, 404 + login asset commits. `pnpm build` + `phpunit` exit 0; Playwright 7-step recipe produces the 7 screenshots; visual baselines replaced. Revertible: `git revert <sha>` (primitives stay on new tokens, so reverts cleanly).

**Backward compatibility for the 17 un-migrated modules.** The class-name audit above is the contract. Every Tailwind class the 17 modules use today (`bg-accent`, `bg-primary-50`, `bg-success-50`, `text-theme-primary`, `text-theme-secondary`, `bg-theme-surface`, `bg-theme-surface-elevated`, `border-theme`, `border-theme-light`, `border-theme-strong`, `shadow-soft`, `shadow-medium`, `shadow-large`, `rounded-xl`, `rounded-2xl`, `rounded-full`, `text-sm`, `text-base`, `text-lg`, `text-xl`, `text-2xl`, `text-3xl`, `bg-success-badge`, `bg-warning-badge`, `bg-danger-badge`, `hover:bg-accent-hover`, `hover:bg-theme-surface`, `hover:shadow-medium`, `transition-all duration-200`) is preserved. The values they resolve to change. No module template edits are required for the 17; only the visual output changes, and that is exactly the proposal's intent.

## Open Questions

- [ ] Newsreader OFL redistribution: confirmed OFL on Google Fonts (project repo `ProductionType/Newsreader`). Variable `opsz` axis exists. The two file (static + variable) downloads on Google Fonts total ~80 KB; the single variable `OPSZ,wght` is ~38 KB. Within the 40 KB budget. No open question; commit to the variable file.
- [ ] The optional login mp4: deferred to PR3 review per the proposal. If the re-encode produces > 2 MB, do not commit; the still fallback at 56 KB is sufficient.
- [ ] The `info` ramp is deleted from the token surface and the generated CSS. The unit test enforces this. Any caller still using `colors.info.500` (grep: 0 in the JS, 3 in the old `themes.css`, all deleted) breaks. The unit test `info-keyword-absent` asserts 0 matches under `resources/css/`.
- [ ] `variant="glass"` compat decision (the corrected one). The design keeps `variant="glass"` as the default elevated data-card look used by 76 call sites across 21 module files. A previous draft proposed renaming or moving it to a frosted `.card-glass` class; that was rejected because it would have landed a `backdrop-filter: blur(12px)` on 76 data surfaces (including the 5 Dashboard stat cards and every medical-record / cash-amount table) and blown the PR2 budget. The new surface: `cream-100` bg, `ink-200` hairline, `shadow-medium`, no `backdrop-filter`. The name is a historical artifact (it once meant iCloud-blue translucent in the previous design) and the comment in `Card.vue` makes that explicit. Chrome translucent is the new `.surface-glass` class, opt-in only, used in three places: `AppLayout` desktop sidebar, `AppLayout` top bar, and the `Sheet` wrapper around the mobile menu. LOC impact of this decision vs. the rejected alternative: option 1 (chosen) edits 1 file (`Card.vue` redefinition), 0 call-site migrations, 0 PR2 budget cost. Option 2 (rejected) would touch 76 call sites across 21 module files at ~25 chars each plus import/path fixes, ~50–80 LOC of mechanical diff and PR2 review burden, contradicting the "byte-identical class names" compat strategy.
- [ ] Dashboard labels (verified from `DashboardPage.vue`). The 5 stat cards are "Citas Hoy", "Pacientes / Total registrados", "Profesionales / Equipo médico", "Total Citas / Este mes", "Estado de Caja". The 5 quick actions are "Pacientes", "Nueva Cita", "Profesionales", "Ambientes", "Reportes". Labels like "ingresos del día", "tratamientos activos", "recordatorios" do not exist in the codebase and must not appear in any spec, design, or PR3 visual.

## Key Learnings

1. Generated CSS is the only durable answer to token drift; the unit test is a fail-late check, not a prevention.
2. WAAPI cannot be re-targeted mid-flight without paying a per-interrupt cost that an rAF loop avoids; for the spring contract, rAF + CSS variables is the only correct choice on the web platform today.
3. The 17 un-migrated modules are the load-bearing reason to keep Tailwind class names byte-identical in the primitive layer; the only consumer-facing break is the `LoginCard` wrapper deletion, called from one file (`LoginPage.vue`).
4. Self-hosted Newsreader variable + metric-compatible fallback + `useFontsLoaded` data attribute is enough to prevent FOUT-driven layout shift; a single `font-display: swap` is not.
5. The 300 ms debounce on the dashboard's WebSocket burst is load-bearing for "motion does not fight motion" — the existing code already does it, the design must preserve it, and the new stat-card spring must use a tween (`spring.set(new, { velocity: 0 })`), not an entrance, on subsequent updates.
