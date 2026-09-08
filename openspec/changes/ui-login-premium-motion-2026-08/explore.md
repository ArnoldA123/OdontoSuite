# Explore: Login Premium Motion

Change: `ui-login-premium-motion-2026-08`
Phase: explore
Author: orchestrator (parent inline)
Date: 2026-08-23
Artifact store: hybrid (this file + Engram `sdd/ui-login-premium-motion-2026-08/explore`)

---

## 1. Current state inventory

### 1.1 Login module (`resources/js/modules/auth/`)

| File | Lines | Purpose |
|---|---|---|
| `LoginPage.vue` | ~470 | The login screen. Form + hero column (3-tile bento + wordmark). |
| `ForgotPasswordModal.vue` | n/a | Modal triggered from "¿Olvidaste tu contraseña?". |
| `ResetPasswordModal.vue` | n/a | Modal that opens separately (NOT auto-opened). |

**Existing motion in LoginPage:**

- Card entrance spring: `useSpring({ response: 0.35, damping: 1.0, cssVar: '--spring-card-o' })` driving `translate3d(0, calc((1 - var(--spring-card-o)) * 12px), 0)` + paired opacity spring.
- Hero column entrance: `useSpring({ response: 0.45, damping: 1.0 })` (slower mirror of card, 0.10s delta per apple-design §7).
- Submit button (UiButton) carries the Apple premium construction: `var(--elevation-3)` + `inset 0 1px 0 rgba(255,255,255,0.30)` + `0 0 0 1px rgba(0,0,0,0.04)`. On `:active` it presses down `translateY(1px)`.
- Password toggle button has `transition: color 200ms ease-out, background-color 200ms ease-out`. No spring.
- Forgot link has colour-only hover transition.
- `prefers-reduced-motion`, `prefers-reduced-transparency`, `prefers-contrast` media queries are honoured.

**Existing validation:**
- Validate-on-submit only (`validateForm()` runs in `handleLogin()`).
- Error rendering is inline `<p class="field-error">` below each field.
- Auth failure is an inline `<div class="auth-error" role="alert" aria-live="polite">` (NOT a toast — a11y-correct).
- No live validation. No character counter.

### 1.2 Auth composable (`resources/js/composables/useAuth.js`)

- `login(credentials)` returns the API response on success, throws on failure.
- `isLoading` ref is exposed (currently unused by LoginPage — the component manages its own `loading` ref).
- Tokens stored in `localStorage` (`auth_token`, `user`). 401 clears both.
- `login` flow: `POST /api/auth/login` → set token → set user → return response. Latency: typical 200-800ms on dev (we have no SLA target).

### 1.3 Motion primitives (`resources/js/composables/useSpring.js`, `useSpring2D.js`, `useSpringMath.js`)

- **useSpring.js**: imperative rAF + CSS-var writer. Apple "response" + "damping" API. Reduced-motion honoured. CSS-var binding only — does NOT mutate a `<div>`'s transform directly. The LoginPage wraps the spring value into `transform: translate3d(...)` inside its scoped style.
- **useSpring2D.js**: thin wrapper that instantiates two independent `useSpring` calls (one per axis) and re-exports `projectAndSnap`. Designed for 2D gestures (drag, sheet swipe). The same primitive is the right primitive for **magnetic cursor on the submit button** (X and Y track mouse independently — Decision 2 forbids a single spring on a 2D distance).
- **useSpringMath.js**: pure math kernels (stepSpring, settle, projectAndSnap, instantSettle, prefersReducedMotion). Already covered by `tests/Unit/DesignSystem/UseSpringMathTest.php`.
- **Coverage gap**: `useSpring2D.js` is declared and exported but has ZERO real consumers (only `useSpring2D` string match in `AppLayout.vue` comment + own file + test). It is **unused inventory**. Wiring it into the login's magnetic hover is the first honest consumer.

### 1.4 UI primitives (relevant subset)

- **Button.vue**: `data-variant="primary"` carries `var(--elevation-3)` + inset highlight + `:active translateY(1px)`. Hover lifts `translateY(-1px)`. Focus ring via `var(--focus-ring-default)`. Reduced-motion collapse to opacity.
  - Gap: no magnetic cursor. The spring physics exists (`useSpring2D`), the consumer does not.
- **Card.vue**: variants `default | glass | flat | elevated | outlined`. **Important**: the `glass` variant is OPAQUE (`bg-systemBackground`, no blur) — historically named but functionally a solid data card. Translucent chrome surfaces use `.surface-glass` from `resources/css/tokens.generated.css` (sidebar, topbar, mobile sheet). The `decorative-glass` is also defined in generated CSS as an actual translucent layer (used in AppLayout).
  - The login card uses `variant="elevated"` (token-bound `var(--elevation-2)` + hairline). To add REAL glassmorphism here we would need to either (a) extend Card with a new `glass-translucent` variant, or (b) apply `.decorative-glass` from generated CSS at the LoginPage root.
- **Input.vue**: floating label animation, focus ring via `var(--focus-ring-default)`. No live-validation hooks. The LoginPage bypasses UiInput entirely (raw `<input>`) so the autocomplete/inputmode attrs reach the form control directly. **That pattern must be preserved** — see §5 below.

### 1.5 Design tokens (`resources/js/design-system/tokens.js`)

- `motion.response = 0.35`, `motion.damping = 1.0`, `motion.dampingBounce = 0.8` (declared but unconsumed).
- `motion.duration = { fast: '120ms', normal: '200ms', slow: '320ms' }`.
- `motion.easings.ios = cubic-bezier(0.25, 0.46, 0.45, 0.94)`.
- `elevation.0..4` rungs (5 levels, iOS label-hue rgba).
- `focusRing` parts + composed value.
- `radius.ios = 10px`, `radius.modal = 14px`, `radius.cardLg = 16px`, `radius.control = 8px`.
- `fontFeatures.tabularNums`.
- `topbar.iconSize = 20px`, `topbar.iconWeight = 1.5` (single optical weight rule).

**Gap**: there is no current `--motion-magnet-max-distance` or any magnetic-cursor token. The proposal can introduce `motion.magnet.response`, `motion.magnet.damping`, `motion.magnet.maxDistance` as new token keys.

### 1.6 Generated CSS glassmorphism helpers (`resources/css/tokens.generated.css`)

- `.surface-glass`: `backdrop-filter: blur(20px) saturate(180%) contrast(1.04)`. Used by AppLayout sidebar + topbar + mobile sheet. Has `prefers-reduced-transparency: reduce` collapse built in.
- `.decorative-glass`: similar, lighter alpha tint, designed for decorative surfaces (e.g. empty states).

These are the *correct* primitives to apply on the login card if we want glassmorphism. NO new CSS needed — just attach the class.

### 1.7 Test coverage (`tests/Unit/DesignSystem/LoginPageRenderTest.php`)

The file carries 18 source-inspection tests pinning existing contracts:

1. Exactly one `<h1>` in LoginPage + NotFoundPage.
2. `autocomplete="username"` and `autocomplete="current-password"` on the right fields.
3. `aria-live` region for errors.
4. NO `images/pexels` references.
5. NO hand-written hex literals in auth/ or errors/ modules.
6. NO `@keyframes float` (legacy blob animation deleted).
7. PR5 invariants: placeholders, no redundant helper-text, password toggle right:12px, primary button elevation-3 + inner highlight, hero scrim rgba(60,60,67,0.05→0.55), 404 hero card radius + hairline.

**Gap**: NO tests for the new motion behaviors. We will need at minimum:
- Login page declares `useMagnetic` (or equivalent) on the submit button or imports it.
- Login page uses a `.surface-glass` or `.decorative-glass` helper OR declares glassmorphism rules that include `backdrop-filter` AND a `prefers-reduced-transparency` collapse.
- Brand glyph SVG declares path data suitable for morphing (must have matched command count for `path1 → path2`).
- The login source imports a `liveValidate` composable OR inlines the live-validation logic.
- NO hand-written hex literals added.

---

## 2. Reusable primitives — what's there, what's missing

| Need | Existing primitive | Gap |
|---|---|---|
| Magnetic hover (2D) | `useSpring2D` (declared, 0 consumers) | First real consumer. Need a `useMagneticHover` composable wrapping `useSpring2D` with rAF mouse tracking, max-distance clamp, and a `disabled` flag for keyboard-only users. |
| Live validation | None | Need a `useFieldValidation(form, rules)` composable, OR inline `watch()` with debounced validate calls. Both are acceptable; composable is cleaner and reusable when we roll out to other forms later. |
| Glassmorphism surface | `.surface-glass`, `.decorative-glass` from generated CSS | Just attach the class. NO new CSS. Already has the reduced-transparency collapse. |
| SVG morph (tooth → check) | None | Native browser support is the path attribute (animated via SMIL or Web Animations API on `d`). Both paths must have **identical** command count and point count. Pre-build both paths and confirm parity. |
| Loading skeleton | `.loading` data attribute on Card.vue triggers `[data-loading]::after` shimmer animation | Already exists. Card.vue already has the shimmer. Need to: (a) wrap the form in a `<div data-loading>` toggle on submit, (b) ensure reduced-motion removes the shimmer (it does — the `@keyframes shimmer` rule is unconsumed under reduced-motion in Card.vue since the loading overlay is purely decorative). |
| Multi-stage progress | None | Add an inline progress indicator (`parsing → autenticando → listo`) with smooth step transitions. Each step is ~150ms with ease-ios. |

---

## 3. Motion architecture options

### Option A — Vanilla (no new deps)
Use `useSpring` / `useSpring2D` + CSS animations + Web Animations API. The existing tokens cover duration + easing. The brand-glyph morph uses inline `<animate attributeName="d" values="...">` SMIL. Bundle impact: 0. Cons: SMIL is being deprecated; writing the path-morph math by hand is fragile.

### Option B — `@vueuse/motion` (~10kb gzipped) — CHOSEN
Adds `v-motion` directive (Framer-Motion-style variants), with built-in variants like `fade`, `slide`, `scale`, and easing presets. Compatible with our existing `useSpring` because `@vueuse/motion` writes to `transform` / `opacity` independently. We can:
- Use `v-motion` for the brand-glyph morph (declarative path via `variants`).
- Use `v-motion` for the live-validation toast slide-in.
- Use `v-motion` for the success cinematic.
- KEEP `useSpring2D` for the magnetic cursor (it's an imperative rAF loop, not a declarative variant — different concern).

### Option C — GSAP + MorphSVG (~70kb)
Far more powerful, but the bundle cost and the AGENTS.md rule "no new animation dependencies without justification" makes it the wrong call for a login-only slice. **REJECTED**.

**Decision: Option B.** `@vueuse/motion` is added as the only new dependency. It sits beside `useSpring`/`useSpring2D` — the existing primitives continue to handle imperative physics (springs, deceleration, snap), and `v-motion` handles declarative variants (enter/exit, hover, success).

---

## 4. Scope confirmation

### IN SCOPE — slice 1 (this change)

| # | Feature | Where it lands |
|---|---|---|
| 1 | **Magnetic cursor on submit button** | New composable `useMagneticHover(el, opts)` wrapping `useSpring2D` with rAF mouse tracking. Applied to the `<UiButton type="submit">` via ref binding. Disabled under `prefers-reduced-motion`. Falls back to the existing `translateY(-1px)` hover lift when reduced-motion is on. |
| 2 | **Live validation** | New composable `useFieldValidation(form, rules)` with debounced validate calls (200ms). Inline error message rendered with a slide-down + opacity transition. Success state shows a tiny checkmark that animates in (scale 0 → 1 with ease-ios, response 0.2). No layout shift. |
| 3 | **Glassmorphism on the form Card** | Attach `decorative-glass` (the lighter tint) to the existing `Card variant="elevated"`. The bento tiles in the hero column provide the content that justifies the blur. **Honor `prefers-reduced-transparency: reduce`** — already wired in `decorative-glass` (collapses to opaque). |
| 4 | **SVG morph: brand glyph tooth → checkmark** | Replace the static `<svg class="brand-glyph">` with a morphing pair. Pre-bake both paths with identical command count. Animate via SMIL or Web Animations API. Trigger on `success` state (post-`login` resolution). |
| 5 | **Loading skeleton + multi-stage progress** | On submit: replace the form content with a 3-stage progress indicator (`Validando → Autenticando → Listo`) + skeleton placeholders for the headline. Use `useSpring` for the per-stage crossfade (response 0.25, damping 1.0). Reverses to the form on failure with a shake animation. |
| 6 | **prefers-reduced-motion** | Every new motion contract collapses to instant or short opacity/colour change under reduced-motion. Verified in the same media query block as the existing spring collapse. |

### OUT OF SCOPE — documented in plan only (future slices)

| Feature | Why deferred | Where it would land |
|---|---|---|
| Drag & drop with physics (snap, rebote, momentum) | The login has nothing draggable. Would need a new screen (e.g. "drag your appointment onto the chair"). | A future "scheduler drag-and-drop" slice. |
| Longpress with preview | No long-press interactions in the login. | Future slices on calendar / patients list. |
| Toggle / checkbox / radio transition | The login has ONE checkbox (recordarme). A custom animated checkbox is a 30-line micro-feature that does not justify a slice. | A future "form primitives polish" slice. |
| Storytelling / scrollytelling | The login is a viewport-sized screen with no scroll surface. | Future slices on a marketing landing or a "tour" overlay. |
| Sound / haptics | The user mentioned sound is opt-in and the project does not carry a sound asset pipeline. | Future slice if a screen demands it (probably never). |

---

## 5. Affected files (preliminary)

### Novel
- `resources/js/composables/useMagneticHover.js` — NEW. Wraps `useSpring2D` with mouse tracking + max-distance clamp + reduced-motion bypass.
- `resources/js/composables/useFieldValidation.js` — NEW. Debounced live validation with success/error states.

### Modified — slice 1
- `resources/js/modules/auth/LoginPage.vue` — wire magnetic hover on submit, live validation, glassmorphism on the form Card, brand-glyph morph, multi-stage loading.
- `resources/js/components/ui/Button.vue` — additive `data-magnetic` attribute hook + scoped CSS that applies a CSS-var-driven translate when the parent binds the spring X/Y to the button. NO break of existing behaviour.

### Test files (additive)
- `tests/Unit/DesignSystem/LoginPageRenderTest.php` — extend with new source-inspection assertions (see §1.7).
- `tests/Unit/DesignSystem/UseSpringMathTest.php` — NO change. Magnetic hover uses the existing primitives; the math is unchanged.
- `tests/Unit/Composables/MagneticHoverTest.php` — NEW. Pure-math test for the magnetic clamp + reduced-motion bypass.
- `tests/Unit/Composables/FieldValidationTest.php` — NEW. Debounce timing + success/error state machine.

### No changes
- `resources/js/design-system/tokens.js` — UNCHANGED. The existing motion + elevation + duration tokens cover everything. (If we want, we can ADD `motion.magnet.response/damping/maxDistance` as new keys in a follow-up slice; this slice uses literal values for the magnetic constants because they are domain-specific to the magnetic effect, not generally reusable.)
- `resources/css/tokens.generated.css` — UNCHANGED. `decorative-glass` already exists.
- `tailwind.config.js` — UNCHANGED.
- Backend — UNCHANGED. No new API endpoints.

---

## 6. Test gaps

### Backend
None — slice 1 is frontend-only. No new endpoints.

### Frontend (PHPUnit source-inspection)

1. **`LoginPageRenderTest::login_page_uses_magnetic_hover_composable`** — asserts `LoginPage.vue` imports `useMagneticHover` (or equivalent) and binds it to a submit ref.
2. **`LoginPageRenderTest::login_page_uses_field_validation_composable`** — asserts the live-validation composable is imported and called against at least one field.
3. **`LoginPageRenderTest::login_page_brand_glyph_has_morph_paths`** — asserts the brand glyph SVG carries TWO `d=` attribute values (or a paths array) AND they have matching command-letter sequences (M+C+L+Z count parity).
4. **`LoginPageRenderTest::login_page_form_card_uses_glass_class`** — asserts the form card has `decorative-glass` (or `surface-glass`) class OR declares `backdrop-filter` with a `prefers-reduced-transparency` recovery rule.
5. **`LoginPageRenderTest::login_page_loading_has_multi_stage_indicator`** — asserts the loading branch renders 3 stage labels (`Validando`, `Autenticando`, `Listo`) or equivalent.
6. **`LoginPageRenderTest::login_page_no_new_hand_written_hex_literals`** — re-run existing grep guard to confirm we did not regress.
7. **`LoginPageRenderTest::login_page_prefers_reduced_motion_collapses_all_new_motion`** — source-grep for the three new motion paths (magnetic, validation, morph, loading) and assert each has a corresponding collapse block.

### Frontend (math / unit)
1. `tests/Unit/Composables/MagneticHoverTest.php` — pure-math test for the clamp function (max-distance, recenter on mouse-leave, reduced-motion bypass). No Vue runtime needed.
2. `tests/Unit/Composables/FieldValidationTest.php` — debounce timing (200ms ± 20ms), state transitions, no re-validate on unchanged input.

### Frontend (Playwright / manual)
1. Magnetic hover: mouse enters button bounds, button tracks cursor up to 50% of its size, releases back to center on mouse-leave. Under `prefers-reduced-motion: reduce`, the magnetic effect is inert and the button uses the existing `translateY(-1px)` hover lift.
2. Live validation: typing in username with 1 char triggers "El usuario es requerido" then clears it; typing in password with 0 chars after focus blur shows the error with slide-down animation; success state shows a tiny checkmark with scale-in.
3. Glassmorphism: the form Card surface shows a blurred bento behind it; toggling `prefers-reduced-transparency: reduce` collapses to opaque.
4. Morph: after a successful login (mock the response), the brand glyph morphs from the tooth path to a check path in ~600ms, then the router pushes to /dashboard.
5. Loading stages: pressing submit triggers a 3-stage progress indicator (`Validando` → `Autenticando` → `Listo`) with ~150ms per stage.

---

## 7. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Magnetic cursor on the SUBMIT button only works with a mouse. Keyboard-only users must have an equal-feel path. | Medium | Magnetic hover is purely additive on the visual side. Keyboard `Tab → Enter` already triggers the same submit path. Reduced-motion + `pointer: coarse` (touch devices) bypass the magnetic effect. |
| Glassmorphism on a near-white bento behind the card can render as a smear instead of a readable surface. | Medium | Use `decorative-glass` (the lighter alpha) NOT `surface-glass` (heavier alpha designed for chrome). Verify on iPhone 12 viewport (smallest realistic target). The LoginPage already uses `prefers-reduced-transparency` to flatten. |
| SVG path morph fails if the two paths have different command counts. | High | Pre-bake both paths in Inkscape / Figma and validate parity BEFORE writing the Vue template. Test with `path1.length === path2.length` AND `path1.match(/[MCLZ]/gi).length === path2.match(/[MCLZ]/gi).length`. |
| Live validation causes jank on rapid typing. | Medium | Debounce 200ms with `setTimeout`; cancel previous timer on each keystroke. Reduced-motion: still debounced (not skipped) so the validation logic is consistent. |
| `v-motion` from `@vueuse/motion` requires importing the plugin in `app.js`. | Low | Add `import { MotionPlugin } from '@vueuse/motion'` and `app.use(MotionPlugin)` to `resources/js/app.js`. Single import, single `app.use` call. |
| `@vueuse/motion` bundle size ~10kb gzipped is acceptable per AGENTS.md but still a new dep. | Low | Locked to a single major version in `package.json`. Removed if unused after a future cleanup. |
| Multi-stage progress indicator may feel slow on a fast login (200ms total). | Low | Each stage is 150ms with `response 0.15` spring; the whole sequence is sub-second. On a real 500ms network call the stages line up with the network, not against it. |
| Reduced-motion bypass must cover the morph (SMIL `<animate>` runs even under reduced-motion). | High | The morph runs ONLY on success (post-`login` resolution). Under reduced-motion, the success state uses a 200ms opacity cross-fade between two static SVGs (tooth fades out, check fades in). No SMIL. |

---

## 8. Open questions to ask before proposal

Three questions only — the rest is settled by the user's earlier answers.

**Q1 — Magnetic hover max-distance and bounce.**
The reference (21st.dev / motion.dev) shows the magnet offset is typically 30-50% of the button size. Apple HIG uses ~20%. Our button is `min-h-[52px]` (size lg) and the brand surface is conservative. **Proposed default**: 40% (so a 52px button offsets up to ~10px in X and Y). Damping 0.7 (underdamped → one gentle bounce on release). Is that the right feel, or should the spec use a flatter 25% / damping 1.0 (critically damped, no bounce)? A bounce reads more "playful", a flat damping reads more "premium Apple". Default proposal: **bounce on, but limited**.

**Q2 — Live validation aggressiveness.**
Apple HIG says "validate on commit, not on change" — i.e. only show errors after the user has finished typing in the field AND blurred. The reference material suggests "validate as you type, show success early". Which feels right for a clinical tool? **Default proposal**: validate on blur OR after 250ms of no input change (whichever first). Success state shows immediately on meeting rules (e.g. password length ≥ 8). This avoids the "nag" feel of per-keystroke validation while still feeling responsive.

**Q3 — Morph trigger condition.**
The brand-glyph morph (tooth → check) fires on `success` (post-login, before router push). The router push happens ~immediately after the API resolves. **Question**: should the morph complete BEFORE the router push (so the user sees the check on the login screen), or should the morph start AND the router push happen in parallel (so the morph runs during the route transition)? **Default proposal**: complete the morph first (~600ms), then push. The check landing on the login surface is the climax — it should not be eaten by the route transition.

---

## Result

```yaml
status: success
executive_summary: |
  Inventory complete. The login already carries 3 stacked motion layers (card
  entrance, hero entrance, primary button press). useSpring2D is declared but has
  zero real consumers — wiring it as the magnetic-hover primitive gives us the
  first honest use. Glassmorphism is already covered by `.decorative-glass` and
  `.surface-glass` in the generated CSS. Live validation, SVG morph, and multi-
  stage loading are new surface area but all four behaviours reduce cleanly to
  the existing tokens + primitives + one new dep (@vueuse/motion, ~10kb).
  Six PHPUnit source-inspection assertions + two math tests + a manual
  Playwright sweep cover the test gap.
artifacts:
  - openspec/changes/ui-login-premium-motion-2026-08/explore.md   (this file)
  - engram://sdd/ui-login-premium-motion-2026-08/explore           (persisted)
next_recommended: sdd-proposal
risks:
  - SVG path parity must be pre-validated; if commands don't match, the morph falls back to opacity cross-fade (degraded but not broken)
  - Magnetic hover is mouse-only; keyboard and touch users MUST get an equal-feel fallback (the existing translateY hover lift)
  - @vueuse/motion must be installed and registered in app.js; missing the app.use call silently breaks all v-motion directives
  - The hero bento behind the form card is the only thing that justifies glassmorphism blur; if a future redesign removes the bento, the glass collapses
skill_resolution: paths-injected   # parent carried forward project-specific skills (none required for read-only explore)
```
