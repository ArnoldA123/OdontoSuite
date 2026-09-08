# Spec: Login Viewport Fit

## V1 — Desktop no overflow

At viewport 1440×900, `document.documentElement.scrollHeight` MUST equal `window.innerHeight` (±1px tolerance for sub-pixel rounding). The login MUST be visible end-to-end with no vertical page scroll.

## V2 — Mobile no overflow

At viewport 390×844 (iPhone 12), `document.documentElement.scrollHeight` MUST equal `window.innerHeight`. The page MUST collapse to a single column with the hero on top.

## V3 — Tablet collapse

At viewport 768×1024, the card MUST be full-width, single column, hero at top with `height: 240px`, form below.

## V4 — Form column internal scroll

If the form's intrinsic content is taller than the form column, the form column MUST scroll internally (overflow-y: auto) rather than pushing the page to scroll.

## Acceptance criteria

- AC1: Playwright 1440×900 reports `hasScroll: false` and `docH == vp`.
- AC2: Playwright 390×844 reports `hasScroll: false` and `docH == vp`.
- AC3: Playwright 768×1024 shows single column with hero at 240px height.
- AC4: `login_page_split_card_constrains_height_to_viewport` source-grep test GREEN.
- AC5: `login_page_form_column_has_overflow_auto` source-grep test GREEN.
- AC6: `login_page_no_external_overflow_at_desktop` Playwright-driven test GREEN.
- AC7: `login_page_collapses_to_single_column_below_768` source-grep test GREEN.
