# Task 07: chrome-surface-glass-rgba

**Phase**: PR1
**LOC estimate**: 0 LOC (covered by task 03 generator emit)
**Spec scenario ref**: `surface-glass rgba`, `Reduced transparency solidifies chrome`
**Design decision ref**: Decision 5 (Liquid-Glass chrome: white-on-white)

## Description

The `.surface-glass` class is emitted by `scripts/build-tokens-css.mjs` (task 03). This task asserts the rgba and media-query contract from the unit-test side: background `rgb(255 255 255 / 0.78)`, border `rgb(0 0 0 / 0.06)`, shadow `rgb(0 0 0 / 0.10)`. `@media (prefers-reduced-transparency: reduce)` collapses to solid `var(--color-systemBackground)` with `backdrop-filter: none`.

## Acceptance criteria

- `vendor/bin/phpunit --filter generated_css_surface_glass_uses_white_on_white_and_pure_black_shadow` exits 0.
- `grep -n "rgb(250 249 247" resources/css/tokens.generated.css` returns 0 rows (no cream-on-cream rgba survives).
- `grep -n "rgba(20, 17, 14" resources/css/tokens.generated.css` returns 0 rows (no warm-black shadow).

## Files touched

- (No files; verification task. Generator emit lives in task 03.)
