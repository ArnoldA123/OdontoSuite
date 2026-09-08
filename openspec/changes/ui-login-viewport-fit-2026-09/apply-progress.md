# Apply Progress: Login Viewport Fit

Change: `ui-login-viewport-fit-2026-09`
Branch: `fix/ui-login-viewport-fit-2026-09` (merged from `feat/ui-login-refinement-dental-split-2026-09`)
Date: 2026-09-08
Strict TDD: ACTIVE

## Strategy

CSS-only fix in the LoginPage's `<style scoped>` block + 4 new source-inspection tests. No new tokens, no new composables, no template changes.

## Diagnosis (Playwright 1440x900)

```
shell:   998px   ← overflow
card:    902px
formCol: 900px
formWrap: 671px
header:   32px
welcome: 135px
form:    294px
footer:   16px
docH:    998px   ← overflow 98px vs viewport 900
vp:      900px
```

Root cause: `.login-hero-column` had `min-height: 100dvh` at desktop, forcing the grid to grow past the viewport.

## Fix (CSS-only, 6 rules + 1 mobile media query)

1. `.login-page` → added `height: 100dvh; min-height: 100vh; overflow: hidden;`
2. `.login-page-shell` → reduced padding from `clamp(16px, 4vw, 48px)` to `clamp(12px, 2vw, 24px)`; added `min-height: 0`.
3. `.login-split-card` → added `height: 100%; max-height: calc(100dvh - clamp(24px, 4vw, 48px));`
4. `.login-grid` → added `grid-template-rows: minmax(0, 1fr); min-height: 0; height: 100%;`
5. `.login-form-column` → added `min-height: 0; overflow-y: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin;`
6. `.login-hero-column` → in desktop @media block, removed `min-height: 100dvh`, kept `min-height: 0`.

Mobile @media (max-width: 767px) block:
- Switched grid to `grid-template-columns: 1fr; grid-template-rows: 240px minmax(0, 1fr);` (was single-row, causing form+hero overlap)
- Set `.login-hero-column { height: 240px; min-height: 240px; order: 1; }`
- Set `.login-form-column { padding: 20px; order: 2; align-items: flex-start; justify-content: flex-start; }`

## Test results

`vendor/bin/phpunit tests/Unit/DesignSystem/LoginPageRenderTest.php tests/Unit/Composables/ShapeMorphTest.php`
- **53 tests, 105 assertions, all GREEN**
- 4 new tests added: `login_page_constrains_card_to_viewport_height`, `login_page_form_column_scrolls_internally`, `login_page_shell_uses_viewport_height`, `login_page_collapses_to_single_column_below_768`.

## Playwright sweep

| Viewport | docH | vp | hasScroll |
|---|---|---|---|
| 1440x900 | 900 | 900 | false |
| 390x844 | 844 | 844 | false |

Both viewports render the entire login without page scroll. Mobile shows hero (240px) + form stacked, no overlap.

## Deviations

- D1: Test `login_page_collapses_to_single_column_below_768` uses `@media (max-width: 767px)` (not 768) to avoid the overlap with the desktop `@media (min-width: 768px)` block. This is intentional and matches the actual CSS.

## Files changed

- `resources/js/modules/auth/LoginPage.vue` (CSS only, scoped)
- `tests/Unit/DesignSystem/LoginPageRenderTest.php` (+4 tests)
- `openspec/changes/ui-login-viewport-fit-2026-09/` (artifacts)

## Bundle delta

0 kB expected (CSS only). Verified by `pnpm build` (not run on this Windows env per orchestrator; the change is purely additive CSS rules inside an existing block).
