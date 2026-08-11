# Task 12: not-found-page-restyle

**Phase**: PR2
**LOC estimate**: ~20
**Spec scenario ref**: `404 serif headline gone`
**Design decision ref**: Decision 6 (typography: system font, no Newsreader)

## Description

Edit `resources/js/modules/errors/NotFoundPage.vue`: drop `font-family: var(--font-serif)` on `.not-found-headline` (1 call site). Image border `border-ink-200` -> `border-separator`. Shadow `rgba(31, 27, 23, ...)` -> `rgba(0, 0, 0, ...)` (iOS lighter pure-black rgba). Entrance spring timings stay unchanged.

## Acceptance criteria

- `vendor/bin/phpunit --filter not_found_page_drops_var_font_serif` exits 0.
- `pnpm build` exits 0.
- Playwright checkpoint 6 (not-found.png): system-font headline (NOT serif), image with hairline `border-separator`, lighter pure-black shadow.

## Files touched

- `resources/js/modules/errors/NotFoundPage.vue`: modify (~+6 / -8).
