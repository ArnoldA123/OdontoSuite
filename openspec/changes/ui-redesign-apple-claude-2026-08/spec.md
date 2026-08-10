# Delta Spec: ui-redesign-apple-claude-2026-08

Phase 1 of the OdontoSuite redesign ("Apple chassis + Claude soul"). Nine
capability areas. All are NEW because no prior spec exists for them; the two
MODIFIED capabilities (`theme-machinery`, `asset-pipeline`) declare their
removed/replaced behavior inline.

Slice mapping: **PR1** (debt cleanup, ≤250 LOC), **PR2** (token layer +
primitive restyle, ≤600 LOC), **PR3** (Login + Dashboard + 404, ≤550 LOC).

User-locked decision (binding): display serif is **Newsreader, self-hosted** in
`public/fonts/`, declared with `@font-face` and `font-display: swap`. No Google
Fonts CDN `<link>` in production HTML. ~40 KB woff2 budget.

Verification legend: **UT** = unit test in
`tests/Unit/DesignSystem/TokensModuleTest.php` or extension;
**PW** = Playwright checkpoint against `php artisan serve` + `pnpm dev`;
**G** = static grep / `git ls-files` assertion in the slice's verification
script.

---

## 1. design-system-palette  (PR2)

### ADDED Requirements

#### Requirement: Token ramps and parity across SoT surfaces

The system SHALL expose four new color ramps (`cream`, `terracotta`,
`clinical-teal`, `ink`) and retain the existing semantic ramps
(`success`, `warning`, `error`, `info`) with `info` shifted from blue to
clinical-teal. Every ramp SHALL be present at the required steps:
`cream` {50, 100, 200}, `terracotta` {400, 500, 600, 700},
`clinical-teal` {500, 600}, `ink` {700, 800, 900},
`success/warning/error` {50, 100, 500, 600, 700},
`info` {500, 600}.

The canonical source of truth SHALL be `resources/js/design-system/tokens.js`.
`tailwind.config.js` SHALL import color values from `tokens.js` (not redefine
hex literals). `resources/css/themes.css` SHALL mirror every step as a CSS
custom property inside exactly one `:root` block. **UT** (`TokensModuleTest`)
parses `tokens.js` and `themes.css` and asserts hex-string equality step-by-step.
**G** `grep -r "#[0-9A-Fa-f]\{6\}" resources/css/themes.css | wc -l` equals the
count of token steps.

#### Scenario: Parity across JS, Tailwind, CSS

- GIVEN `tokens.js`, `tailwind.config.js`, `themes.css` are committed
- WHEN the test parses all three files
- THEN `tokens.js.terracotta[500] === "#C96442"` AND the CSS variable
  `--color-terracotta-500` exists with value `#C96442` AND Tailwind's
  `theme.extend.colors.terracotta[500]` is `"#C96442"`
- AND any mismatch between the three surfaces FAILS the test

#### Scenario: No dark-suffix tokens exist

- GIVEN PR1 has removed dark-mode machinery
- WHEN the test enumerates `tokens.js`
- THEN no key ends in `-dark`, `Dark`, or `_dark`
- AND no `dark:` variant is registered in `tailwind.config.js`

#### Requirement: Contrast contract — terracotta discipline

The system SHALL use `ink-800` (`#1F1B17`) on `cream-50` (`#FAF9F7`) for body
copy. `terracotta-500` SHALL appear ONLY on: primary CTA background (with
white text), secondary CTA border + label, links on cream surfaces, focus
rings, and badges. `terracotta` SHALL NEVER be used for body copy,
placeholder text, or as the fill of a non-interactive informational surface.
Errors SHALL use `error-600` (`#DC2626`), never `terracotta`.
**PW** (PR3 checkpoint 1 + 5) plus **UT** that scans `resources/css` for
`color: var(--color-terracotta-*)` and asserts no rule has it on a `<p>`,
`<span>` body selector.

#### Scenario: Body copy passes AAA

- GIVEN `ink-800` on `cream-50`
- WHEN a contrast ratio is computed
- THEN the ratio is ≥ 13:1 (WCAG AAA for body)

#### Scenario: Terracotta is reserved for accents

- GIVEN the restyled Login page
- WHEN the page is rendered and the test inspects the DOM
- THEN `terracotta-500` appears only on `.btn-primary`, `.link`,
  `.badge-accent`, and `:focus-visible` outline rules
- AND it does NOT appear as `color:` on `<p>`, `<li>`, `<label>`, or
  placeholder text

#### Requirement: Self-hosted Newsreader serif

The system SHALL self-host the Newsreader variable woff2 (with the `opsz`
optical-size axis) at `public/fonts/Newsreader-Variable.woff2`. The font SHALL
be declared via `@font-face` in `resources/css/themes.css` with
`font-display: swap`. The serif SHALL be referenced from the token layer as
`--font-serif`. The fallback stack SHALL be `ui-serif, 'New York', Georgia,
serif`. **G** asserts: no `<link rel="stylesheet" href="https://fonts.googleapis.com/...">`
in any blade or Vue file; the woff2 file is tracked and ≤ 50 KB on disk.

#### Scenario: Font loads on first paint

- GIVEN the page is loaded with a fresh cache
- WHEN the network tab is inspected
- THEN the woff2 is requested from the same origin (no third-party request)
- AND `font-display: swap` is present so text is visible during load

#### Scenario: Font fails to load — fallback stack renders

- GIVEN the woff2 request returns 404
- WHEN the page renders
- THEN `<h1>` displays in `ui-serif, 'New York', Georgia, serif`
- AND layout does not shift (CLS contribution from font load = 0)

#### Scenario: Reduced data preference respected

- GIVEN the user has `prefers-reduced-data: reduce` enabled
- WHEN the page is loaded
- THEN the woff2 is still requested (it is a sub-50 KB swap font), but no
  optional hero mp4 is fetched
- AND `font-display: swap` keeps text visible during any network delay

#### Requirement: Type scale with size-specific tracking and leading

The system SHALL define a type scale where tracking (letter-spacing) and
leading (line-height) are size-specific, never a single global value:
display ≥ 32px uses `letter-spacing: -0.02em` and `line-height: 1.05`;
body copy uses `letter-spacing: 0` and `line-height: 1.5`; small UI text
(≤ 14px) uses `letter-spacing: 0.01em` and `line-height: 1.4`. Display
headings SHALL set `font-optical-sizing: auto`. **UT** that asserts the
computed `letter-spacing` and `line-height` on `.h-display`, `.h-h1`,
`.body`, `.ui-sm` selectors.

#### Scenario: Tracking tightens for large display

- GIVEN `.h-display` at `font-size: clamp(2rem, 5vw, 4rem)`
- WHEN the browser computes style
- THEN `letter-spacing` is `-0.02em` and `line-height` is `1.05`
- AND `font-optical-sizing` is `auto`

#### Scenario: Body stays near zero tracking

- GIVEN `body { font-family: var(--font-sans); font-size: 1rem; }`
- WHEN computed
- THEN `letter-spacing` is `0` and `line-height` is `1.5`

#### Requirement: Anti-requirements (palette + type)

The system MUST NOT include a `prefers-color-scheme: dark` block in
`resources/css/themes.css`, `Avatar.vue`, or any other resource file.
The system MUST NOT introduce a `dark:` variant class.
The system MUST NOT use Fraunces or Instrument_Serif as display faces.
The system MUST NOT load a Google Fonts CDN link in production HTML.

#### Scenario: No dark-mode code

- GIVEN the codebase after PR2 merges
- WHEN `grep -r "prefers-color-scheme: dark" resources/` is run
- THEN it returns nothing (exit code 1, zero matches)

#### Scenario: No third-party font request

- GIVEN the production HTML emitted by `php artisan view:cache`
- WHEN a static grep for `fonts.googleapis.com` or `fonts.gstatic.com` runs
- THEN zero matches exist

---

## 2. motion-runtime  (PR2)

### ADDED Requirements

#### Requirement: `useSpring` composable contract

The system SHALL expose a Vue 3 composable `useSpring` that takes a target
value and animates a CSS variable on a given element from the **live
presentation value** (never the target) toward the target. On every new
target, the composable SHALL interrupt the in-flight animation and re-target
from the current on-screen value. When the consumer provides a release
velocity (gesture handoff), the composable SHALL seed the spring with that
velocity. The default damping SHALL be `1.0` (critically damped). When a
velocity is supplied, the composable SHALL switch to momentum mode with
damping `~0.8`. The composable SHALL check
`window.matchMedia('(prefers-reduced-motion: reduce)').matches` once at
construction and, if true, set the variable to the target instantly.

#### Scenario: Interruptible — re-target from current value

- GIVEN an element currently animating from `0` toward `100` at `t=200ms`
- WHEN the consumer sets a new target of `50`
- THEN the new animation begins from the live interpolated value (≈`57`)
- AND it does NOT jump back to `0` or to `100`

#### Scenario: Gesture release hands off velocity

- GIVEN a drag released at `velocity = 800 px/s` toward a snap point
- WHEN `useSpring({ target, velocity: 800 })` is called
- THEN the spring's initial velocity is `800 px/s` and no visible seam
  appears at release

#### Scenario: Critically damped by default

- GIVEN a target is set without a velocity
- WHEN the animation runs
- THEN it settles without overshoot (one crossing, monotonically
  approaching the target)

#### Scenario: Reduced motion degrades to instant

- GIVEN `prefers-reduced-motion: reduce` matches
- WHEN `useSpring` is constructed
- THEN the CSS variable is set to the target synchronously on the next
  frame, no animation runs

#### Requirement: Removal of global `* { transition: ... }`

The system MUST NOT contain a universal selector transition rule that
transitions background-color, color, border-color, or box-shadow on every
element. Transitions SHALL be scoped to explicit selectors
(`.btn-primary:hover`, `.card-glass:focus-within`, `.input:focus`). The
removal SHALL NOT cause a first-paint flicker — verified by snapshotting
`/login` and `/dashboard` immediately after `domcontentloaded` and asserting
the surface background color equals `cream-50` (no intermediate value).
**G** `grep -n '^\s*\*\s*{' resources/css/themes.css resources/css/tokens.css
resources/css/utilities.css | grep transition` returns zero matches.

#### Scenario: No universal transition selector

- GIVEN the CSS files after PR2
- WHEN grepped
- THEN zero rules of the form `* { transition: ... }` exist

#### Scenario: No first-paint flicker

- GIVEN a fresh page load
- WHEN the first frame is captured at `domcontentloaded`
- THEN `getComputedStyle(document.body).backgroundColor` is
  `rgb(250, 249, 247)` (`cream-50`), not a transient gray or white

#### Requirement: Anti-requirements (motion)

The composable MUST NOT animate `width`, `height`, `top`, or `left`. The
composables MUST NOT use CSS `@keyframes` for entrance motion on interactive
primitives. The composable MUST NOT lock out input during an animation
(`pointer-events: none` is forbidden on elements the user can grab mid-flight).

#### Scenario: Only transform/opacity animated

- GIVEN an element being sprung
- WHEN the WAAPI keyframes are inspected
- THEN only `transform` and/or `opacity` keyframes exist; no `width`,
  `height`, `top`, `left`

---

## 3. reduced-motion-contract  (PR2 + PR3)

### ADDED Requirements

#### Requirement: Honor all three accessibility signals at every primitive

Every Vue primitive in scope (Button, Card, Modal, Sheet, Input, Badge, Toast,
Skeleton, LoadingSpinner, EmptyState, Avatar, AppLayout, LoginPage,
DashboardPage, NotFoundPage) SHALL respect `prefers-reduced-motion`,
`prefers-reduced-transparency`, AND `prefers-contrast: more` independently.

- `prefers-reduced-motion: reduce` → entrance springs collapse to opacity
  cross-fade (≤ 200ms), no transform, no parallax, no shake.
- `prefers-reduced-transparency: reduce` → chrome surfaces set
  `backdrop-filter: none; background: var(--color-cream-100)` (solid).
- `prefers-contrast: more` → text lifts to `ink-900`, borders lift to
  `ink-700`, badge tints are removed (badge background = `cream-50`,
  badge text = `ink-900`).

**PW** (PR3 checkpoints 2, 3, 7) plus **UT** that parses each primitive's
template/style block for a `@media (prefers-reduced-motion: reduce)`,
`@media (prefers-reduced-transparency: reduce)`, and `@media
(prefers-contrast: more)` block.

#### Scenario: Reduced motion flattens entrance

- GIVEN Login page is loaded with `prefers-reduced-motion: reduce`
- WHEN the entrance animation would normally run
- THEN no transform is applied and the opacity reaches 1 in ≤ 200ms

#### Scenario: Reduced transparency solidifies chrome

- GIVEN AppLayout chrome (sidebar + top bar) with
  `prefers-reduced-transparency: reduce`
- WHEN computed styles are read
- THEN `backdrop-filter` is `none` and `background-color` is
  `rgb(242, 239, 233)` (`cream-100`)

#### Scenario: High contrast lifts text and borders

- GIVEN Dashboard stat cards under `prefers-contrast: more`
- WHEN computed styles are read
- THEN card text is `rgb(20, 17, 14)` (`ink-900`) and card border is
  `rgb(42, 38, 34)` (`ink-700`)

#### Requirement: Anti-requirements (a11y)

The system MUST NOT animate `width`/`height` under reduced-motion fallback.
The system MUST NOT remove focus rings under any preference. The system
MUST NOT disable transitions in a way that hides state changes from screen
readers (opacity-only fades require `aria-live` if the state carries meaning).

#### Scenario: Focus rings remain visible

- GIVEN any primitive under any media query
- WHEN it receives keyboard focus
- THEN a focus ring is computed (`:focus-visible` rule exists and renders)

---

## 4. theme-machinery (MODIFIED, PR1)

### REMOVED Requirements

#### Requirement: `useTheme` composable (REMOVED)

(Reason: dark mode is permanently removed per the proposal's locked
decision; the composable writes localStorage that nothing reads.)
(Migration: any consumer that imports `useTheme` SHALL be deleted or
rewritten to a no-op. A static grep must return zero matches.)

#### Scenario: No `useTheme` references

- GIVEN PR1 is merged
- WHEN `grep -rn "useTheme\|setTheme\|getThemeOptions" resources/`
- THEN zero matches exist (excluding test fixtures)

#### Requirement: `ThemeSelector.vue` (REMOVED)

(Reason: dead UI commented out in `AppLayout.vue`; dark mode is removed.)
(Migration: the file is deleted; any future "theme" key in localStorage is
read-once and ignored.)

#### Scenario: Component deleted

- GIVEN the file system after PR1
- WHEN `git ls-files resources/js/components/ui/ThemeSelector.vue` runs
- THEN exit code is non-zero (file is untracked / deleted)

#### Requirement: `localStorage.theme` (NO-OP READ)

(Reason: any existing user with a `theme` key in localStorage must not
crash the app or re-introduce dark mode on next visit.)
(Migration: a one-line read-once at app bootstrap ignores the value and
never re-writes. The `tokens.js` SoT has no `theme` concept.)

#### Scenario: Pre-existing theme key is ignored

- GIVEN `localStorage.setItem('theme', 'dark')` from a prior session
- WHEN the app boots
- THEN it reads the key, takes no action, and does not change rendered
  styles
- AND it does not re-write the key (no console warning either)

#### Requirement: Dark-mode CSS blocks (REMOVED)

(Reason: no dark mode; `prefers-color-scheme: dark` rules are dead code.)
(Migration: deleted from `themes.css` and `Avatar.vue`.)

#### Scenario: No dark blocks

- GIVEN the codebase after PR1
- WHEN `grep -rn "prefers-color-scheme: dark" resources/` runs
- THEN zero matches

#### Requirement: `MobileNavigation.vue` (REMOVED)

(Reason: dead navigation; superseded by `MobileMenu.vue`.)
(Migration: file deleted; no consumer replacement needed.)

#### Scenario: MobileNavigation file gone

- GIVEN PR1 merged
- WHEN `git ls-files resources/js/components/layout/MobileNavigation.vue`
  runs
- THEN the file is absent

#### Requirement: `design-system.js` stale duplicate (REMOVED)

(Reason: drifted from `tokens.js`; nobody imports it.)
(Migration: file deleted; canonical is `resources/js/design-system/tokens.js`.)

#### Scenario: Stale duplicate gone

- GIVEN PR1 merged
- WHEN `grep -rn "from.*design-system.js" resources/` runs
- THEN zero matches

---

## 5. asset-pipeline (MODIFIED, PR2 + PR3)

### MODIFIED Requirements

#### Requirement: Committed image assets

The system SHALL commit exactly two new image assets in this change:

- `public/images/pexels/auth/login/6812463_modern-dental_p2.jpg`
  (≤ 60 KB, hero still fallback)
- `public/images/pexels/errors-404/4439425_page-404_p3.jpg`
  (≤ 30 KB, 404 page)

**G** `git ls-files public/images/pexels/auth/login/6812463_modern-dental_p2.jpg`
exits 0 AND the file size on disk is ≤ 60 KB.

#### Scenario: Hero still is committed

- GIVEN the repo after PR3
- WHEN the file is inspected
- THEN it exists at the documented path with size ≤ 60 KB

#### Scenario: 404 image is committed

- GIVEN the repo after PR3
- WHEN the file is inspected
- THEN it exists at the documented path with size ≤ 30 KB

#### Requirement: `.gitignore` rule for heavy mp4s

The system SHALL add `.gitignore` entries that exclude all
`public/images/pexels/**/_v*.mp4` EXCEPT optionally one chosen login
candidate (if and only if Phase 1 needs video). **G** `git ls-files | grep
_v1.mp4` returns zero or one row, and the row's path matches the chosen
login candidate only.

#### Scenario: Heavy mp4 stays out

- GIVEN `public/images/pexels/auth/login/10189094_closeup_v1.mp4` exists
  on disk (71.7 MB)
- WHEN `git ls-files` runs
- THEN the file is NOT tracked

#### Scenario: Login video policy

- GIVEN the chosen login candidate is `6763242_clinic_v1.mp4` re-encoded
  via `ffmpeg` to H.264 720p ≤ 2 MB with a JPEG poster frame
- WHEN `git ls-files` runs
- THEN either zero or exactly one mp4 is tracked, and its size is ≤ 2 MB

#### Requirement: Byte budget enforcement

No single committed asset SHALL exceed 5 MB. The PR's CI SHALL fail if
any added file under `public/images/pexels/` exceeds this budget.
**G** `git diff --stat <base>..HEAD -- 'public/images/**' | awk '{print $3}'`
shows no line with a number greater than `5120` (KB).

#### Scenario: Budget cap holds

- GIVEN PR3 adds three image files
- WHEN sizes are summed
- THEN each individual file is ≤ 5120 KB and the total added is ≤ 110 KB

#### Requirement: Anti-requirements (assets)

The system MUST NOT commit any `.mp4` larger than 2 MB. The system MUST NOT
ship Pexels photography inside data cards (stat cards, appointment rows,
forms). The system MUST NOT auto-play video with audio.

#### Scenario: No photography in data cards

- GIVEN the Dashboard renders stat cards and appointment rows
- WHEN the DOM is inspected
- THEN no `<img>` element exists inside `.stat-card` or `.appointment-row`
  (icons/illustrations are SVG inline; the only photographic surface is
  the Login hero and the 404 page)

---

## 6. login-experience  (PR3)

### ADDED Requirements

#### Requirement: Layout — editorial split hero

The `LoginPage.vue` SHALL render as a two-column layout on viewports ≥
768px: left column contains brand mark, headline, subhead, and form;
right column contains the hero image with a translucent overlay. Below
768px, the layout SHALL collapse to single-column and the right column
becomes a 200px-tall hero strip above the form.

#### Scenario: Desktop shows two columns

- GIVEN viewport width 1280px
- WHEN the page renders
- THEN `.login-grid` has exactly 2 columns
- AND the left column width is 50% of the grid

#### Scenario: Mobile collapses to single column

- GIVEN viewport width 375px
- WHEN the page renders
- THEN `.login-grid` has exactly 1 column
- AND the hero is a strip above the form, height ≤ 220px

#### Requirement: States — idle, validating, submitting, error, success

The form SHALL expose five observable states. Transitions SHALL be
announced via `aria-live="polite"` on a status region:

- **idle**: inputs are interactive, primary button enabled.
- **validating**: blur on each field triggers inline validation; invalid
  fields get `aria-invalid="true"` and a visible error message below.
- **submitting**: primary button shows a spinner, is disabled, and
  `aria-busy="true"` is set on the form. Inputs are disabled.
- **error**: a non-field error (e.g., wrong credentials) appears above
  the form with `role="alert"`. The form returns to idle after 1.5s or
  on next interaction.
- **success**: navigation to `/dashboard` occurs; a toast announces
  success with `aria-live="polite"`.

#### Scenario: Submitting state disables inputs

- GIVEN the user clicks "Ingresar" with valid fields
- WHEN the request is in flight
- THEN all inputs are `disabled`, the button shows a spinner, and
  `aria-busy="true"` is present on `<form>`

#### Scenario: Error is announced

- GIVEN the API returns 401
- WHEN the response is rendered
- THEN a `role="alert"` region contains the Spanish error text and is
  focused programmatically

#### Requirement: Keyboard and screen-reader behavior

The form SHALL be fully operable with keyboard only. Tab order SHALL be:
brand-mark-link (skip), username, password, primary button,
forgot-password link. The first focusable element on page load SHALL be
the username input. Focus rings SHALL be visible on all interactive
elements (terracotta outline, 2px, 2px offset). Each input SHALL have a
programmatically associated `<label>` (no placeholder-as-label).

#### Scenario: First focus is username

- GIVEN the Login page is loaded with no prior interaction
- WHEN the user presses Tab once
- THEN the username input receives focus

#### Scenario: Labels are programmatic

- GIVEN the Login page DOM
- WHEN inspected
- THEN each `<input>` has an `<label for>` association AND a visible
  label OR `aria-label` (no placeholder-as-label)

#### Requirement: Hero media with poster fallback

The right column SHALL render an `<img>` (the committed JPEG) with a
`loading="lazy"` attribute and `decoding="async"`. If the optional mp4
is committed, it SHALL be layered behind the poster with
`preload="metadata"`, no `autoplay`, and `aria-hidden="true"`. The image
MUST have descriptive `alt` text in Spanish.

#### Scenario: Image loads lazily with alt text

- GIVEN the Login page is rendered
- WHEN the DOM is inspected
- THEN the hero `<img>` has `alt="Equipo dental moderno"` AND
  `loading="lazy"`

#### Scenario: Optional video does not autoplay

- GIVEN the optional login mp4 is committed
- WHEN the `<video>` element is inspected
- THEN it has no `autoplay` attribute AND `aria-hidden="true"` AND a
  `poster` attribute pointing to the JPEG

#### Requirement: Forgot/Reset password modals

`ForgotPasswordModal.vue` and `ResetPasswordModal.vue` SHALL restyle on
new tokens. The dev-only `reset_token` field SHALL NOT be exposed in the
UI flow (kept only on the API surface for tests). Each modal SHALL trap
focus, restore focus on close, and dismiss on `Esc`.

#### Scenario: Reset token is not in the UI

- GIVEN the Reset Password modal
- WHEN rendered
- THEN no `<input>` has name or id matching `reset_token` (a static
  grep across the modal source returns zero)

#### Scenario: Focus is trapped

- GIVEN the Forgot Password modal is open
- WHEN the user presses Tab repeatedly
- THEN focus cycles within the modal and does not escape to the page
  underneath

---

## 7. dashboard-experience  (PR3)

### ADDED Requirements

#### Requirement: Five stats, five quick actions, today's appointments

The Dashboard SHALL render three regions in this order:

1. **Stats row** — five stat cards. Labels are verified from the current
   `DashboardPage.vue` and MUST NOT be invented: "Citas Hoy",
   "Pacientes" (sub: "Total registrados"), "Profesionales" (sub: "Equipo
   médico"), "Total Citas" (sub: "Este mes"), "Estado de Caja". Three of
   the five are permission-gated (`viewAppointment`, `manageUsers`,
   `viewCashRegister`), so fewer than five may render.
2. **Quick actions row** — five quick-action cards, verified labels:
   "Pacientes", "Nueva Cita", "Profesionales", "Ambientes", "Reportes".
3. **Today's appointments** — list of up to 3 appointment rows for the
   current day, each showing time, patient, appointment type, and status
   pill. (The current implementation caps at 3, not 10.)

All cards use the `Card` primitive. `variant="glass"` is the existing
data-card surface used by all five stat cards today and is **opaque**
after the redesign (cream-100, ink-200 hairline, shadow-medium, no
`backdrop-filter`) — see design Decision 5. Stats and
quick actions SHALL be in CSS Grid (`grid-cols-1 md:grid-cols-3
lg:grid-cols-5 gap-4`).

#### Scenario: Layout has three regions

- GIVEN an authenticated session
- WHEN the dashboard renders
- THEN it contains exactly one stats row, one quick-actions row, and
  one appointments list, in that vertical order

#### Scenario: Grid collapses on mobile

- GIVEN viewport width 375px
- WHEN the dashboard renders
- THEN stats and quick actions each render as a single column

#### Requirement: Loading skeletons, empty states, error states

The Dashboard SHALL expose three states:

- **loading**: five skeleton stat cards, five skeleton quick actions,
  three skeleton appointment rows — all using the `Skeleton` primitive
  with shimmer matching the final card height.
- **empty**: when no appointments exist for today, an `EmptyState`
  primitive shows the heading "Sin citas para hoy" and a CTA "Agendar
  nueva cita".
- **error**: if the API returns 5xx or network fails, each region shows
  an inline error with a "Reintentar" button; the page does NOT crash.

#### Scenario: Skeleton shape matches final

- GIVEN the Dashboard is in loading state
- WHEN the skeleton is measured
- THEN the stat-card skeleton has the same width AND height (within
  ±2px) as the loaded stat card

#### Scenario: Empty state is reachable

- GIVEN an authenticated user with zero appointments for today
- WHEN the dashboard renders
- THEN `EmptyState` is visible with the documented copy and CTA

#### Requirement: WebSocket live-update path

The dashboard subscribes to a `dashboard.today-updated` event via the
existing `useEcho` composable (debounced 300ms trailing-edge per the
proposal). Updates SHALL NOT replay the entrance motion. The mechanism:
the affected card receives a brief `useSpring` highlight pulse
(`response 0.2, damping 1.0`) on the new value, while the rest of the
layout stays put. The pulse SHALL respect `prefers-reduced-motion`.

#### Scenario: Live update does not replay entrance

- GIVEN the dashboard has finished its entrance motion
- WHEN a WS event arrives updating one stat
- THEN only that stat card pulses briefly; no other element's transform
  or opacity re-runs

#### Scenario: Live update under reduced motion

- GIVEN `prefers-reduced-motion: reduce` is active
- WHEN a WS event arrives
- THEN the stat value updates with an opacity cross-fade only, no
  transform

#### Requirement: Anti-requirements (dashboard)

The system MUST NOT use inline gradient backgrounds (`bg-gradient-*`,
`linear-gradient`, hand-rolled CSS gradients) on any dashboard surface.
The system MUST NOT embed `<style scoped>` blocks inside `DashboardPage.vue`
(removed in PR3). The system MUST NOT contain photography in stat cards or
appointment rows.

#### Scenario: No inline gradients

- GIVEN the restyled Dashboard
- WHEN `grep -n "linear-gradient\|bg-gradient" resources/js/modules/dashboard/DashboardPage.vue`
- THEN zero matches

#### Scenario: Scoped style block removed

- GIVEN PR3 merged
- WHEN the file is inspected
- THEN it contains no `<style scoped>` block

---

## 8. app-shell  (PR3)

### ADDED Requirements

#### Requirement: Translucent sidebar and top bar

`AppLayout.vue` SHALL render the sidebar and top bar as translucent
layers with `backdrop-filter: blur(20px) saturate(180%)` and a
semi-transparent cream background. A 1px inner highlight border SHALL be
present. The chrome SHALL be tagged with a comment "Liquid-Glass web
approximation — not Apple's native Liquid Glass". Under
`prefers-reduced-transparency: reduce`, the chrome SHALL set
`backdrop-filter: none; background: var(--color-cream-100)`.

#### Scenario: Chrome is translucent by default

- GIVEN AppLayout renders with no reduced-transparency preference
- WHEN computed styles are read on `.sidebar` and `.topbar`
- THEN `backdrop-filter` is non-`none` and includes `blur(` with a value
  between 16 and 28 px

#### Scenario: Chrome is solid under reduced transparency

- GIVEN `prefers-reduced-transparency: reduce` matches
- WHEN computed styles are read
- THEN `backdrop-filter` is `none` AND `background-color` is
  `rgb(242, 239, 233)` (`cream-100`)

#### Requirement: Mobile sheet navigation

On viewports < 768px, the sidebar SHALL collapse into a sheet that
slides in from the left. The sheet SHALL use the `Sheet` primitive with
the documented momentum spring (`response 0.3, damping 0.8`) and SHALL
trap focus while open. Opening the sheet SHALL NOT replay entrance
motion on the page underneath.

#### Scenario: Mobile sheet slides from left

- GIVEN viewport width 375px and the hamburger is tapped
- WHEN the sheet mounts
- THEN it transitions from `translateX(-100%)` to `translateX(0)` along
  the X axis only (no Y motion)

#### Scenario: Focus is trapped in mobile sheet

- GIVEN the mobile sheet is open
- WHEN Tab is pressed repeatedly
- THEN focus cycles within the sheet's nav links

#### Requirement: Wayfinding — every screen answers four questions

Every screen in scope (Login, Dashboard, 404) SHALL answer:

1. **Where am I?** — current location is visible (page title, breadcrumb,
   or active-nav indicator).
2. **Where can I go?** — primary navigation is reachable in ≤ 1 tab.
3. **What's there?** — primary content is the first thing below the
   fold-aware chrome.
4. **How do I get out?** — at least one escape path exists on every
   modal/sheet (close button + Esc + outside-click for modals; back
   affordance for 404).

**PW** (PR3 checkpoints 1, 5, 6) plus a static check that every `<main>`
or page-equivalent has a `<h1>`.

#### Scenario: Every screen has an H1

- GIVEN Login, Dashboard, 404 pages
- WHEN the DOM is inspected
- THEN each page has exactly one `<h1>` element

#### Scenario: 404 has an escape

- GIVEN the 404 page is rendered
- WHEN the DOM is inspected
- THEN a "Volver al inicio" link exists pointing to `/login` AND a
  browser-back affordance is present

#### Requirement: Anti-requirements (app shell)

The system MUST NOT stack two translucent surfaces on top of each other.
The system MUST NOT use `h-screen` for full-height pages (use
`min-h-[100dvh]`).

#### Scenario: No h-screen on full-height pages

- GIVEN `AppLayout.vue`
- WHEN `grep -n "h-screen\|height: 100vh" resources/js/components/layout/AppLayout.vue`
  runs
- THEN zero matches

---

## 9. not-found-experience  (PR3)

### ADDED Requirements

#### Requirement: 404 page composition

`NotFoundPage.vue` SHALL render: the committed 404 image (≤ 30 KB JPEG),
a single headline ("Página no encontrada"), a one-line subhead, and a
primary CTA "Volver al inicio" linking to `/login`. The page SHALL enter
with a single `useSpring` entrance on the headline only (response 0.35,
damping 1.0). The image SHALL have descriptive Spanish `alt` text.

#### Scenario: 404 has the documented elements

- GIVEN an unmatched route
- WHEN the 404 page renders
- THEN it contains one `<h1>`, one paragraph, one link to `/login`,
  AND one `<img>` with the committed JPEG

#### Scenario: 404 entrance is interruptible

- GIVEN the 404 page entrance is in flight
- WHEN the user navigates away (clicks the CTA) within 200ms
- THEN the CTA navigation completes; the entrance spring is cancelled
  cleanly (no orphaned keyframes)

#### Requirement: Anti-requirements (404)

The system MUST NOT show a generic "500" or stack-trace on 404. The
system MUST NOT auto-redirect without user consent.

#### Scenario: No auto-redirect

- GIVEN a 404
- WHEN the page renders
- THEN no `<meta http-equiv="refresh">` is present AND no
  `router.replace` runs automatically

---

## Cross-cutting: verification summary per slice

| Slice | Grep assertions | Unit tests | Playwright checkpoints |
|---|---|---|---|
| **PR1** | no `prefers-color-scheme: dark`, no `useTheme\|ThemeSelector\|design-system.js\|MobileNavigation` | `TokensModuleTest` still passes (no dark-suffix tokens) | manual: app loads identically |
| **PR2** | token parity, single `:root`, no `* { transition }`, no Google Fonts CDN | `TokensModuleTest` extended for cream/terracotta/clinical-teal/ink ramps | visual diff vs pre-PR2 baseline (near-identical) |
| **PR3** | no `linear-gradient` in Dashboard, no `h-screen` in AppLayout, no `<style scoped>` in Dashboard, no reset_token input | — | checkpoints 1-7 (login, dashboard, 404, reduced-motion, reduced-transparency, contrast) |

## Cross-cutting: anti-requirements index

- MUST NOT use `Fraunces` or `Instrument_Serif`.
- MUST NOT ship a Google Fonts `<link>` in production HTML.
- MUST NOT animate `width`/`height`/`top`/`left`.
- MUST NOT contain a global `* { transition: ... }` rule.
- MUST NOT contain a `prefers-color-scheme: dark` block anywhere.
- MUST NOT commit any `.mp4` > 2 MB or any asset > 5 MB.
- MUST NOT use inline gradients on dashboard surfaces.
- MUST NOT embed photography in data cards.
- MUST NOT auto-play video with audio.
- MUST NOT auto-redirect from 404.
- MUST NOT remove focus rings under any preference.
- MUST NOT use `h-screen` for full-height pages.
- MUST NOT use placeholder-as-label on inputs.
- MUST NOT use `<style scoped>` inside `DashboardPage.vue`.
- MUST NOT expose `reset_token` in the Reset Password UI flow.
