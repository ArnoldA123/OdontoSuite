# Spec: login-glassmorphism

## Summary
The form Card surface becomes translucent, blurring the hero bento behind it. Implemented by attaching the existing `.decorative-glass` class (lighter alpha, designed for decorative surfaces) from `resources/css/tokens.generated.css`. The class already has `prefers-reduced-transparency: reduce` collapse built in.

## Source-of-truth references
- `.decorative-glass` in `resources/css/tokens.generated.css` (committed; emits `backdrop-filter: blur(20px) saturate(180%) contrast(1.04)` + a `prefers-reduced-transparency` collapse).
- `.surface-glass` (heavier, used in AppLayout chrome — NOT used here).

## Scenarios

### G1 — Card surface is translucent
**Given** the form Card is rendered with `variant="elevated"` (the existing choice) AND has the `.decorative-glass` class attached,
**Then** the Card's background MUST be translucent (alpha < 1.0) AND must apply `backdrop-filter: blur(...)` so the bento behind it is visibly blurred.

### G2 — Uses existing CSS class, no hand-rolled backdrop-filter
**Given** the LoginPage source is committed,
**Then** the LoginPage MUST reference `.decorative-glass` (or `.surface-glass`) by class,
**And** it MUST NOT contain a hand-written `backdrop-filter` declaration in any `<style scoped>` block.

### G3 — Reduced-transparency collapse is honored
**Given** the user has `prefers-reduced-transparency: reduce` enabled,
**When** the form Card is rendered,
**Then** the Card's surface MUST collapse to an opaque fill (the `.decorative-glass` collapse rule from generated CSS handles this).

### G4 — iPhone 12 viewport legibility
**Given** the viewport is `390x844` (iPhone 12),
**When** the form Card is rendered,
**Then** the form's text MUST remain readable (contrast ratio ≥ 4.5:1 measured against the blurred bento's darkest expected tone).

## Acceptance criteria
- AC1: The LoginPage template attaches `decorative-glass` to the form Card's root class list.
- AC2: `tests/Unit/DesignSystem/LoginPageRenderTest.php` extends with `login_page_form_card_uses_glass_class` (source-grep for `decorative-glass` OR `backdrop-filter` + the reduced-transparency recovery rule).
- AC3: `pnpm build` succeeds; the generated CSS contains the existing `.decorative-glass` rule (it already does — this is a sanity check).
- AC4: The source contains NO hand-written hex literals (existing `login_page_no_hand_written_hex_literals` test must not regress).
