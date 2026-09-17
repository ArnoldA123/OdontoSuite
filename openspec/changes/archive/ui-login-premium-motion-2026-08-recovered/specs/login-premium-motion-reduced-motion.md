# Spec: login-premium-motion-reduced-motion

## Summary
Cross-cutting spec: every new motion path (magnetic hover, live validation, SVG morph, multi-stage loading, shake) MUST collapse to instant OR a ≤200ms opacity/colour change under `prefers-reduced-motion: reduce`. This spec enforces the cross-cutting invariant and the GPU-compositor-only rule.

## Source-of-truth references
- WCAG 2.3.3 Animation from Interactions (W3C technique C39)
- MDN `prefers-reduced-motion`
- The existing `LoginPage.vue` reduced-motion media-query block (existing entrance collapse)

## Scenarios

### R1 — Every new motion path has a reduced-motion collapse
**Given** a motion path is added to the login (magnetic, validation, morph, loading stage, shake),
**Then** the source MUST contain a `@media (prefers-reduced-motion: reduce)` block that replaces the motion with an opacity OR colour change of at most `var(--motion-duration-normal)` (200ms),
**And** the block MUST be co-located with the motion declaration (not in a global stylesheet).

### R2 — GPU compositor only
**Given** any new motion path is added,
**Then** the animated properties MUST be limited to `transform` and `opacity`,
**And** the source MUST NOT contain new animations on `width`, `height`, `top`, or `left` properties.

### R3 — Source-inspection testability
**Given** the reduced-motion collapse must be testable,
**Then** the source MUST declare a `@media (prefers-reduced-motion: reduce)` block per motion path,
**And** a source-grep test MUST find at least four such blocks (one per motion path: magnetic, validation, morph, loading).

### R4 — OS setting toggled mid-flight is honored
**Given** the magnetic composable uses `useSpring2D` (which inherits `useSpring`'s contract),
**When** the user toggles `prefers-reduced-motion: reduce` while the magnetic effect is in-flight,
**Then** the spring MUST settle instantly (via the existing `instantSettle` path).

### R5 — SMIL morph does not run under reduced-motion
**Given** the morph uses SMIL `<animate attributeName="d">`,
**When** `prefers-reduced-motion: reduce` is active,
**Then** the SMIL element MUST NOT be rendered (the template conditionally renders the opacity cross-fade fallback instead).

## Acceptance criteria
- AC1: `tests/Unit/DesignSystem/LoginPageRenderTest.php` extends with `login_page_prefers_reduced_motion_collapses_all_new_motion` (source-grep for ≥ 4 `prefers-reduced-motion` blocks; one per motion path).
- AC2: A source-grep test asserts NO new `@keyframes` rules animate `width`/`height`/`top`/`left` (only `transform` and `opacity`).
- AC3: The magnetic composable inherits the `useSpring` reduced-motion contract (no extra code needed; verified by `UseSpringMathTest::prefers_reduced_motion_instant_settle`).
