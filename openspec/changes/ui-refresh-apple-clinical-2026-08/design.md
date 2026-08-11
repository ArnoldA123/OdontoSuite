# Design: ui-refresh-apple-clinical-2026-08

> Change: `ui-refresh-apple-clinical-2026-08`. Phase: `sdd-design`. Read alongside `openspec/changes/ui-refresh-apple-clinical-2026-08/proposal.md` (Engram id 462) and the previous design `openspec/changes/ui-redesign-apple-claude-2026-08/design.md` (Engram id 453). The previous design's Decisions 1, 2, 4 are **locked**; Decisions 3, 5, 6 are **updated**; Decision 7 is **new** for this change. The architectural lever is the same: `tokens.js` SoT + generator pipeline + per-axis spring runtime + chrome/data-card split.

## Technical Approach

The change is a pure token swap + primitive revalue + 3-page vertical retouch. The "Apple chassis" (Liquid-Glass chrome, spring runtime, `prefers-*` contracts) holds. The "Claude soul" (cream surfaces, terracotta accent, Newsreader serif) retires and is replaced with an iOS clinical language: system font only, white surfaces, iOS system colors, 10 px radius, hairline separators, vibrant filled status chips.

The architecture is the same generator pipeline proven out in the previous design: `tokens.js` is SoT, `scripts/build-tokens-css.mjs` emits `resources/css/tokens.generated.css` (no hand-edits), and `tailwind.config.js` re-imports the same module. Class names on the 17 un-migrated modules are preserved; only the values they resolve to change. The 3 verticals (Login + Dashboard + 404) get explicit token revaluation and the Newsreader-serif headlines are dropped.

The new ramp shape is iOS 13+: `systemBlue`/`systemRed`/`systemOrange`/`systemYellow`/`systemGreen`/`systemIndigo`/`systemPurple`/`systemPink`/`systemGray` each at 50/100/500/600/700; `background` = `systemBackground`/`secondaryBackground`/`tertiaryBackground`/`groupedBackground`; `label` = `label`/`secondaryLabel`/`tertiaryLabel`/`quaternaryLabel`; plus `separator` and `fill` ramps. Deprecated alias keys (`bg-cream-50` → `bg-systemGray-50`, `bg-terracotta-500` → `bg-systemBlue-500`, `bg-clinicalTeal-50` → `bg-systemBlue-50`) live in `tokens.js` so the 17 un-migrated modules' Tailwind classes keep resolving without churn.

## Architecture Decisions

### Decision 1 — Token pipeline reuse (LOCKED, no change)

`tokens.js` SoT → `scripts/build-tokens-css.mjs` → `resources/css/tokens.generated.css`. The generator is the only writer of generated CSS. `TokensModuleTest.php` enforces hex-parity between `tokens.js` and the generated CSS. **Alternatives** (hand-mirror, Tailwind plugin) were rejected in the previous design and stay rejected.

### Decision 2 — Motion runtime reuse (LOCKED, no change)

`useSpring` + `useSpring2D` + `useSpringMath` composables are unchanged. rAF loop + CSS custom properties; per-axis independence; velocity handoff via integrator's last frame. Timings unchanged: `response 0.35 damping 1.0` entrance, `response 0.3 damping 0.8` momentum, `response 0.2 damping 1.0` opacity cross-fade. `prefers-reduced-motion` short-circuit unchanged. **Why:** the spring contract is the difference between "fine" and "Apple-native"; changing it for a token swap is scope creep.

### Decision 3 — Token architecture: iOS clinical palette + typography + radius (NEW)

`tokens.js` rewrite replaces the `terracotta`/`cream`/`ink`/`clinicalTeal` ramps with iOS 13+ system colors. The shape of the export surface is preserved (`colors`/`spacing`/`radius`/`typography`/`shadow`/`breakpoint`/`motion`).

```js
// Excerpt — resources/js/design-system/tokens.js
colors: {
  // iOS system colors (50/100/500/600/700 — same shape as terracotta/cream had)
  systemBlue:   { 50:'#E5F1FF', 100:'#CCE4FF', 500:'#007AFF', 600:'#0062CC', 700:'#004999' },
  systemRed:    { 50:'#FFEBEA', 100:'#FFD9D7', 500:'#FF3B30', 600:'#D70015', 700:'#A50E10' },
  systemOrange: { 50:'#FFF1E5', 100:'#FFE2C7', 500:'#FF9500', 600:'#C93400', 700:'#9A2700' },
  systemYellow: { 50:'#FFF9D6', 100:'#FFF1AD', 500:'#FFCC00', 600:'#A57000', 700:'#7A5200' },
  systemGreen:  { 50:'#E8F5E9', 100:'#CDEDCF', 500:'#34C759', 600:'#248A3D', 700:'#1A6530' },
  systemIndigo: { 50:'#EFE9FF', 100:'#DFD2FF', 500:'#5856D6', 600:'#3F3DAB', 700:'#2D2B80' },
  systemPurple: { 50:'#F6E9FF', 100:'#ECD2FF', 500:'#AF52DE', 600:'#7A38A1', 700:'#55276F' },
  systemPink:   { 50:'#FFE9F0', 100:'#FFD2DD', 500:'#FF2D55', 600:'#C30039', 700:'#8E0028' },
  systemGray:   { 50:'#F2F2F7', 100:'#E5E5EA', 500:'#8E8E93', 600:'#636366', 700:'#3A3A3C' },

  // iOS background ramp
  background: {
    systemBackground:   '#FFFFFF',
    secondaryBackground:'#F2F2F7',
    tertiaryBackground: '#FFFFFF',
    groupedBackground:  '#F2F2F7'
  },

  // iOS label ramp
  label: {
    label:           '#000000',
    secondaryLabel:  '#3C3C43',
    tertiaryLabel:   'rgba(60, 60, 67, 0.30)',
    quaternaryLabel: 'rgba(60, 60, 67, 0.18)'
  },

  // iOS hairline separator
  separator: { separator: '#C6C6C8' },

  // iOS fill (opaque-ish overlays for grouped rows)
  fill: { systemFill:'rgba(120, 120, 128, 0.20)',
          secondarySystemFill:'rgba(120, 120, 128, 0.16)',
          tertiarySystemFill:'rgba(118, 118, 128, 0.12)' },

  // Deprecated alias keys — kept so the 17 un-migrated modules' Tailwind
  // classes keep resolving. Do NOT add new consumers.
  cream:         { 50:'#F2F2F7', 100:'#E5E5EA', 200:'#D1D1D6' },           // → systemGray-50/100/200
  terracotta:    { 500:'#007AFF', 600:'#0062CC' },                          // → systemBlue-500/600
  clinicalTeal:  { 50:'#E5F1FF', 500:'#007AFF', 600:'#0062CC' },            // → systemBlue-50/500/600
  info:          { 500:'#007AFF' }                                            // → systemBlue-500 (iOS convention)
}
```

```js
// Excerpt — radius: iOS standard
radius: {
  none:  '0',
  sm:    '4px',     // small chips
  md:    '8px',     // inputs (slight inset)
  ios:   '10px',    // cards, buttons, status chips
  modal: '14px',    // Modal, Sheet, bottom pickers
  full:  '9999px'   // pills
}
// radius.lg/2xl/3xl retire. Tailwind utility classes `rounded-lg/2xl/3xl`
// are removed from `tailwind.config.js` so accidental consumers fail-loud.
```

```js
// Excerpt — typography: system font only, Newsreader removed
typography: {
  fontFamily: {
    sans: ['-apple-system','BlinkMacSystemFont','Segoe UI','Roboto',
           'Helvetica Neue','Arial','sans-serif']
    // serif: REMOVED entirely. No fallback. No opt-in.
  },
  fontSize: {
    xs:     ['12px', { lineHeight:'16px', letterSpacing:'0' }],
    sm:     ['13px', { lineHeight:'18px', letterSpacing:'0' }],
    base:   ['15px', { lineHeight:'22px', letterSpacing:'0' }],
    lg:     ['17px', { lineHeight:'24px', letterSpacing:'0' }],
    xl:     ['20px', { lineHeight:'28px', letterSpacing:'-0.01em' }],
    '2xl':  ['24px', { lineHeight:'32px', letterSpacing:'-0.015em' }],
    '3xl':  ['30px', { lineHeight:'36px', letterSpacing:'-0.02em' }],
    '4xl':  ['36px', { lineHeight:'40px', letterSpacing:'-0.022em' }],
    display:['48px', { lineHeight:'48px', letterSpacing:'-0.022em' }],
    hero:   ['64px', { lineHeight:'64px', letterSpacing:'-0.022em' }]
    // font-optical-sizing REMOVED — system font has no `opsz` axis.
  }
}
```

**Why iOS system colors over a custom palette:** the previous design's terracotta+cream+teal read as "warm craft" — wrong for a clinical admin tool. iOS system colors read as "this is a serious tool" the way every native Apple app does. The trade-off (less personality, more credibility) is the explicit intent. **Alternatives:** keep terracotta as a "warm dental" accent (rejected — already tried in previous design, called out by `design-taste-frontend` §4.2); pure monochrome (rejected — loses semantic state in cash status + appointment chips); green-only success (rejected — iOS uses color-coded state chips and the dashboard needs all six semantic colors).

### Decision 4 — CSS file consolidation (LOCKED, no change)

Two CSS files only: `tokens.generated.css` (generator) + `utilities.css` (hand). Five files → two was completed in PR2 of the previous design and stays. Generator emits one `:root` block, one `@media (prefers-reduced-transparency)`, one `@media (prefers-contrast: more)`, one `.surface-glass` class. `@media print` byte-preserved in `app.css`.

### Decision 5 — Liquid-Glass chrome: white-on-white (UPDATED)

`.surface-glass` class is the only chrome-only Liquid-Glass surface. Inner rgba changes from cream-on-cream to white-on-white; shadow tints shift from warm-black `rgb(31 27 23 / ...)` to pure `rgb(0 0 0 / ...)`. The `::after` inner-edge refraction stays (white catches the material light).

```css
/* Emitted by scripts/build-tokens-css.mjs — DO NOT hand-edit */
.surface-glass {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  background: linear-gradient(135deg,
              rgb(255 255 255 / 0.78),
              rgb(255 255 255 / 0.62));
  backdrop-filter: blur(20px) saturate(180%) contrast(1.04);
  -webkit-backdrop-filter: blur(20px) saturate(180%) contrast(1.04);
  border-right: 1px solid rgb(0 0 0 / 0.06);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.55),
    inset 0 -1px 0 rgb(0 0 0 / 0.04),
    0 18px 40px -16px rgb(0 0 0 / 0.10);
}
.surface-glass::after {
  content: ""; position: absolute; inset: 0; pointer-events: none;
  border-radius: inherit;
  border: 1px solid rgb(255 255 255 / 0.18);
  mix-blend-mode: overlay;
}
@media (prefers-reduced-transparency: reduce) {
  .surface-glass {
    background: var(--color-systemBackground);  /* white, not cream */
    backdrop-filter: none; -webkit-backdrop-filter: none;
    box-shadow: none;
  }
}
```

**Why:** iOS chrome is white-on-white, not cream-on-cream. Keeping cream under the glass makes the Liquid-Glass effect read as "frosted beige" instead of "frosted white" — the material light is the wrong color. **Consumed only by** `AppLayout.vue` (sidebar, mobile header, top bar) — verified by grep.

### Decision 6 — Typography: system font, no Newsreader, no FOUT mitigation (UPDATED)

DELETE `public/fonts/newsreader-latin.woff2`. DELETE `resources/js/composables/useFontsLoaded.js` (dead code; was Newsreader FOUT mitigation). REMOVE `@font-face` block emit in `build-tokens-css.mjs`. REMOVE `var(--font-serif)` references in `LoginPage.vue` (3 call sites: `.welcome-headline`, `.hero-caption-title`, `prefers-contrast` block) and `NotFoundPage.vue` (1 call site: `.not-found-headline`).

```js
// build-tokens-css.mjs — REMOVE this entire block:
// @font-face {
//   font-family: "Newsreader";
//   font-style: normal;
//   font-weight: 100 900;
//   font-display: swap;
//   font-optical-sizing: auto;
//   src: url("/fonts/newsreader-latin.woff2") format("woff2");
//   unicode-range: U+0000-00FF, U+0131, ...;
// }
//
// And REMOVE the --font-serif var emit:
//   --font-serif: Newsreader, ui-serif, "New York", Georgia, serif;
//
// Keep:
//   --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
//                "Helvetica Neue", Arial, sans-serif;
```

**Why:** the system font is available immediately on every supported platform — no FOUT risk, no metric-matching fallback, no `data-fonts-loaded` adjustment. `useFontsLoaded` and Newsreader are dead weight the moment we stop using the serif. **No opt-in fallback** ships; a future contributor who wants serif re-introduces it explicitly with their own OFL font.

### Decision 7 — Dashboard status chips: iOS filled pattern (NEW)

Replaces the Dashboard's hand-rolled icon chip backgrounds (`bg-success-50` / `bg-warning-50` / `bg-cream-200` / `bg-clinicalTeal-50` / `bg-error-50`) with the iOS filled pattern: `bg-system{Color}-100` background + `text-system{Color}-600` foreground. 32 px rounded-square, 10 px radius. The `bg-info-100` `text-info-700` alias resolves to `bg-systemBlue-100` `text-systemBlue-700` via the deprecated alias key.

```html
<!-- DashboardPage.vue — cash status badge (Estado de Caja) -->
<span v-if="cashStatusPillStatus === 'open'"
      class="inline-flex items-center gap-1.5 px-2.5 py-1
             bg-systemGreen-100 text-systemGreen-600
             text-xs font-semibold rounded-ios">
  <span class="w-1.5 h-1.5 rounded-full bg-systemGreen-500"></span>
  Abierta
</span>
<span v-else-if="cashStatusPillStatus === 'closed'"
      class="inline-flex items-center gap-1.5 px-2.5 py-1
             bg-systemRed-100 text-systemRed-600
             text-xs font-semibold rounded-ios">
  <span class="w-1.5 h-1.5 rounded-full bg-systemRed-500"></span>
  Cerrada
</span>
<span v-else
      class="inline-flex items-center gap-1.5 px-2.5 py-1
             bg-systemGray-100 text-systemGray-600
             text-xs font-semibold rounded-ios">
  <span class="w-1.5 h-1.5 rounded-full bg-systemGray-500"></span>
  Sin sesión
</span>

<!-- DashboardPage.vue — appointment status chip (each status variant) -->
<!-- scheduled   : bg-systemOrange-100 text-systemOrange-600 -->
<!-- confirmed   : bg-systemGreen-100  text-systemGreen-600  -->
<!-- in_consultation: bg-systemYellow-100 text-systemYellow-600 -->
<!-- completed   : bg-systemBlue-100   text-systemBlue-600   -->
<!-- cancelled   : bg-systemRed-100    text-systemRed-600    -->
<!-- no_show     : bg-systemGray-100   text-systemGray-600   -->

<!-- DashboardPage.vue — "Citas Hoy" big number -->
<p class="text-3xl font-semibold text-label leading-tight">
  {{ citasHoyCount }}
</p>
<!-- Was: <p class="text-3xl font-semibold text-terracotta-600"> — the
     iOS clinical read uses pure black for big numbers, never accent. -->
```

**Why:** the iOS status chip pattern is what the user sees in Calendar, Reminders, Health, and every native Apple app — same 32 px rounded-square, same `*100` background + `*600` foreground, same 10 px radius. The previous design's "abierto/cerrado" badge was an icon chip with mixed semantic colors that read inconsistently across light/dark/contrast modes. **Alternatives:** filled pill (rejected — pill shape implies clickable; status is read-only); outlined chip (rejected — iOS clinical uses filled, not outlined, for status); monochrome gray (rejected — loses cash-state semantic).

## Data Flow

Three flows matter for the change. None change shape; only rendered values change.

### Login flow

```
LoginPage.vue → useAuth().login(form) → useApi().post('/api/auth/login')
  → AuthController::login → token → SUCCESS → push /dashboard

Entrance choreography (unchanged):
  Card spring:    useSpring({ response: 0.35, damping: 1.0, from: 24, to: 0 })
                  writes --spring-card-y to .login-card; transform: translate3d(0, var(--spring-card-y, 0), 0)
  Opacity spring: useSpring({ response: 0.2, damping: 1.0, from: 0, to: 1 })
                  writes --spring-card-opacity to .login-card; opacity: var(--spring-card-opacity, 1)
```

What changes in PR2: card surface `bg-cream-100` → `bg-systemBackground` (white) + 10 px corners + `border-separator` hairline; icon ring color `var(--color-terracotta-500)` → `var(--color-systemBlue-500)`; primary button `bg-terracotta-500` → `bg-systemBlue-500`; headline `var(--font-serif)` removed.

### Dashboard WebSocket burst

```
Reverb                       useEcho (singleton)               DashboardPage
── appointment.created ──►  channel('appointments')         .listen('.appointment.created',   e => same-day → debouncedLoadDashboardData())
── cash-session.opened ──►  channel('cash-register')         .listen('.cash-session.opened',    () => { loadCurrentSession(); debouncedLoadDashboardData() })
── payment.registered  ──►  channel('cash-register')         .listen('.payment.registered',    () => { loadCurrentSession(); debouncedLoadDashboardData() })
                                                                          │
                                                                          ▼
                                                                  debounce 300 ms
                                                                          │
                                                                          ▼
                                                                  GET /api/dashboard/stats
                                                                  GET /api/dashboard/appointments-today
                                                                          │
                                                                          ▼
                                                                  spring.set(newValue, { velocity: 0 })  ← tween, not entrance
```

300 ms debounce at `DashboardPage.vue:882` is preserved (load-bearing — see previous design). Stat cards enter with `useSpring({ response: 0.35, damping: 1.0 })` on first paint; subsequent value changes use `spring.set(new, { velocity: 0 })` (tween, not entrance — Apple's "motion does not fight motion"). Status icon chips render in the iOS filled pattern (Decision 7). "Citas Hoy" big number is `text-label` (pure black), not `text-terracotta-600`.

### 404 flow

```
Router (any unmatched route) → NotFoundPage.vue → 404 entrance spring
  useSpring({ response: 0.35, damping: 1.0, from: 16, to: 0 }) writes --spring-404-y
  Card opacity cross-fade
```

What changes in PR2: headline `font-family: var(--font-serif)` removed (system font); image border `border-ink-200` → `border-separator`; shadow `rgba(31, 27, 23, ...)` → `rgba(0, 0, 0, ...)` (iOS lighter pure-black).

## File Changes

| File | Action | Description | LOC est. |
|---|---|---|---|
| `resources/js/design-system/tokens.js` | Modify | New iOS ramps; remove `fontFamily.serif`; tune `letterSpacing`; replace `radius.lg/2xl/3xl` with `radius.ios`/`radius.modal`; keep deprecated alias keys. | +220 / -160 |
| `scripts/build-tokens-css.mjs` | Modify | Drop `@font-face` emit; drop `--font-serif`; emit iOS semantic aliases; swap shadow rgba to pure black; swap `.surface-glass` rgba to white-on-white. | +40 / -20 |
| `resources/css/tokens.generated.css` | Regenerate | Full regen by `pnpm tokens:build`. Not hand-edited. | +300 / -200 |
| `tailwind.config.js` | Modify | Re-source from new tokens; drop `rounded-lg/2xl/3xl` utilities; add `rounded-ios`/`rounded-modal` aliases. | +10 / -15 |
| `resources/js/composables/useFontsLoaded.js` | Delete | Dead code; was Newsreader FOUT mitigation. No consumers (grep audit). | -40 |
| `public/fonts/newsreader-latin.woff2` | Delete | Font binary. ~38 KB. | -bin |
| `resources/js/components/ui/Button.vue` | Modify | Primary → `bg-systemBlue-500`; focus ring → `ring-systemBlue-500`; radius 10 px. | +3 / -3 |
| `resources/js/components/ui/Card.vue` | Modify | Surface → `bg-systemBackground`; border → `border-separator`; radius 10 px; lighter shadow. `variant="glass"` stays opaque (no `backdrop-filter`). | +6 / -4 |
| `resources/js/components/ui/Modal.vue` | Modify | Surface → `bg-systemBackground`; corners 14 px. | +3 / -2 |
| `resources/js/components/ui/Sheet.vue` | Modify | Surface → `bg-systemBackground`; corners 14 px. | +3 / -2 |
| `resources/js/components/ui/Input.vue` | Modify | Surface → `bg-secondaryBackground`; border → `border-separator`; focus ring → `systemBlue-500`. | +4 / -3 |
| `resources/js/components/ui/Badge.vue` | Modify | `variant="info"` re-keyed to `systemBlue`; filled iOS pattern. | +4 / -3 |
| `resources/js/components/ui/StatusPill.vue` | Modify | Filled iOS pattern; 10 px radius. | +3 / -2 |
| `resources/js/components/ui/Toast.vue` | Modify | Surface → `bg-systemBackground` + `border-separator` + shadow. | +3 / -2 |
| `resources/js/components/ui/Skeleton.vue` | Modify | Derive from `bg-systemGray-100`. | +1 / -1 |
| `resources/js/components/ui/LoadingSpinner.vue` | Modify | `--spinner-color` → `systemBlue-500`. | +1 / -1 |
| `resources/js/components/ui/EmptyState.vue` | Modify | Surface → `bg-systemBackground`. | +1 / -1 |
| `resources/js/components/ui/Avatar.vue` | Modify | Token swap only (already neutral). | +1 / -1 |
| `resources/js/components/ui/Breadcrumbs.vue` | Modify | Separator → `text-systemGray-500` iOS seam style. | +1 / -1 |
| `resources/js/components/ui/Tabs.vue` | Modify | Active indicator → `bg-systemBlue-500`. | +2 / -2 |
| `resources/js/components/ui/ConfirmDialog.vue` | Modify | Surface → `bg-systemBackground`. | +1 / -1 |
| `resources/js/components/ui/NotificationToast.vue` | Modify | Surface → `bg-systemBackground`. | +1 / -1 |
| `resources/js/components/layout/AppLayout.vue` | Modify (PR1) | Page bg → `bg-systemBackground`; nav text → `text-label`; WS indicator chips → `bg-systemGray-100 text-systemGray-600`; `.surface-glass` rgba → white-on-white. | +8 / -6 |
| `resources/js/components/layout/PageHeader.vue` | Modify | Token swap only. | +2 / -2 |
| `resources/js/components/layout/FloatingActionButton.vue` | Modify | Token swap only. | +1 / -1 |
| `resources/js/modules/auth/LoginPage.vue` | Modify (PR2) | Drop `var(--font-serif)` on 2 call sites (`.welcome-headline`, `.hero-caption-title` + `prefers-contrast`); swap `var(--color-...)` to new token names; card surface → white + 10 px + hairline; icon ring → `systemBlue-500`. | +20 / -25 |
| `resources/js/modules/auth/ForgotPasswordModal.vue` | Modify (PR2) | Inherits primitive changes from PR1. | +3 / -3 |
| `resources/js/modules/auth/ResetPasswordModal.vue` | Modify (PR2) | Inherits primitive changes from PR1. | +3 / -3 |
| `resources/js/modules/dashboard/DashboardPage.vue` | Modify (PR2) | Icon chip backgrounds → `bg-system{Color}-100`; cash status badge → semantic-system; "Citas Hoy" big number → `text-label`; card border → `border-separator`. | +35 / -50 |
| `resources/js/modules/errors/NotFoundPage.vue` | Modify (PR2) | Drop `var(--font-serif)` on `.not-found-headline`; image border → `border-separator`; shadow → iOS lighter pure-black. | +6 / -8 |
| `tests/Unit/DesignSystem/TokensModuleTest.php` | Modify | New ramp assertions + Newsreader absence + `useFontsLoaded` absence + cream/terracotta/clinicalTeal literal absence + iOS hex verification + `radius.ios`/`radius.modal` + alias regression guard + `info` re-key. | +90 / -20 |
| `tests/Visual/baselines/*.png` | New (PR2) | Pre-PR2 visual baseline + PR2 iOS-clinical baselines (7 screenshots from Playwright recipe). | +bin |

**Totals:** 22 source files modified, 2 deleted (`useFontsLoaded.js` + newsreader woff2), 0 created in PR1 (only test extension + regenerated CSS). PR1 ≈ 240 LOC; PR2 ≈ 130 LOC. Both under the 400-LOC/slice budget.

## Interfaces / Contracts

### `useSpring` API (UNCHANGED)

```js
// resources/js/composables/useSpring.js — locked from previous design
const spring = useSpring({ response: 0.35, damping: 1.0, from: 0 })
spring.set(120)                       // animate to 120
spring.set(120, { velocity: 800 })    // handoff from a flick
spring.stop()                        // hard stop, keeps current value
```

### `useSpring2D` API (UNCHANGED)

```js
// resources/js/composables/useSpring2D.js — locked from previous design
const { x, y } = useSpring2D({ response: 0.35, damping: 1.0 })
x.set(120, { velocity: vx })
y.set(nearestSnap(projectedTarget), { velocity: y.velocity.value })
```

### `useSpringMath` API (UNCHANGED)

Momentum projection helper. Locked from previous design.

### `useFontsLoaded` — DELETE (no replacement API)

Was a ref<boolean> that flipped on `document.fonts.ready`. System font has no FOUT risk; this composable is dead. No replacement ships. Grep audit confirms zero consumers.

### Primitive prop surface (UNCHANGED)

`Button`, `Card`, `Modal`, `Sheet`, `Input`, `Badge`, `StatusPill`, `Toast`, `Skeleton`, `LoadingSpinner`, `EmptyState`, `Avatar`, `Breadcrumbs`, `Tabs`, `ConfirmDialog`, `NotificationToast` — all prop names, types, defaults preserved. Only the rendered values (CSS classes) change. `Card.vue` `variant="glass"` stays opaque (no `backdrop-filter`) — the previous design's revision is preserved.

### `tokens.js` export surface (UNCHANGED)

Same named exports: `colors`, `spacing`, `radius`, `typography`, `fontFamily`, `fontSize`, `shadow`, `breakpoint`, `motion`. The shape of `colors` changes (terracotta/cream/ink/clinicalTeal → iOS ramps), but `tailwind.config.js` re-imports and the unit test re-asserts the new shape.

## Testing Strategy

| Layer | What to test | Approach |
|---|---|---|
| Unit (PHP, `TokensModuleTest`) | All new iOS ramps asserted; literal hex checks (`systemBlue.500 === '#007AFF'`, `background.systemBackground === '#FFFFFF'`, `label.label === '#000000'`, `separator.separator === '#C6C6C8'`); Newsreader absence (`assertArrayNotHasKey('serif', $tokens['fontFamily'])`); `@font-face` block absence in generated CSS; `newsreader-latin.woff2` file absence; `useFontsLoaded.js` file absence; `var(--font-serif)` absence across `resources/`; cream/terracotta/clinicalTeal hex absence in `resources/` outside `tokens.js` + `tokens.generated.css`; `radius.ios === '10px'` and `radius.modal === '14px'`; shadow rgba uses `rgba(0, 0, 0, ...)`; `.surface-glass` rgba uses `rgb(255 255 255 / ...)`; deprecated alias keys present; `info` ramp re-keyed; single `:root` block; no `@media (prefers-color-scheme: dark)`; generated CSS hex-set equals `tokens.colors` hex-set. | PHPUnit source inspection + regex. |
| Unit (JS / Playwright Test) | `useSpring` API contract: critically-damped `damping 1.0` settles without overshoot, `damping 0.8` overshoots once, `prefers-reduced-motion` reduces to instant, 2D X/Y independent. | `tests/Visual/useSpring.spec.mjs` (regression guard from previous design). |
| Visual (Playwright 7-step recipe) | 1. `login-light.png` (white card, systemBlue button, no serif). 2. `login-reduced-motion.png` (no entrance translation). 3. `login-reduced-transparency.png` (sidebar solid white). 4. `after-login.png` (dashboard visible). 5. `dashboard.png` (5 stat cards, 5 quick actions, status icon chips in iOS filled pattern). 6. `not-found.png` (system font headline, no serif). 7. `dashboard-high-contrast.png` (text pure black, borders `label #3C3C43`). | Playwright CLI; `tests/Visual/baselines/*.png` byte-stable. |
| E2E (Playwright, pre-existing) | Login → Dashboard → 404 nav still works; reset password flow works; WS indicator on dashboard shows `connected` after Reverb warm-up. | `playwright-cli` smoke recipe + `tests/Feature/Api/AuthTest.php`. |
| Static grep | `grep -r "Newsreader" resources/` → 0. `grep -r "useFontsLoaded" resources/` → 0. `grep -r "#FAF9F7\|#F2EFE9\|#E8E3D8\|#C96442\|#B05432\|#2C7A7B" resources/` → 0 outside SoT. `grep -r "prefers-color-scheme: dark" resources/` → 0. `grep -r "var(--font-serif)" resources/` → 0. `ls public/fonts/newsreader-latin.woff2` → not-found. `ls resources/js/composables/useFontsLoaded.js` → not-found. `pnpm lint:check` → 0. | CI step. |

## Threat Matrix

N/A — the change touches CSS, Vue 3 templates, and one Vue composable (deletion). It does not touch routing, shell commands, subprocesses, VCS/PR automation, executable-file classification, or process integration. CI gates (`pnpm build`, `pnpm tokens:build`, `vendor/bin/phpunit`) are existing pipelines; the change adds no new shell or process surface.

## Migration / Rollout

Two chained PRs, retargeted per `sdd-phase-common.md` Section E:

```
main
 └─ feat/ui-redesign-apple-claude-2026-08-p3 (previous, merged)
     └─ feat/ui-refresh-apple-clinical-2026-08 (PR1 target)
         └─ feat/ui-refresh-apple-clinical-2026-08-p2 (PR2 target)
```

- **PR1** ≤ 240 LOC. Tokens + primitives + chrome + font deletion + composable deletion. Visually inert for the 3 verticals (Login + Dashboard + 404 still render in cream + terracotta + Newsreader because the page-level template changes are in PR2). The 17 un-migrated modules auto-recolor via deprecated alias keys. `pnpm tokens:build` is byte-stable. Revertible: `git revert <sha>`.
- **PR2** ≤ 130 LOC. Login + Dashboard + 404 + 2 modal inheritance-only changes + visual baseline refresh. `pnpm build` + `phpunit` exit 0; Playwright 7-step recipe produces 7 screenshots; visual baselines committed byte-stable. Revertible: `git revert <sha>` (PR1 already merged; tokens stay on iOS values, so a clean revert re-chromes the 3 pages to cream/terracotta/Newsreader).

**Backward compatibility for the 17 un-migrated modules.** Tailwind class names (`bg-cream-50`, `bg-terracotta-500`, `bg-clinicalTeal-50`, `bg-info-500`, `text-info-700`, etc.) are preserved. `tokens.js` carries deprecated alias keys mapping each to the corresponding iOS value. The 17 modules get an automatic visual re-skin (cream → systemGray, terracotta → systemBlue, teal → systemBlue) with zero template edits. Diff is in `tokens.js` + `tokens.generated.css` + `tailwind.config.js` only.

## Open Questions

- [ ] Picsum vs Pexels for hero replacement if needed (decision deferred to PR-time per proposal).
- [ ] Whether the `*` selector in `app.css` carries `font-family` that needs system-font update (audit at PR1 time).
- [ ] Whether `useFontsLoaded.js` deletion breaks any test fixture (audit at PR1 time; grep returns zero imports, but vitest/spec fixtures may reference the symbol).
- [ ] Whether `tailwind.config.js` needs `rounded-lg/2xl/3xl` removal as a hard break (currently `radius.lg/2xl/3xl` are simply not in `tokens.radius` — any Tailwind class consuming them resolves to default 0px; could be a silent visual break in the 17 modules). PR1 audit required.
- [ ] `info` ramp re-key: `Badge.vue` `variant="info"` and `AppLayout.vue` WS indicator `bg-info-100 text-info-700` confirmed as the only consumers (per previous design). PR1 audit re-confirms.

## Key Learnings

1. The 17 un-migrated modules are the load-bearing constraint on this change: deprecated alias keys in `tokens.js` (`bg-cream-50` → `bg-systemGray-50`, etc.) keep their Tailwind classes resolving without any template edits, so the only diff reviewers see is `tokens.js` + `tokens.generated.css` + `tailwind.config.js`.
2. Newsreader is deletable in three coordinated moves (font binary + `@font-face` block + `useFontsLoaded.js` composable) because the system font has no FOUT risk — no replacement composable, no opt-in fallback, no metric-matched chain.
3. The Liquid-Glass effect requires white-on-white, not cream-on-cream: keeping cream under the glass makes the material light read as "frosted beige" instead of "frosted white", and the iOS chrome illusion breaks.
4. iOS system colors at hex level are stable since iOS 13 (2019); the iOS 13+ system color reference is the canonical source and `TokensModuleTest` literal-checks each value so any drift fails the build.
5. The 300 ms dashboard WS debounce is load-bearing for "motion does not fight motion" — the previous design preserves it, this change preserves it, and the iOS filled status chips re-key the visual state without touching the data flow.
