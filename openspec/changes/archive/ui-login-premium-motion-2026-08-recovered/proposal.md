# Proposal: Login Premium Motion (`ui-login-premium-motion-2026-08`)

## Intent

The login is readable and on-brand (springs, reduced-motion, canvas/surface contrast, hero bento, primary-button elevation + inner highlight) thanks to `ui-premium-microdetail-2026-08` and `ui-rollout-all-modules-2026-08`. It is NOT yet premium-feeling because:

1. The submit button does not react to the cursor — it sits flat until pressed.
2. Validation is submit-only — the user finds out their password is too short after waiting for the API round-trip.
3. The form Card surface is opaque; the hero bento is shallow-behind and the eye reads two flat planes instead of one depth layer.
4. The brand glyph is a static tooth; there is no signature moment on success.
5. The loading state is a single spinner inside the button — no personality.

This change ships SIX microinteractions on the login (slice 1) and documents FIVE more in the plan for future slices. The signature: **the brand tooth morphs into a checkmark on success, the form surface blurs the bento behind it, the submit button pulls toward the cursor, and validation greets the user before they finish typing.**

## Scope

### IN SCOPE — slice 1 (this change)

| # | Feature | Mechanism |
|---|---|---|
| 1 | **Magnetic cursor on submit button** | New `useMagneticHover(el, opts)` composable wrapping `useSpring2D`. 40% offset, damping 0.7 (one gentle bounce on release). Bypassed under `prefers-reduced-motion: reduce` and on `(pointer: coarse)`. Mouse-leave recenters via the spring's own `set(0)`. |
| 2 | **Live form validation** | New `useFieldValidation(form, rules)` composable. **On blur + 250ms idle** (per user decision Q2). Success state shows a small checkmark that scales in with `useSpring(response 0.2)`. Error message slides down + fades. No layout shift. |
| 3 | **Glassmorphism on the form Card** | Attach `.decorative-glass` (the lighter alpha, generated CSS) to the existing `Card variant="elevated"`. Already wired with `prefers-reduced-transparency: reduce` collapse. Bento provides the content that justifies the blur. |
| 4 | **Brand glyph morph (tooth → check)** | Replace the static `<svg class="brand-glyph">` with a morphing pair. **Pre-validate command/point parity** (M+C+L+Z count must match) before writing the template. Animate via SMIL `<animate attributeName="d">` for max browser coverage. Triggered ONLY on `success` state. Under reduced-motion: opacity cross-fade between two static SVGs (no SMIL). |
| 5 | **Multi-stage loading** | On submit, replace form content with a 3-stage progress indicator (`Validando → Autenticando → Listo`) + skeleton placeholders for the headline. Crossfade stages with `useSpring(response 0.25, damping 1.0)`. On failure: revert to the form with a 220ms shake (transform only, reduced-motion collapses to opacity flash). |
| 6 | **prefers-reduced-motion** | Every new motion contract collapses to instant or short opacity/colour change. Existing useSpring contract already handles the magnetic hover; the live validation, morph, and loading stage each declare an explicit reduced-motion block. |

### OUT OF SCOPE — documented in plan only

| Feature | Reason deferred | Future slice |
|---|---|---|
| Drag & drop with physics | Login has nothing draggable. | Future "scheduler drag-and-drop" |
| Longpress with preview | Login has no long-press targets. | Future calendar / patients list |
| Toggle / checkbox / radio transition | Login has one checkbox; not worth a slice. | Future "form primitives polish" |
| Scrollytelling | Login is viewport-sized, no scroll. | Future marketing / tour overlay |
| Sound / haptics | No sound asset pipeline in project. | Future if any screen demands it |

## Capabilities

### New Capabilities
- `login-premium-motion`: the SIX microinteractions scoped above, additive on top of the existing login (`auth/login-page` capability).

### Modified Capabilities
- None. The `auth/login-page` capability surface (POST `/api/auth/login`, error envelope, rate limiting) is unchanged.

## Approach

Stacked-to-main, auto-chain, two PRs. Forecast per slice:

| PR | Scope | Forecast lines |
|---|---|---|
| PR1 | Foundation: `@vueuse/motion` install + plugin registration + `useMagneticHover` composable + `useFieldValidation` composable + `Button.vue` `data-magnetic` hook + PHPUnit tests for both new composables. NO LoginPage edits. | ~250 |
| PR2 | LoginPage.vue: wire all six microinteractions. `.decorative-glass` attached. Brand-glyph morph paths pre-validated + animated. Multi-stage loading. Source-inspection tests in `LoginPageRenderTest`. Manual Playwright sweep. | ~320 |

Each PR is < 400 lines. PR1 lays the foundation so PR2's diff is purely composition.

**Token changes:** NONE. The existing motion + elevation + duration tokens cover everything. Magnetic constants are domain-specific to the magnetic effect and live inside the composable, not in tokens.

**Backend changes:** NONE. No new endpoints, no API changes.

## Risks and Rollback

| Risk | Severity | Mitigation |
|---|---|---|
| Magnetic cursor is mouse-only. Keyboard-only users must have an equal-feel path. | Medium | Magnetic is purely additive visual. Keyboard `Tab → Enter` already triggers submit. `prefers-reduced-motion: reduce` and `(pointer: coarse)` bypass the magnet entirely; the existing `translateY(-1px)` hover lift keeps working. |
| Glassmorphism can render as a smear if the bento behind it is too uniform. | Medium | Use `.decorative-glass` (lighter alpha), not `.surface-glass` (heavier, designed for chrome). Verified at iPhone 12 viewport (smallest realistic target). Reduced-transparency collapses to opaque. |
| SVG path morph fails if the two paths have different command counts. | HIGH | Pre-validate path parity BEFORE writing the Vue template. Test: `path1.match(/[MCLZ]/gi).length === path2.match(/[MCLZ]/gi).length`. Fallback: opacity cross-fade (degraded but not broken). |
| Live validation causes jank on rapid typing. | Low | Debounce 250ms via `setTimeout`. Cancel previous timer on each input change. Reduced-motion keeps the debounce (validation logic stays consistent). |
| `@vueuse/motion` plugin must be registered in `app.js`. Missing the `app.use(MotionPlugin)` call silently breaks every `v-motion` directive. | Medium | Add a CI PHPUnit assertion scanning `resources/js/app.js` for `MotionPlugin` registration (or import equivalent). |
| Multi-stage loading may feel slow on a fast login. | Low | Each stage ~150ms; whole sequence sub-second. On a 500ms network call, stages line up with the network, not against it. |
| Reduced-motion bypass must cover the SVG morph. SMIL `<animate>` runs under reduced-motion in most browsers. | High | The morph runs ONLY on success. Under reduced-motion, the success state uses an opacity cross-fade between two static SVGs (no SMIL). |

**Rollback:**
- PR1: revert `package.json`, `pnpm-lock.yaml`, `resources/js/app.js`, `useMagneticHover.js`, `useFieldValidation.js`, `Button.vue` additive change, and the two PHPUnit files. LoginPage is untouched in PR1, so no user-visible behaviour changes.
- PR2: revert `LoginPage.vue` only. The two new composables remain dormant until a future screen adopts them.

## Success Criteria

- [ ] `pnpm install` succeeds; `@vueuse/motion` v3.0.3 locked.
- [ ] `php artisan test --filter=MagneticHoverTest|FieldValidationTest|LoginPageRenderTest|GeneratedTokensCssTest|UseSpringMathTest` all green.
- [ ] `php artisan test --testsuite=Unit` all green (regression guard).
- [ ] `pnpm build` succeeds.
- [ ] `pnpm lint:check` succeeds.
- [ ] Manual Playwright sweep at 1440x900 + iPhone 12 viewport confirms:
  - Mouse-enter on submit → button tilts toward cursor up to 40% of size, bounces gently on release.
  - Mouse-leave → button springs back to center.
  - Type 1 char in username, Tab away → error "El usuario es requerido" slides down.
  - Type 8+ chars in password, blur → green checkmark scales in.
  - Toggle `prefers-reduced-motion: reduce` in DevTools → all of the above collapse to opacity/colour changes only; morph is an opacity cross-fade.
  - Toggle `prefers-reduced-transparency: reduce` → form Card surface flattens to opaque.
  - Successful login (mocked) → brand tooth morphs into a check (~600ms), then router pushes to /dashboard.
  - Failed login → form reappears with a 220ms shake; toast/error visible.
- [ ] Source-inspection assertions in `LoginPageRenderTest`:
  - imports `useMagneticHover`
  - imports `useFieldValidation`
  - brand-glyph SVG declares two paths with matched command count
  - form Card uses `.decorative-glass` OR `backdrop-filter` + reduced-transparency collapse
  - loading branch renders 3 stage labels
  - no hand-written hex literals (existing guard, must not regress)
  - prefers-reduced-motion collapse covers all 4 new motion paths (magnetic, validation, morph, loading)
- [ ] Bundle impact: `@vueuse/motion` adds ≤12kb gzipped to the production build.
