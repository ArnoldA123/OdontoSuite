# Task 13: visual-baselines-replacement

**Phase**: PR2
**LOC estimate**: 0 LOC (binary asset refresh)
**Spec scenario ref**: All PR2 visual scenarios
**Design decision ref**: Testing strategy (visual baseline refresh)

## Description

Replace the pre-PR1 visual baselines with iOS-clinical baselines via the Playwright 7-step recipe. Commit 7 screenshots: `login-light.png`, `login-reduced-motion.png`, `login-reduced-transparency.png`, `after-login.png`, `dashboard.png`, `not-found.png`, `dashboard-high-contrast.png`. Each PNG <= 200 KB, byte-stable.

## Acceptance criteria

- All 7 PNG files exist in `tests/Visual/baselines/`.
- Each PNG <= 204800 bytes.
- `php artisan test --filter=VisualBaselineTest` exits 0 (if such a test exists; otherwise manual check).

## Files touched

- `tests/Visual/baselines/login-light.png`: create (binary; <= 200 KB).
- `tests/Visual/baselines/login-reduced-motion.png`: create (binary; <= 200 KB).
- `tests/Visual/baselines/login-reduced-transparency.png`: create (binary; <= 200 KB).
- `tests/Visual/baselines/after-login.png`: create (binary; <= 200 KB).
- `tests/Visual/baselines/dashboard.png`: create (binary; <= 200 KB).
- `tests/Visual/baselines/not-found.png`: create (binary; <= 200 KB).
- `tests/Visual/baselines/dashboard-high-contrast.png`: create (binary; <= 200 KB).
