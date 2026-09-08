# Spec: Login Refinement — Reduced Motion

Change: `ui-login-refinement-dental-split-2026-09`

## R1 — Transparency collapse

- Under `prefers-reduced-transparency: reduce`, the outer card MUST flatten to `background: var(--color-system-background)` (no translucency, no shadow).
- Under `prefers-reduced-transparency: reduce`, the floating overlay cards MUST flatten to `background: #ffffff` (no blur, no translucency).

## R2 — Motion collapse

- Under `prefers-reduced-motion: reduce`, the outer card's entrance MUST collapse to a static render (no spring, no opacity ramp).
- Under `prefers-reduced-motion: reduce`, every overlay card entrance MUST collapse to a static render (no stagger, no `y`).
- Under `prefers-reduced-motion: reduce`, the submit polymorphic state crossfade MUST be opacity-only (no `y`, no `scale`).
- Under `prefers-reduced-motion: reduce`, the brand-glyph tooth→check morph MUST use the existing opacity cross-fade path (no SMIL `<animate>`).
- Under `prefers-reduced-motion: reduce`, the magnetic-hover effect MUST be inert (button does not track the cursor).

## R3 — Opacity crossfade

- Every `v-motion` directive in the login MUST be wrapped in a check that returns `false` for the enter/leave variants when `prefers-reduced-motion: reduce` matches.
- The crossfade duration MUST be 150ms (faster than the 200-250ms default) to feel "almost instant" under reduced-motion.

## R4 — Image respect

- The hero image `<img>` MUST carry the `prefers-reduced-motion: reduce` semantics: the page does NOT animate the image in any way. The image either loads or it does not. There is no parallax, no Ken-Burns, no fade-in beyond the natural browser behavior.

## Acceptance criteria

- AC1: Toggling DevTools "prefers-reduced-motion: reduce" and reloading the page removes every spring/opacity-ramp entrance.
- AC2: Toggling DevTools "prefers-reduced-transparency: reduce" flattens the outer card and the floating overlays.
- AC3: No console errors are introduced by the reduced-motion paths.
