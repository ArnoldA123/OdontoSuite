# Task 11: dashboard-status-chips

**Phase**: PR2
**LOC estimate**: ~60
**Spec scenario ref**: `Cash status badge color matches state`, `Stat number not colored`
**Design decision ref**: Decision 7 (iOS filled status chip pattern)

## Description

Edit `resources/js/modules/dashboard/DashboardPage.vue`: icon chip backgrounds re-keyed to iOS filled pattern (`bg-systemGreen-100 text-systemGreen-600`, `bg-systemOrange-100 text-systemOrange-600`, `bg-systemGray-100 text-systemGray-600`, `bg-systemBlue-100 text-systemBlue-600`, `bg-systemRed-100 text-systemRed-600`); chip dimensions 32 px rounded-square (10 px radius). Cash status badge: "Abierta" -> `bg-systemGreen-100 text-systemGreen-600`, "Cerrada" -> `bg-systemRed-100 text-systemRed-600`, "Sin sesion" -> `bg-systemGray-100 text-systemGray-600`. "Citas Hoy" stat number `text-terracotta-600` -> `text-label`. Card border `border-ink-200` -> `border-separator`. The 300 ms WS debounce at line 882 is preserved (load-bearing for `motion does not fight motion`).

## Acceptance criteria

- `vendor/bin/phpunit --filter dashboard_cash_badge_color_matches_state` exits 0.
- `vendor/bin/phpunit --filter dashboard_stat_number_uses_text_label` exits 0.
- `vendor/bin/phpunit --filter dashboard_no_linear_gradient` exits 0.
- `pnpm build` exits 0.
- Playwright checkpoint 5 (dashboard.png): 5 stat cards with iOS filled icon chips; "Citas Hoy" big number is pure black; cash status badge is iOS filled pattern.
- `grep -n "debounce" resources/js/modules/dashboard/DashboardPage.vue` returns at least 1 row (sanity: 300 ms debounce preserved).

## Files touched

- `resources/js/modules/dashboard/DashboardPage.vue`: modify (~+35 / -50).
