# Premium Design Foundation — Specification

## Purpose

Defines the token-first foundation (canvas/surface separation, tinted elevation, alpha hairlines, nested radii, durations, focus ring, font features), the primitive interaction states that consume that foundation, and the exemplar application on the three acceptance screens (Dashboard, Login, 404). Information architecture does not move; only material and interaction.

## ADDED Requirements

### Material layer

### Requirement: Canvas color is an additive token that wraps the page background

A NEW key `tokens.colors.background.canvas` MUST be added with value `'#F2F2F7'` (the existing systemGray-50 hex is reused, so no new hex literal enters the file). The existing `tokens.colors.background.systemBackground` MUST remain `'#FFFFFF'` and MUST NOT be mutated. The page/body wrapper of the three exemplar screens MUST consume `background.canvas`; cards and other surface primitives MUST continue to consume `background.systemBackground`. This produces visible canvas-vs-surface contrast without repainting the rest of the app, which still reads `systemBackground` directly.

#### Scenario: Dashboard and 404 share the canvas via a wrapping element

- GIVEN a logged-in user at `/dashboard` and an unauthenticated user at `/404`
- WHEN the rendered `background-color` of the page-wrapper element is read at 1440x900
- THEN both pages compute `rgb(242, 242, 247)` (the canvas token)
- AND the rendered `background-color` of a card computes `rgb(255, 255, 255)` (the surface token)
- AND `tokens.colors.background.systemBackground` still equals `#FFFFFF` (unchanged)

#### Scenario: Other modules keep their current appearance

- GIVEN any module not in the three exemplar screens
- WHEN its existing `background.systemBackground` consumer renders
- THEN its computed `background-color` is unchanged from before this change (still white)

### Requirement: Hairline alpha-border token

A `tokens.colors.border.hairline` MUST be added with a value of `rgba(60, 60, 67, 0.12)` (or equivalent low-alpha gray). Existing `tokens.colors.separator.separator` (`#C6C6C8`) remains for backwards compatibility but MUST NOT be used as the card outline.

#### Scenario: Card outline is a hairline, not a hard outline

- GIVEN the dashboard at 1440x900
- WHEN `border-top-color` of a rendered stat card is read
- THEN the alpha is `<= 0.2`, not full opacity

### Requirement: Tinted, layered elevation ramp using the label/separator hue family

`tokens.elevation` MUST be added as a new top-level object containing exactly four rungs, in this order, using the iOS label/separator hue family `rgba(60, 60, 67, α)`:

| Rung | Value |
|---|---|
| `elevation1` | `0 1px 3px rgba(60, 60, 67, 0.04)` (single contact shadow, no ambient) |
| `elevation2` | `0 2px 8px rgba(60, 60, 67, 0.06), 0 1px 2px rgba(60, 60, 67, 0.04)` (two layers) |
| `elevation3` | `0 8px 16px rgba(60, 60, 67, 0.08), 0 2px 6px rgba(60, 60, 67, 0.06)` (two layers, larger surfaces read thicker) |
| `elevation4` | `0 16px 24px rgba(60, 60, 67, 0.12), 0 4px 8px rgba(60, 60, 67, 0.08)` (two layers, sheet-level) |

Each rung from `elevation2` upward MUST be two layers per Apple's rule that bigger surfaces read thicker. No rung MAY use `rgba(0, 0, 0, ...)` as the shadow color.

#### Scenario: Card shadow uses the `60, 60, 67` family, not pure black

- GIVEN the rendered dashboard
- WHEN `box-shadow` of a stat card is read
- THEN every shadow color in the declaration uses `rgba(60, 60, 67, ...)`
- AND no shadow color in the declaration is `rgba(0, 0, 0, ...)`

#### Scenario: Surface-classes-to-rung mapping is documented in the token

- GIVEN the token file
- WHEN `tokens.elevation.elevation1..elevation4` are read
- THEN the comment on each names which surface classes consume it (default Card, list-row Card, popovers/dropdowns, Modal/Sheet)
- AND every rung from `elevation2` up declares exactly two shadow layers

### Requirement: Nested radius rhythm with `cardLg` and `control` keys

`tokens.radius.cardLg` MUST be added equal to `16px` (NOT `20px`). `tokens.radius.control` MUST be added equal to `8px`. Existing `tokens.radius.ios` (`10px`) and `tokens.radius.modal` (`14px`) MUST remain pinned. The keys `lg`, `2xl`, `3xl` MUST remain absent. The JS key is camelCase (`cardLg`); the emitted CSS variable is `--radius-card-lg`.

#### Scenario: Outer card radius is larger than inner control radius

- GIVEN the dashboard
- WHEN the rendered `border-radius` of a stat card and its inner interactive control are read
- THEN the outer value is `>= 16px` (the `cardLg` token)
- AND the inner value is `< outer value` (the `control` token, `8px`, or `ios` `10px`)

#### Scenario: No legacy radius keys reappear

- GIVEN the token file
- WHEN `tokens.radius` is read
- THEN the keys `lg`, `2xl`, and `3xl` are absent (pinned by the existing `tokens_module_radius_ios_and_modal` test)
- AND `tokens.radius.cardLg` equals `'16px'`
- AND `tokens.radius.control` equals `'8px'`

### Requirement: New token scales — `motion.duration`, `focusRing`, `fontFeatures`

Three new top-level keys MUST be added to `tokens.js`:

- `tokens.motion.duration`: exactly three keys, no more, no fewer — `fast: '120ms'`, `normal: '200ms'`, `slow: '320ms'`. Each value MUST end in `ms`.
- `tokens.focusRing`: an object with four parts — `width: '3px'`, `color: tokens.colors.systemBlue[500]` (`#007AFF`), `alpha: 0.20`, `offset: '2px'`. The generator MUST emit BOTH the composed `--focus-ring-default` shorthand AND its individual parts so consumers can compose their own.
- `tokens.fontFeatures.tabularNums`: the valid CSS value `'"tnum" 1, "lnum" 1'` (the literal Tailwind class name `tabular-nums` is NOT a valid `font-feature-settings` value).

The existing `letterSpacing` table MUST remain pinned (`xs..lg = "0"`, `xl..hero = -0.01em..-0.022em`).

#### Scenario: `motion.duration` has exactly three keys

- GIVEN the token file
- WHEN `tokens.motion.duration` is read
- THEN `array_keys` equals exactly `['fast', 'normal', 'slow']`
- AND `fast === '120ms'`, `normal === '200ms'`, `slow === '320ms'`
- AND no key named `base`, `instant`, or `spring` is present (those are dead tokens)

#### Scenario: `focusRing` composes correctly

- GIVEN any interactive primitive (Card, Button, Input, Badge)
- WHEN the rendered `:focus-visible` outline is read
- THEN the width is `3px`, the alpha is `0.20`, the color is `rgb(0, 122, 255)` (the systemBlue-500 value), and the offset is `2px`
- AND the rendered CSS resolves to the same value whether it consumes `--focus-ring-default` or the individual parts

#### Scenario: `fontFeatures.tabularNums` is a valid CSS feature-settings value

- GIVEN the token file
- WHEN `tokens.fontFeatures.tabularNums` is read
- THEN its value is the string `'"tnum" 1, "lnum" 1'`
- AND it can be passed to `font-feature-settings` without a CSS parse error
- AND no `tokens.fontFeatures.proportionalNums` key exists (dropped; no consumer in this slice)

### Micro-interaction layer

### Requirement: `ease-ios` adoption on new and updated transitions

`tokens.motion.easings.ios` (`cubic-bezier(0.25, 0.46, 0.45, 0.94)`) MUST be the timing function applied to every transition declared on a primitive (Card, Button, Input, Badge, Avatar) and on every transition declared on the three exemplar screens.

#### Scenario: All primitive transitions use `ease-ios`

- GIVEN the rendered dashboard
- WHEN every CSS `transition` declared by a primitive under `resources/js/components/ui/` is enumerated
- THEN every transition timing function is `cubic-bezier(0.25, 0.46, 0.45, 0.94)` or the `motion-ease-ios` Tailwind utility that resolves to it
- AND no transition uses `cubic-bezier(0.4, 0, 0.2, 1)` (the Tailwind default) on a primitive

### Requirement: Press feedback on Card, Button, Input, Badge, and every primitive rendered by the three exemplar screens

Card, Button, Input, Badge, Avatar, and any other primitive actually rendered by Login, Dashboard, or 404 MUST emit visible press feedback that fires on `pointerdown`, not on `click`. Acceptable devices: `transform: scale(0.97)` combined with the primitive's resting transform, OR a `box-shadow` step-down that swaps to the next-lower elevation rung.

#### Scenario: Press is visible on pointer-down

- GIVEN the dashboard at 1440x900
- WHEN a synthetic `pointerdown` is dispatched on the primary KPI card and the computed `transform` is read in the same frame
- THEN a scale below `1` is applied (target `0.97`-`0.98`)
- AND on `pointerup` the card returns to scale `1`

#### Scenario: Button press steps down elevation, not just swaps color

- GIVEN the login screen
- WHEN `pointerdown` fires on the primary submit button
- THEN either `transform: scale(<1)` OR a one-rung shadow reduction is computed
- AND the original color remains visible (the press is not a color-only swap)

### Requirement: Single tokenised focus ring

`Card.vue`, `Button.vue`, `Input.vue`, `Badge.vue`, `Avatar.vue`, and every primitive under `resources/js/components/ui/` MUST consume `tokens.focusRing` rather than redefining `:focus-visible` inline. Zero primitives may declare `backdrop-filter`.

#### Scenario: One focus ring token, no inline redefinitions

- GIVEN the rendered dashboard
- WHEN every `:focus-visible` outline in any primitive is read
- THEN each resolves to the same width/color/alpha/offset from `tokens.focusRing`
- AND no primitive carries a `backdrop-filter` declaration

### Requirement: Hover lift is compositor-friendly

Any surface that lifts on hover MUST do so via `transform` (translateY or scale) and/or `opacity`. Hover state MUST NOT animate `width`, `height`, `top`, `left`, `margin`, or `box-shadow` as the only change.

#### Scenario: Hover uses transform

- GIVEN any clickable surface (Card, Button)
- WHEN the rendered `:hover` style is read
- THEN at least one of `transform`, `opacity`, or a tokenised `box-shadow` step is present
- AND no hover transition animates layout properties only

### Requirement: Motion contracts preserved

`tokens.motion.response` MUST remain `0.35`. `tokens.motion.damping` MUST remain `1.0`. `tokens.motion.dampingBounce` (`0.8`) is preserved but only consumed by a momentum-derived interaction (e.g. sidebar collapse, a spring-driven dismiss). If no interaction qualifies, it remains a token with zero consumers and a comment explaining its purpose.

#### Scenario: Response and damping are unchanged

- GIVEN the token file
- WHEN `tokens.motion.response` and `tokens.motion.damping` are read
- THEN `response === 0.35` and `damping === 1.0`
- AND `dampingBounce === 0.8` is still defined

### Requirement: Reduced-motion and reduced-transparency degrade gracefully

`@media (prefers-reduced-motion: reduce)` MUST collapse every new spring/transform transition on the primitives and exemplar screens to a short opacity cross-fade of at most `200ms` (NOT zero milliseconds — reduced motion means a gentler non-vestibular equivalent, not the removal of feedback, per Apple guidance). `@media (prefers-reduced-transparency: reduce)` MUST raise the alpha of `.surface-glass` to fully opaque (or drop the `backdrop-filter` entirely) and raise any other translucent surface to its nearest opaque equivalent. `prefers-contrast: more` MUST NOT regress any existing behaviour.

#### Scenario: Reduced motion disables transform but keeps opacity feedback

- GIVEN a Chromium instance with `prefers-reduced-motion: reduce` set
- WHEN any new transition on a primitive or exemplar screen fires
- THEN `transform` is not animated
- AND opacity changes complete within `200ms` (the cross-fade IS preserved, not stripped)

#### Scenario: Reduced transparency solidifies glass

- GIVEN `prefers-reduced-transparency: reduce`
- WHEN `.surface-glass` is read
- THEN its computed `background-color` is fully opaque
- AND `backdrop-filter` is `none`

### Typographic craft

### Requirement: Numerals use `font-feature-settings: "tnum" 1, "lnum" 1`

Every KPI number on the dashboard MUST apply `font-feature-settings: "tnum" 1, "lnum" 1` via the `tabular-nums` Tailwind utility or the corresponding token utility, in addition to the existing `tabular-nums` class. The change MUST be expressed as a token utility, not as a literal declaration in any `.vue` file.

#### Scenario: KPI figures are tabular and lining

- GIVEN the dashboard
- WHEN a rendered KPI number is read
- THEN `font-feature-settings` contains `"tnum"` and `"lnum"`
- AND no `.vue` file under `resources/js/modules/` declares `font-feature-settings` inline

### Requirement: Single `<h1>` per page, no competing headings

Each of the three exemplar pages MUST contain exactly one `<h1>`. The dashboard greeting line ("Buenos días, Admin") MUST NOT be an `<h1>` and MUST NOT compete with the topbar `<h1>` for size/weight; it remains a `text-2xl font-semibold` `<p>` at most.

#### Scenario: Dashboard has exactly one `<h1>`

- GIVEN the dashboard route
- WHEN `document.querySelectorAll('h1').length` is read
- THEN the count is exactly `1`
- AND the greeting line is a `<p>` or `<div>`, not an `<h1>`

### KPI anatomy

### Requirement: Fixed-slot KPI card anatomy

Each KPI card MUST allocate four reserved slots in order: eyebrow, number, delta slot, caption slot. Empty slots MUST reserve their layout footprint rather than collapsing. The chip slot MUST be optional: when the API emits `delta_label: null`, the slot renders no chip but the card height matches the cards that do render one.

#### Scenario: All five cards share one row baseline

- GIVEN the dashboard with any combination of chip/no-chip per card
- WHEN the rendered `getBoundingClientRect().bottom` of each of the five cards is read
- THEN all five values are equal
- AND no card overflows its grid row

#### Scenario: Citas Hoy caption wrapping does not break baseline

- GIVEN today's date caption spans two lines
- WHEN the row baseline is read
- THEN it matches the baseline of the four sibling cards
- AND the row height is determined by the fixed-slot layout, not by caption content

### Chrome craft

### Requirement: Topbar controls share one optical size

The WS status dot, the bell icon, and the avatar in the topbar MUST render at the same optical size (the bell glyph stroke weight, the dot diameter, and the avatar diameter MUST all read at one of two documented size classes: `topbar-control` for the dot and bell, `topbar-control-lg` for the avatar — both tokenised). They MUST share a vertical alignment baseline.

#### Scenario: Three topbar controls align on one baseline

- GIVEN the rendered dashboard topbar
- WHEN `getBoundingClientRect()` is read for the dot, bell, and avatar centers
- THEN the centers share a single `y` value within `1px`
- AND the values come from `tokens.topbar.control*`, not inline

### Requirement: Sidebar grouping and search are additive only

The existing nav items (18 items in total per the source) MUST keep their labels, order, and route slugs. This change MAY introduce grouping (sections, dividers) and a search input ABOVE the first item; it MUST NOT rename, reorder, or remove any nav item.

#### Scenario: Nav labels and order unchanged

- GIVEN the rendered sidebar
- WHEN each visible nav label is read in DOM order
- THEN the labels and their sequence match the pre-change source verbatim
- AND the visible count equals 18
- AND every route slug resolves to the same destination

### Requirement: Quick-action affordance uses a non-chevron device

The quick-action tiles MUST look clickable (visual affordance such as an accent-tinted hover state, a small arrow-icon stub, or a keyhint chip), but MUST NOT introduce the SVG path `M9 5l7 7-7 7`. The path ban is enforced by an existing source-assertion test.

#### Scenario: Gray-filled tiles no longer read disabled

- GIVEN the dashboard with five quick-action tiles
- WHEN the rendered background of each tile is read in its resting state
- THEN the background is a clickable affordance (e.g. `tokens.colors.surface.card` plus a hover lift) and not the disabled `systemGray-100` fill
- AND `rg 'M9 5l7 7-7 7' resources/js/modules/dashboard/` returns zero matches

### Requirement: Dashboard empty state resolves to `<EmptyState>`

When the "Citas Hoy" list is empty, the dashboard MUST render the `<EmptyState>` component (an existing primitive pinned by tests). The empty state MUST show the missing-data message and a single primary CTA. It MUST NOT be a large dead box with a lone outline icon.

#### Scenario: Empty appointment list renders EmptyState

- GIVEN an authenticated dashboard with zero appointments for today
- WHEN the "Citas Hoy" section is rendered
- THEN an `<EmptyState>` element is present in the DOM
- AND it contains one CTA button or link
- AND no dead-box-without-CTA pattern is rendered

### Login craft

### Requirement: Login inputs use exactly one of {fill, border}

The login inputs MUST render with one and only one of the two treatments: a `#F2F2F7` group-fill (no border) OR a 1px hairline border (no fill). Both treatments MUST NOT be present at the same time on the same input.

#### Scenario: Inputs do not carry both fill and border

- GIVEN the login page at 1440x900
- WHEN the computed `background-color` and `border-top-width` of each input are read
- THEN either `background-color` is `rgb(242, 242, 247)` and `border-top-width` is `0px`, OR `background-color` is `rgb(255, 255, 255)` and `border-top-width` is `1px`
- AND the two treatments do not coexist on a single input

### Requirement: Login inputs carry placeholder text

Each input MUST render a placeholder string that conveys the expected format (e.g. `tu@clinica.com`, `••••••••`). Empty placeholders are not permitted.

#### Scenario: Placeholder present

- GIVEN the login page
- WHEN each `<input>` is read
- THEN the `placeholder` attribute is a non-empty string

### Requirement: Password reveal button aligns inside the input's visual frame

The password reveal/eye button is already inside the `.field-input-wrap` wrapper in the DOM. The defect is purely visual (today the button visually sits outside the input box, so the two fields render at different widths). The change MUST restyle the reveal button so it visually sits inside the input frame, with the resulting email and password input boxes rendering at the same width.

#### Scenario: Both fields share one width after the styling fix

- GIVEN the login page
- WHEN the rendered `getBoundingClientRect().width` of the email and password inputs is read
- THEN both values are equal within `1px`
- AND the DOM placement of the reveal button is unchanged (it remains inside `.field-input-wrap`)

### Requirement: Login helper text removed or replaced with real guidance

The current helper text merely restates the label (e.g. "Ingrese su email" under an email input). The change MUST either remove the helper text entirely OR replace it with a non-redundant guidance string (e.g. "Use el email con el que se registró").

#### Scenario: No redundant helper text

- GIVEN the login page
- WHEN each helper `<p>` under an input is read
- THEN its text content is either empty OR does not lexically repeat the label text

### Requirement: Primary button has accent-tinted shadow and inner top highlight

The login primary button MUST render with (a) an accent-tinted shadow from the new elevation ramp (using the `rgba(60, 60, 67, α)` family or a tokenised systemBlue tint for emphasis) and (b) an inner top highlight (a `::before` pseudo-element or a stacked gradient with a transparent midpoint) that suggests light catching a physical surface.

#### Scenario: Button is not flat

- GIVEN the login page
- WHEN the rendered `box-shadow` of the primary button is read
- THEN the shadow color is NOT pure `rgba(0, 0, 0, ...)`
- AND an inner top highlight is detectable in the rendered styles

### Requirement: Hero photo renders with rounded corners and a legibility scrim

The `/images/ui/login-hero.jpg` image MUST render with `border-radius: tokens.radius.cardLg` (`16px`), and the eyebrow text overlaid on the photo MUST be either vertically offset to sit on a scrim band OR colored at WCAG AA contrast against the photo's median luminance.

#### Scenario: Hero photo is rounded and eyebrow is legible

- GIVEN the login page
- WHEN the rendered `border-radius` of the hero `<img>` is read
- THEN it equals `16px` (the `tokens.radius.cardLg` value)
- AND the eyebrow text passes a contrast ratio of `>= 4.5:1` against its immediate background

### Requirement: 404 hero matches the login hero treatment

`NotFoundPage.vue` MUST consume the same `tokens.radius.cardLg`, scrim, and contrast rule for its hero asset. The single-`<h1>`-per-page rule, the `aria-live` error region, and the no-hand-written-hex rule MUST be preserved.

#### Scenario: 404 hero and eyebrow are legible

- GIVEN the 404 page
- WHEN the rendered hero `<img>` `border-radius` and the eyebrow computed color are read
- THEN both rules match the login hero

## Out of Scope (recorded decisions)

| Item | Decision | Reason |
|---|---|---|
| Sparkline per KPI card | Deferred | No per-day time series is exposed by the backend yet |
| Two-tone numeral treatment on KPI figures | Deferred (REVERSIBLE, pending user override) | The number IS the clinical datum in these cards; fading trailing digits of `105` degrades legibility for zero comprehension gain; the treatment works in the reference dashboards only because their numerals are decorative marketing copy |
| Quick-action chevron affordance | Banned device | Existing source-assertion test forbids the SVG path `M9 5l7 7-7 7` |
| Sidebar IA changes (labels, order, grouping content) | Forbidden | Information architecture must not move |
| Dark mode | Forbidden | The design system is light-only; tokens pin no dark variants |
| External animation dependency | Forbidden | Vue 3 + Tailwind 3 only; no Motion/Framer install |
| Auth/login redesign beyond micro-detail | Out of scope | IA, fields, autocomplete attributes, error region are preserved |
| `motion.duration.base`, `motion.duration.instant`, `motion.duration.spring` keys | Dropped | A 0ms key and a label-only key are dead tokens; only `fast`/`normal`/`slow` ship |
| `tokens.fontFeatures.proportionalNums` | Dropped | No consumer in this slice |
| Percentage on the "Pacientes" chip | Rejected as percentage of the cumulative headline | See `dashboard-period-comparisons` spec, Out of Scope table |

## Items marked as not directly verifiable by PHPUnit or Playwright

Per the ruling, any requirement that has no PHPUnit source-assertion, PHPUnit Feature test, or Playwright visual mechanism is marked here so it is not left as an unfalsifiable MUST:

- "Visual affordance such as an accent-tinted hover state, a small arrow-icon stub, or a keyhint chip" for quick-action tiles — the precise visual device is a design choice among three options; the spec records the constraint ("not the banned chevron path") and verifies the no-banned-path rule via a source-assertion test, but the chosen device is a design-time decision.
- "An inner top highlight ... that suggests light catching a physical surface" — the human-perceptual claim is not machine-verifiable; the spec is satisfied by any implementation that adds a detectable top-edge highlight via a `::before` or stacked background, which a Playwright computed-style check can detect.
- "Eyes that read premium" / "feels like an extension of you" — out of scope as a spec target; the spec targets only checkable behavior.

## Non-negotiable invariants every requirement must respect

- IA does not move: navigation labels and order, KPI card order and labels, quick-action labels, route slugs, and form field names and order all stay.
- Every new visual value enters through `tokens.js` and is consumed as a token/utility. No hex literals or raw shadow strings in any `.vue` component.
- Generated CSS: exactly one top-level `:root`; `.surface-glass` emitted exactly once; hex literals equal the union of hex literals in `tokens.js`; no dangling `var()`.
- Zero `backdrop-filter` in `Card.vue` or any primitive under `components/ui/`. Chrome blur lives only in `.surface-glass`.
- `DashboardPage.vue`: zero `linear-gradient`/`bg-gradient`, zero `<style scoped>`, zero hand-written hex, quick-actions capped at 3 columns, subtitles not truncated, 300ms debounce, `<EmptyState>` for empty appointments, appointments capped at 3, `<UiSkeleton>` for loading, `tabular-nums` on stat numbers, all five stat labels and five quick-action labels present.
- `AppLayout.vue`: keeps `surface-glass` and `min-h-[100dvh]`, zero hand-written hex.
- `LoginPage.vue` / `NotFoundPage.vue`: exactly one `<h1>` each, no hand-written hex, no animated background blobs, no `images/pexels`, existing autocomplete attributes, the `aria-live` error region.
- No external animation dependency. Vue 3 + Tailwind 3. Light mode only.

## Verification surface

Every requirement above MUST be checkable by one of: a PHPUnit source-assertion test, a PHPUnit Feature test, or a documented Playwright visual pass on one of the three exemplar screens. Items flagged in "Items marked as not directly verifiable" are exempt from the falsifiability rule and the rule applies to the rest.
