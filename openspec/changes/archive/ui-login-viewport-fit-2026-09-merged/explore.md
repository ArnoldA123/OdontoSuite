# Explore: Login Viewport Fit

Change: `ui-login-viewport-fit-2026-09`
Date: 2026-09-08
Branch: `fix/ui-login-viewport-fit-2026-09` (merged from `feat/ui-login-refinement-dental-split-2026-09`)

## Problem

After `ui-login-refinement-dental-split-2026-09` ships, the login page no longer fits in a 1440×900 viewport without vertical scroll.

## Playwright diagnosis (1440×900 viewport)

```
shell:   998px   ← overflow
card:    902px
formCol: 900px
formWrap: 671px
header:   32px   (brand)
welcome: 135px   (headline + subtitle)
form:    294px   (2 inputs + recordarme + submit)
footer:   16px   (Términos link inside card)
docH:    998px   ← overflow 98px vs viewport 900
vp:      900px
```

Sum of vertical content inside the form column: header(32) + welcome(135) + form(294) + options + submit + footer + paddings + margins ≈ 838px before the wrapper.

## Root cause

The login shell is sized by its content, not by the viewport:
- `html`, `body` are not constrained (`height: 100%` missing).
- `.login-page-shell` has no `min-height: 100vh` and no `display: flex` to center the card.
- `.login-split-card` has `height: auto` — it grows with content past the viewport.
- The grid columns are not `min-height: 0` so they cannot shrink, forcing the grid to overflow.

## Affected files

- `resources/js/modules/auth/LoginPage.vue` — only the `<style scoped>` block needs changes.
- `tests/Unit/DesignSystem/LoginPageRenderTest.php` — add 4 new source-inspection assertions.
- `resources/views/app.blade.php` — verify viewport meta tag is present (already present in the project per AGENTS, but worth a regression test).

## Out of scope

- New tokens. The fix is pure CSS layout.
- New composables. No state changes.
- Refactoring the polymorphic submit / form Card morph. Untouched.
- The 3 floating overlays. Their positioning already uses `position: absolute` so they don't affect the card's intrinsic height.
- Backend. Zero changes.

## Result

```yaml
status: success
executive_summary: |
  The login overflows 98px on 1440×900 because the card is content-sized, not
  viewport-sized. The fix is a 6-rule CSS-only change: constrain the shell +
  card to the viewport, give the form column overflow-y: auto so it can
  scroll internally if the form content is too tall, and tighten the internal
  paddings by ~16px. No new tokens, no new composables, no Vue template changes.
next_recommended: sdd-proposal (compact)
risks:
  - The form column's overflow-y: auto must use the correct iOS momentum
    scroll (`-webkit-overflow-scrolling: touch` for older iOS).
  - The hero column must NOT scroll (image would jitter). Use overflow: hidden
    on .login-hero-wrap and ensure the image is `position: absolute` filling
    the wrapper.
  - At < 768px the card must collapse to single column with hero on top at a
    fixed 240px height; the form gets the remaining height with overflow-y: auto.
skill_resolution: paths-injected
```
