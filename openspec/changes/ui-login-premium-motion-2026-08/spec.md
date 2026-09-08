# Spec: Login Premium Motion (`ui-login-premium-motion-2026-08`)

Change: `ui-login-premium-motion-2026-08`
Phase: spec
Artifact store: hybrid (this file + Engram `sdd/ui-login-premium-motion-2026-08/spec`)

---

## Capability overview

Seven new specs, all scoped to the login surface. Each spec is independent — it can be implemented and verified in isolation. The first two specs (`login-magnetic-hover`, `login-live-validation`) name new composables and their contracts; the rest (`login-glassmorphism`, `login-brand-glyph-morph`, `login-multi-stage-loading`, `login-premium-motion-reduced-motion`, `login-premium-motion-bundle-budget`) name behaviors that compose those primitives into the LoginPage.

| Spec ID | Title | Owner | File |
|---|---|---|---|
| `login-magnetic-hover` | Magnetic cursor on submit button | new composable | `specs/login-magnetic-hover.md` |
| `login-live-validation` | Live form validation as the user types | new composable | `specs/login-live-validation.md` |
| `login-glassmorphism` | Translucent surface on the form Card | composition | `specs/login-glassmorphism.md` |
| `login-brand-glyph-morph` | Tooth SVG morphs to checkmark on success | composition | `specs/login-brand-glyph-morph.md` |
| `login-multi-stage-loading` | Three-stage loading indicator on submit | composition | `specs/login-multi-stage-loading.md` |
| `login-premium-motion-reduced-motion` | Reduced-motion collapse for all new motion paths | cross-cutting | `specs/login-premium-motion-reduced-motion.md` |
| `login-premium-motion-bundle-budget` | Bundle-size guard for `@vueuse/motion` | cross-cutting | `specs/login-premium-motion-bundle-budget.md` |

Mapping to the PR slices (declared in `design.md`):

| Spec | PR1 foundation | PR2 composition |
|---|:---:|:---:|
| `login-magnetic-hover` | ✓ (composable + Button.vue hook + math tests) | ✓ (LoginPage wires it) |
| `login-live-validation` | ✓ (composable + state tests) | ✓ (LoginPage wires it) |
| `login-glassmorphism` | — | ✓ |
| `login-brand-glyph-morph` | — | ✓ |
| `login-multi-stage-loading` | — | ✓ |
| `login-premium-motion-reduced-motion` | ✓ (in Button hook) | ✓ (in LoginPage each path) |
| `login-premium-motion-bundle-budget` | ✓ (CI assertion) | — |

---

## Spec index (pointers)

The full Given/When/Then scenarios are in `specs/*.md`. This index is the authoritative table of contents.

### `login-magnetic-hover`
1. `M1` When the cursor enters the submit button bounds, the button MUST tilt toward the cursor.
2. `M2` The maximum tilt offset MUST be clamped to 40% of the smaller of the button's width or height.
3. `M3` On cursor-leave, the button MUST spring back to `(0, 0)` with one gentle bounce (damping 0.7).
4. `M4` Under `prefers-reduced-motion: reduce`, the magnetic effect MUST be inert; the existing `translateY(-1px)` hover lift continues to work.
5. `M5` On `(pointer: coarse)` devices (touch), the magnetic effect MUST be inert.
6. `M6` The spring MUST settle within 600ms of cursor-leave.

### `login-live-validation`
1. `V1` A field's error MUST NOT be shown until the user blurs the field OR has been idle for 250ms (whichever is first).
2. `V2` A field's success indicator MUST appear immediately upon meeting all rules (no debounce).
3. `V3` The error message MUST slide down + fade in (transform + opacity, GPU compositor only).
4. `V4` On input change, the previous debounce timer MUST be cancelled and a new one scheduled.
5. `V5` The composable MUST expose `validateField(field)` and `validateAll()` methods callable from the parent.
6. `V6` Errors MUST be inline below the input (NOT a toast), preserving the existing `aria-live` region on auth failure.

### `login-glassmorphism`
1. `G1` The form Card surface MUST apply a translucent background + backdrop-filter blur.
2. `G2` The class `.decorative-glass` (from `resources/css/tokens.generated.css`) MUST be used; no hand-rolled `backdrop-filter` declarations.
3. `G3` Under `prefers-reduced-transparency: reduce`, the surface MUST collapse to opaque.
4. `G4` The blur MUST render correctly at iPhone 12 viewport (390x844) without breaking the form's readability.

### `login-brand-glyph-morph`
1. `P1` On successful login, the brand glyph MUST morph from the tooth path to a check path.
2. `P2` The two paths MUST have identical SVG command-letter counts (M, C, L, Z). The morph MUST be pre-validated before template commit.
3. `P3` The morph duration MUST be ~600ms, driven by SMIL `<animate attributeName="d">`.
4. `P4` Under `prefers-reduced-motion: reduce`, the morph MUST be an opacity cross-fade between the two static SVGs (no SMIL).
5. `P5` The morph MUST complete BEFORE `router.push('/dashboard')` is called.

### `login-multi-stage-loading`
1. `L1` On submit, the form content MUST be replaced with a 3-stage indicator: `Validando` → `Autenticando` → `Listo`.
2. `L2` Each stage MUST crossfade with the next in ~150ms.
3. `L3` A skeleton placeholder MUST mimic the final form's headline height to avoid layout shift.
4. `L4` On failure (API error), the form MUST reappear with a 220ms shake animation. Under reduced-motion, the shake collapses to a 200ms opacity flash.
5. `L5` On success, the form does not reappear — the morph (spec `P*`) handles the success surface.

### `login-premium-motion-reduced-motion`
1. `R1` Every new motion path (magnetic, validation, morph, loading stages, shake) MUST collapse to instant OR a ≤200ms opacity/colour change under `prefers-reduced-motion: reduce`.
2. `R2` No new motion path MAY use `width`, `height`, `top`, or `left` properties (GPU compositor only).
3. `R3` The reduced-motion collapse MUST be testable by source-inspection (each path has its own media-query block).
4. `R4` Switching the OS setting mid-flight MUST be honoured (already true for `useSpring`; the magnetic composable MUST inherit this contract).

### `login-premium-motion-bundle-budget`
1. `B1` The new dependency `@vueuse/motion` MUST add ≤12kb gzipped to the production build.
2. `B2` The plugin MUST be registered in `resources/js/app.js` via `app.use(MotionPlugin)` (or the equivalent import).
3. `B3` Source-inspection test MUST assert the plugin registration exists.
4. `B4` If the budget is exceeded, the change MUST NOT ship until either (a) tree-shaking is verified, or (b) the dependency is downgraded or replaced.

---

## Capability `auth/login-page` (no change)

The existing `/api/auth/login` endpoint, error envelope, and rate-limit middleware are untouched. The change is pure frontend composition. The `login` composable's contract (`useAuth().login(credentials)` returns the response on success, throws on failure) is preserved.

---

## Out of scope (deferred capabilities — future slices)

The proposal deferred five categories. Each becomes its own capability in a future change:

- `microinteraction-drag-physics` — drag & drop with snap/rebote/momentum. Land in a future "scheduler drag-and-drop" slice.
- `microinteraction-longpress-preview` — long-press with detail preview. Land in a future calendar / patients list slice.
- `microinteraction-toggle-transitions` — animated toggle/checkbox/radio. Land in a future "form primitives polish" slice.
- `microinteraction-scrollytelling` — pinned sections, chapter progress, parallax. Land in a future marketing / tour slice.
- `microinteraction-sound-haptics` — opt-in audio + `navigator.vibrate`. Land only if a screen demands it.

None of these are needed for the login and none are wired in this change.

---

## Result

```yaml
status: success
executive_summary: |
  Seven specs written, each with 4-6 Given/When/Then scenarios and RFC 2119
  keywords. Five of the seven are composition specs (LoginPage edits only);
  two are new-composable specs (useMagneticHover + useFieldValidation) that
  land in PR1 and are consumed in PR2. The cross-cutting specs (reduced-
  motion, bundle-budget) gate the change's a11y and perf contracts.
artifacts:
  - openspec/changes/ui-login-premium-motion-2026-08/spec.md        (this file)
  - openspec/changes/ui-login-premium-motion-2026-08/specs/*.md      (7 spec files)
  - engram://sdd/ui-login-premium-motion-2026-08/spec                (persisted)
next_recommended: sdd-design
risks:
  - Magnetic hover must be mouse-only; keyboard/touch users MUST see no behaviour change. R1 + R4 in the reduced-motion spec cover this.
  - SVG morph command-count parity must be pre-validated. P2 makes this a hard gate.
  - Multi-stage loading must not feel slow on fast networks. L1-L3 explicitly cap each stage at 150ms.
skill_resolution: paths-injected
```
