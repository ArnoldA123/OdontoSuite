# Task 10: login-page-restyle

**Phase**: PR2
**LOC estimate**: ~50
**Spec scenario ref**: `Login card chrome`
**Design decision ref**: Decision 3 (token architecture), Decision 6 (typography)

## Description

Edit `resources/js/modules/auth/LoginPage.vue`: drop `font-family: var(--font-serif)` on `.welcome-headline`, `.hero-caption-title`, and the `prefers-contrast` block (3 call sites). Card surface `bg-cream-100` -> `bg-systemBackground`; corners `rounded-xl` -> `rounded-ios` (10 px); border `border-ink-200` -> `border-separator`. Icon ring `var(--color-terracotta-500)` -> `var(--color-systemBlue-500)`. Primary button `bg-terracotta-500` -> `bg-systemBlue-500`. Entrance spring timings stay unchanged.

## Acceptance criteria

- `vendor/bin/phpunit --filter login_page_drops_var_font_serif` exits 0.
- `pnpm build` exits 0.
- Playwright checkpoint 1 (login-light.png): card is white with 10 px corners and hairline `border-separator`; primary button is `systemBlue`; headline font-family is system stack (NOT serif).
- Playwright checkpoint 2 (login-reduced-motion.png): no entrance translation; opacity cross-fade only.
- Playwright checkpoint 3 (login-reduced-transparency.png): chrome solid white, no `backdrop-filter`.

## Files touched

- `resources/js/modules/auth/LoginPage.vue`: modify (~+20 / -25).
- `resources/js/modules/auth/ForgotPasswordModal.vue`: modify (inherit only; ~+3 / -3).
- `resources/js/modules/auth/ResetPasswordModal.vue`: modify (inherit only; ~+3 / -3).
