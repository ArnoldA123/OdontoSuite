# Task 03: generator-emission-update

**Phase**: PR1
**LOC estimate**: ~40
**Spec scenario ref**: `surface-glass rgba`, `No dark block`
**Design decision ref**: Decision 4 (CSS file consolidation), Decision 5 (Liquid-Glass chrome: white-on-white)

## Description

Edit `scripts/build-tokens-css.mjs` to: drop `@font-face` block emit; drop `--font-serif` declaration; emit iOS semantic aliases (`--color-accent`, `--color-text-primary`, `--color-background`, `--color-border`); swap global shadow rgba from warm-black `rgba(20, 17, 14, ...)` to pure `rgba(0, 0, 0, ...)`; emit `.surface-glass` rgba as `rgb(255 255 255 / 0.78)` background (white-on-white) with `rgb(0 0 0 / 0.06)` border + `rgb(0 0 0 / 0.10)` outer shadow + `@media (prefers-reduced-transparency: reduce)` collapse to `var(--color-systemBackground)`.

## Acceptance criteria

- `vendor/bin/phpunit --filter generated_css_has_no_font_face_no_font_serif` exits 0 (no `@font-face`, no `--font-serif`, no `newsreader` in generated CSS).
- `vendor/bin/phpunit --filter generated_css_surface_glass_uses_white_on_white_and_pure_black_shadow` exits 0 (`.surface-glass` rgba matches `rgb(255 255 255 / ...)`; shadow uses `rgba(0, 0, 0, ...)`).
- `pnpm tokens:build` exits 0.
- `grep -n "rgba(20, 17, 14" resources/css/tokens.generated.css` returns 0 rows.

## Files touched

- `scripts/build-tokens-css.mjs`: modify (~+40 / -20 LOC).
- `resources/css/tokens.generated.css`: regenerate (no hand-edit; ~+300 / -200 LOC).
