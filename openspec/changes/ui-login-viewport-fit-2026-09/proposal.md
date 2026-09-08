# Proposal: Login Viewport Fit

## Intent

Fix the vertical overflow on the login page introduced by `ui-login-refinement-dental-split-2026-09`. The login must fit in a 1440×900 viewport with zero vertical scroll. Below 768px it must collapse to a single column with a fixed-height hero strip and a scrollable form region — never a vertical scroll on the page itself.

## Approach

Pure CSS layout change inside the LoginPage's `<style scoped>` block. Six rules:

1. **`.login-page`** → add `min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box;`
2. **`.login-page-shell`** → keep as the outer wrapper; ensure `width: 100%; max-width: 1200px; height: 100%;`
3. **`.login-split-card`** → `height: 100%; max-height: calc(100vh - 48px); display: grid; grid-template-rows: minmax(0, 1fr); grid-template-columns: 45fr 55fr;`
4. **`.login-form-column`** → `overflow-y: auto; min-height: 0; padding: 40px 32px;` (was padding: 24px 32px; bump to 40px so the form breathes when shrunken)
5. **`.login-hero-wrap`** → `height: 100%; min-height: 0; overflow: hidden; position: relative;`
6. **`.login-hero-wrap img`** → keep `position: absolute; inset: 0; object-fit: cover;`

Plus a `@media (max-width: 768px)` block:

```css
@media (max-width: 768px) {
  .login-page { padding: 0; }
  .login-page-shell { max-width: 100%; height: 100vh; }
  .login-split-card {
    border-radius: 0;
    box-shadow: none;
    grid-template-columns: 1fr;
    grid-template-rows: 240px 1fr;
  }
  .login-hero-wrap { height: 240px; }
  .login-form-column { padding: 24px 20px; }
  .login-footer { padding: 0 20px 16px; }
}
```

## Risks and Rollback

| Risk | Severity | Mitigation |
|---|---|---|
| Form column's overflow-y: auto introduces a visible scrollbar in WebKit | Low | Use `scrollbar-width: thin` + a transparent track on WebKit. |
| Hero image jitter on resize | Low | `position: absolute; inset: 0` on the img prevents reflow. |
| iOS Safari viewport changes (URL bar) cause layout shift | Medium | Use `min-height: 100dvh` (dynamic viewport) where supported; fall back to `100vh`. |
| Mobile form is too short → form contents jumble | Low | Padding 24px 20px is conservative; the form has natural margins. |

Rollback: `git revert <sha>` restores the previous scoped CSS in one step. Zero API/DB impact.

## Success Criteria

- [ ] `vendor/bin/phpunit tests/Unit/DesignSystem/LoginPageRenderTest.php` → 33 + 4 = 37/37 GREEN.
- [ ] Playwright at 1440×900: `document.documentElement.scrollHeight ≤ window.innerHeight`.
- [ ] Playwright at 390×844: `document.documentElement.scrollHeight ≤ window.innerHeight`.
- [ ] Playwright at 768×1024: card is full width, single column, hero at top.
- [ ] `pnpm build` succeeds; bundle delta ≤ 0 kB (CSS-only change).
