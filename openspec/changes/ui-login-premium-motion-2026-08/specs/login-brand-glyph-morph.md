# Spec: login-brand-glyph-morph

## Summary
On successful login, the brand glyph (tooth path) morphs into a checkmark path. The morph completes BEFORE `router.push('/dashboard')` is called, so the check is the climax of the login surface. Under `prefers-reduced-motion: reduce`, the morph is an opacity cross-fade between two static SVGs.

## Source-of-truth references
- Flubber / anime.js / GSAP MorphSVG patterns (reference, NOT added as deps)
- SMIL `<animate attributeName="d">` (native browser support)
- The existing `brand-glyph` SVG in `LoginPage.vue`

## Scenarios

### P1 — Morph fires on success only
**Given** the user has submitted valid credentials,
**When** the `useAuth().login()` promise resolves with success,
**Then** the brand-glyph SVG MUST morph from the tooth path to the check path,
**And** the morph MUST complete before `router.push('/dashboard')` is called.

### P2 — Path command parity is pre-validated
**Given** the two paths (tooth + check) are designed,
**When** the template is committed,
**Then** the tooth path and the check path MUST have identical SVG command-letter counts (`M`, `C`, `L`, `Z`),
**And** the source MUST contain a comment block referencing the parity check (e.g. `<!-- M5 C5 L8 Z1 — matches tooth -->`).

### P3 — Morph duration and driver
**Given** the morph is triggered,
**Then** it MUST complete in ~600ms,
**And** it MUST be driven by SMIL `<animate attributeName="d" values="..." dur="0.6s" fill="freeze" />`.

### P4 — Reduced-motion collapse
**Given** the user has `prefers-reduced-motion: reduce`,
**When** the success state is entered,
**Then** the morph MUST be replaced by an opacity cross-fade between two static SVGs (`opacity 1 → 0` over 200ms on path A; `opacity 0 → 1` over 200ms on path B),
**And** no SMIL `<animate>` element MUST be rendered.

### P5 — Morph completes before router push
**Given** the morph is running,
**When** the user waits,
**Then** `router.push('/dashboard')` MUST NOT fire until the morph's `onend` callback (or equivalent `setTimeout` fallback for reduced-motion).

### P6 — Failure path does not trigger morph
**Given** the login fails (API error),
**Then** the brand glyph MUST stay on the tooth path,
**And** the form MUST reappear (per `login-multi-stage-loading` spec).

## Acceptance criteria
- AC1: The brand-glyph SVG template declares BOTH paths (tooth + check) AND an `<animate>` element with matching command counts.
- AC2: `tests/Unit/DesignSystem/LoginPageRenderTest.php` extends with `login_page_brand_glyph_has_morph_paths` (source-grep for two `d=` attribute values OR a paths array, plus the parity comment).
- AC3: The `setTimeout`-based fallback path is documented in a comment block (so a future regression test can find it).
- AC4: Source contains NO hand-written hex literals added (existing test must not regress).
