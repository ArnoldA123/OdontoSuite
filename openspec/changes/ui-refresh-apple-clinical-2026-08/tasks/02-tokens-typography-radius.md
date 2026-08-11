# Task 02: tokens-typography-radius

**Phase**: PR1
**LOC estimate**: ~30
**Spec scenario ref**: `fontFamily.serif absent`, `Letter spacing tightens with size`, `Radius literals`
**Design decision ref**: Decision 3 (typography + radius)

## Description

In `resources/js/design-system/tokens.js`, drop `fontFamily.serif`, tune `fontSize.letterSpacing` per step for SF/system, and replace `radius.lg/2xl/3xl` with `radius.ios` (10 px) + `radius.modal` (14 px). Remove any `font-optical-sizing` declaration.

## Acceptance criteria

- `vendor/bin/phpunit --filter tokens_module_font_family_sans_only` exits 0 (no `serif` key in `fontFamily`).
- `vendor/bin/phpunit --filter tokens_module_letter_spacing_table` exits 0 (per-step `letterSpacing` matches: `xs/sm/base/lg=0`, `xl=-0.01em`, `2xl=-0.015em`, `3xl=-0.02em`, `4xl/display/hero=-0.022em`).
- `vendor/bin/phpunit --filter tokens_module_radius_ios_and_modal` exits 0 (`radius.ios === '10px'`, `radius.modal === '14px'`; `radius.lg/2xl/3xl` absent).
- `pnpm tokens:build` exits 0 and regenerated CSS has no `font-optical-sizing` declarations.

## Files touched

- `resources/js/design-system/tokens.js`: modify (typography + radius; ~+15 / -30 LOC).
