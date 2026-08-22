# Spec: ui-rollout-hotfix-premium-2026-08

**Change**: ui-rollout-hotfix-premium-2026-08 (SDD phase: spec)
**Scope**: Login + Dashboard only
**Design authority**: apple-design + design-taste-frontend skills (binding)
**RFC 2119 keywords**: MUST / SHOULD / MAY

---

## Domain 1: Login (PR-hotfix-login)

### HOTFIX-LOGIN-001 — Brand mark uses wordmark + SF-style glyph, NOT generic favicon PNG
- **MUST**: The brand mark area in `<header class="login-header">` MUST contain (a) the literal text `OdontoSuite` rendered in the system font and (b) an inline SVG glyph with `stroke-width="1.75"` and no fill, sized 24×24.
- **MUST NOT**: The brand mark MUST NOT contain an `<img>` element referencing `easy_dent.png` or any raster favicon.
- **Rule cited**: design-taste §0.D (anti-default discipline); apple-design §15 (system font wordmark).
- **Test**: `HotfixLoginBrandMarkTest` — assert `<header>` contains text "OdontoSuite" AND contains `<svg>` with `stroke-width="1.75"`. Assert NO `<img>` with `src` ending `easy_dent.png`.

### HOTFIX-LOGIN-002 — Headline optical sizing at display scale
- **MUST**: The `<h1 id="login-headline">` MUST compute `letter-spacing` to `-0.025em × font-size` or tighter at viewport ≥ 1024px.
- **MUST**: `line-height` MUST be `1.05` or `1.1` (not relaxed).
- **MUST NOT**: Headline MUST NOT use `letter-spacing: 0` (forbidden for display scale per apple-design §15).
- **Rule cited**: apple-design §15 typography (size-specific tracking).
- **Test**: `HotfixLoginHeadlineTrackingTest` — assert computed `letter-spacing` ≤ `-0.025em × font-size` at 1440 viewport.

### HOTFIX-LOGIN-003 — Subtitle uses readable size
- **MUST**: Subtitle `<p class="welcome-subtitle">` MUST compute `font-size` ≥ `1rem` (16px).
- **SHOULD**: `font-size: 1.0625rem` (17px) preferred per system font body-large.
- **Rule cited**: apple-design §15 (default body 17px), design-taste §4.7 (hero subtext ≤ 20 words).
- **Test**: `HotfixLoginSubtitleTest` — assert computed `font-size` ≥ 16px.

### HOTFIX-LOGIN-004 — Primary submit button has elevation + inset highlight + tinted shadow
- **MUST**: `<UiButton variant="primary">` MUST apply `box-shadow` containing ALL of:
  - `var(--elevation-3)` (or higher rung)
  - `inset 0 1px 0 rgba(255, 255, 255, X)` where X ≥ 0.25
  - Optional outer tinted shadow for primary keys
- **MUST**: Primary button on `:active` MUST apply `transform: translateY(1px)`.
- **MUST NOT**: Primary button MUST NOT render as flat solid rectangle without inset highlight.
- **Rule cited**: apple-design §1 (response on pointer-down), §12 (heavier material on bigger CTA), §16 (craft — detail matters).
- **Test**: `ButtonConstructionTest` (project-wide, not login-specific) — assert ALL primary buttons across the app have the elevation + inset. Pin RULE, not example.

### HOTFIX-LOGIN-005 — Form card surface uses elevated variant on solid canvas
- **MUST**: `<Card>` wrapping the login form MUST NOT use `variant="glass"` (glass on solid canvas reads flat per tokens.js comment).
- **MUST**: Card surface MUST compute `box-shadow` containing `var(--elevation-2)` (or higher) AND `border: 1px solid var(--color-hairline)`.
- **SHOULD**: `border-radius: var(--radius-card-lg)` (16px).
- **Rule cited**: apple-design §12 (bigger surfaces read thicker — strong blur + deeper shadow on glass; but on solid canvas use elevation + hairline + soft inset highlight).
- **Test**: `HotfixLoginCardSurfaceTest` — assert `<Card>` `variant` is NOT `glass`. Assert computed `box-shadow` includes `var(--elevation-2)`.

### HOTFIX-LOGIN-006 — Hero column is NOT the stock dental photo
- **MUST NOT**: Hero column MUST NOT contain `<img>` with `src` ending `login-hero.jpg`.
- **MUST**: Hero column MUST be an editorial composition — typography-led wordmark + abstract line-art SVG(s), on `var(--color-canvas)` background.
- **MUST**: Hero MUST NOT use generic AI-default gradient blobs.
- **Rule cited**: design-taste §4.8 (real images — no stock dental photos), §9.F ("NO div-based fake product UI in the hero" — small line-art SVGs are OK).
- **Test**: `HotfixLoginHeroSourceTest` — assert NO `<img>` with `src` ending `login-hero.jpg`. Visual Playwright check that hero reads editorial.

### HOTFIX-LOGIN-007 — Mirror spring entrance (form + hero)
- **MUST**: The form-wrap MUST attach a `useSpring({ damping: 1.0, response: 0.35 })` for entrance.
- **MUST**: The hero column MUST attach a `useSpring({ damping: 1.0, response: 0.45 })` (slower mirror).
- **MUST**: Under `prefers-reduced-motion: reduce`, BOTH springs MUST collapse to instant.
- **Rule cited**: apple-design §4 (critically damped default), §7 (spatial consistency — mirror easing).
- **Test**: `HotfixLoginMirrorSpringTest` — assert two distinct `useSpring` instances attached. Playwright with `prefers-reduced-motion: reduce` emulation asserts no transform present.

### HOTFIX-LOGIN-008 — Zero em-dashes in visible text
- **MUST**: NO `—` character in any visible string on the Login page (template text, caption, footer note).
- **Rule cited**: design-taste §9.G (binary ban).
- **Test**: `HotfixLoginEmDashAuditTest` — scan all visible text nodes for U+2014. Assert zero matches.

### HOTFIX-LOGIN-009 — Reduced-transparency + High-contrast media queries
- **MUST**: `@media (prefers-reduced-transparency: reduce)` MUST flatten the hero overlay to solid (already present).
- **MUST**: `@media (prefers-contrast: more)` MUST lift text contrast (already present).
- **Rule cited**: apple-design §14 (reduced motion + accessibility).
- **Test**: Playwright toggles for both preferences, asserts legibility (contrast ratio check on key text).

---

## Domain 2: Dashboard (PR-hotfix-dashboard)

### HOTFIX-DASH-001 — Sidebar section labels removed (no uppercase tracking)
- **MUST**: Sidebar MUST NOT contain any element with `text-xs uppercase tracking-[0.18em]` (or similar uppercase-tracking class) for section labels.
- **MUST**: Sections MUST be separated by a 1px `var(--color-hairline)` horizontal divider instead of text labels.
- **Rule cited**: design-taste §9.F "Section-Numbering Eyebrows" + §4.7 "EYEBROW COUNT (mechanical)".
- **Test**: `SidebarEyebrowAuditTest` — regex scan `AppLayout.vue` template for `tracking-[0.18em]` or `uppercase tracking-`. Assert zero matches for section labels.

### HOTFIX-DASH-002 — KPI cards have NO icon-in-rounded-gray-box
- **MUST**: KPI card surface MUST NOT contain a `<div>` or `<span>` with classes combining `rounded` AND `bg-system-gray-*` (or `bg-neutral-*`) AND small icon size — the Material-leak pattern.
- **SHOULD**: KPI card MAY include a small accent dot (4px circle, `var(--color-system-blue-500)`) or no glyph at all.
- **Rule cited**: design-taste §9.D (NO three-equal Material cards), apple-design §16 (icon stroke 1.5 — not icon-in-box).
- **Test**: `IconInBoxAuditTest` — scan DashboardPage.vue for the Material icon-in-box pattern. Assert zero matches on KPI cards.

### HOTFIX-DASH-003 — KPI numbers use tabular-nums
- **MUST**: The big number element on each KPI card MUST compute `font-feature-settings` containing `"tnum" 1`.
- **Rule cited**: apple-design §15 (tabular nums for numbers).
- **Test**: `KpiNumberTabularTest` — assert computed `font-feature-settings` includes `"tnum"` on each KPI number element.

### HOTFIX-DASH-004 — KPI cards have hover lift
- **MUST**: KPI card surface MUST apply `:hover { transform: translateY(-1px); box-shadow: var(--elevation-2); transition: transform 200ms var(--motion-easing-ios), box-shadow 200ms var(--motion-easing-ios); }`.
- **Rule cited**: apple-design §4 (springs for entrance — hover uses CSS transition), §12 (deeper shadow on hover).
- **Test**: Playwright `:hover` simulation — assert computed `transform` includes `translateY(-1px)` and `box-shadow` references `var(--elevation-2)`.

### HOTFIX-DASH-005 — Quick Actions card surface matches KPI cards
- **MUST**: Quick Actions cards MUST use the same surface treatment as KPI cards (`var(--color-surface-elevated)` + `var(--elevation-1)` + hairline + `var(--radius-card-lg)`).
- **Rule cited**: design-taste §4.4 (Shape Consistency Lock — same radius system across cards).
- **Test**: `HotfixDashboardSurfaceConsistencyTest` — assert Quick Action cards and KPI cards share `border-radius`, `box-shadow` token references, and surface color.

### HOTFIX-DASH-006 — Quick Actions NO letter-key badge
- **MUST NOT**: Quick Actions cards MUST NOT contain a `<span>` or `<kbd>` element displaying a single uppercase letter as a shortcut badge.
- **Rule cited**: design-taste §9.D (no Material keyboard-shortcut reference visual).
- **Test**: `HotfixDashboardQuickActionsTest` — assert NO Quick Action card has a shortcut badge `<span>`.

### HOTFIX-DASH-007 — Empty state uses line-art SVG + primary CTA
- **MUST**: The "Citas de Hoy" empty state MUST contain:
  - An inline `<svg>` line-art calendar icon at `stroke-width="1.5"`, 24×24, in `var(--color-label-tertiary-label)`.
  - A primary CTA `<UiButton variant="primary">` with text "Crear nueva cita".
- **MUST NOT**: Empty state MUST NOT be a div-based fake screenshot.
- **SHOULD**: Subtle radial-gradient backdrop (`radial-gradient(circle at center, var(--color-system-blue-50) 0%, transparent 70%)`).
- **Rule cited**: apple-design §12 (translucent chrome for depth — radial gradient as subtle depth), §16 (icon stroke 1.5); design-taste §9.F (no div-based fake UI).
- **Test**: `HotfixDashboardEmptyStateTest` — assert empty state contains SVG with `stroke-width="1.5"` AND a primary button with text matching `/crear.*cita/i`.

### HOTFIX-DASH-008 — Greeting date uses tabular-nums
- **MUST**: The date string in the greeting block MUST compute `font-feature-settings` containing `"tnum" 1`.
- **Rule cited**: apple-design §15.
- **Test**: `HotfixDashboardDateTabularTest` — assert computed `font-feature-settings` includes `"tnum"`.

### HOTFIX-DASH-009 — Per-section staggered springs (4 sections, 60ms stagger)
- **MUST**: Dashboard MUST attach `useSpring({ damping: 1.0, response: 0.35 })` to each of: greeting block, KPI grid, Quick Actions, Citas de Hoy empty state.
- **MUST**: Sections MUST enter with stagger delays of 0ms, 60ms, 120ms, 180ms (via `setTimeout` post-mount).
- **MUST**: Under `prefers-reduced-motion: reduce`, ALL four springs MUST collapse to instant.
- **Rule cited**: apple-design §4 (springs for entrance), §8 (intermediate frames telegraph direction).
- **Test**: `HotfixDashboardStaggerTest` — assert 4 distinct spring instances. Playwright with `prefers-reduced-motion: reduce` asserts no transform present.

### HOTFIX-DASH-010 — Reduced-motion + High-contrast media queries on Dashboard
- **MUST**: `@media (prefers-reduced-motion: reduce)` MUST kill all 4 staggered springs.
- **MUST**: `@media (prefers-contrast: more)` MUST lift text contrast on greeting + KPI labels.
- **Rule cited**: apple-design §14.
- **Test**: Playwright toggles both, asserts legibility.

### HOTFIX-DASH-011 — Zero em-dashes in visible text
- **MUST**: NO `—` character in any visible string on the Dashboard page.
- **Rule cited**: design-taste §9.G.
- **Test**: `HotfixDashboardEmDashAuditTest` — scan all visible text nodes for U+2014. Assert zero matches.

---

## Cross-cutting

### HOTFIX-CROSS-001 — Tokens.js unchanged
- **MUST**: No mutation to `resources/js/design-system/tokens.js` or `resources/css/tokens.generated.css` (except regeneration if a new token is added, which is out of scope).
- **Rule cited**: inherited from vertical slice standing guard rails.

### HOTFIX-CROSS-002 — No new primitives
- **MUST**: No new components in `resources/js/components/ui/` beyond `Card` `variant="elevated"` (one-line addition).
- **Rule cited**: inherited from vertical slice standing guard rails.

### HOTFIX-CROSS-003 — Per-PR budget ≤ 400 lines
- **MUST**: PR-hotfix-login ≤ 280 lines.
- **MUST**: PR-hotfix-dashboard ≤ 380 lines.
- **Rule cited**: review budget 400 lines, raised from vertical slice precedent.

### HOTFIX-CROSS-004 — All unit tests pass on MySQL
- **MUST**: `php artisan test --testsuite=Unit` MUST pass.
- **MUST**: `php artisan test --testsuite=Feature --filter=Hotfix` MUST pass (new tests).
- **Rule cited**: standing guard rails (MySQL only).

### HOTFIX-CROSS-005 — Playwright visual verification at 1440x900 + 390x844
- **MUST**: Capture before/after screenshots of /login and /dashboard at both viewports.
- **MUST**: Capture toggled states for `prefers-reduced-motion: reduce`, `prefers-reduced-transparency: reduce`, `prefers-contrast: more`.
- **Rule cited**: vertical slice precedent (proposal §8 success criteria).

### HOTFIX-CROSS-006 — Conventional commits, no AI attribution
- **MUST**: Commit messages follow Conventional Commits format.
- **MUST NOT**: No `Co-Authored-By` Claude or AI attribution in commits.
- **Rule cited**: global CLAUDE.md rule.

---

## Out-of-scope (explicit)

- 404 page (user-deferred to a future change).
- All 17 un-migrated modules (Profesionales, Caja, BI, Calendar, etc.).
- `tokens.js` mutation.
- New primitives beyond `Card variant="elevated"`.
- Dark mode.
- Sidebar structural changes (icons + labels stay).
- Auth flow, route slugs, form field names.
- Any database migration.
