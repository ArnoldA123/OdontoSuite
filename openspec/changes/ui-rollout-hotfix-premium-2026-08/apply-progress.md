# Apply Progress: ui-rollout-hotfix-premium-2026-08

**Change**: ui-rollout-hotfix-premium-2026-08
**Phase**: apply (post-implementation, pre-verify)
**Date**: 2026-08-22
**Status**: implementation complete, ready for verify

---

## PR-hotfix-login (≤ 280 lines)

### Phase 0: RED — failing tests first

All 8 RED tests written and confirmed failing before implementation:

- T-L01-RED: `HotfixLoginBrandMarkTest` (RED confirmed)
- T-L02-RED: `HotfixLoginHeadlineTrackingTest` (RED confirmed)
- T-L03-RED: `HotfixLoginSubtitleTest` (RED confirmed)
- T-L04-RED: `ButtonConstructionTest` (RED confirmed, project-wide rule)
- T-L05-RED: `HotfixLoginCardSurfaceTest` (RED confirmed)
- T-L06-RED: `HotfixLoginHeroSourceTest` (RED confirmed)
- T-L07-RED: `HotfixLoginMirrorSpringTest` (RED confirmed)
- T-L08-RED: `HotfixLoginEmDashAuditTest` (RED confirmed)

### Phase 1: GREEN — implementation

- T-L01-GREEN: `LoginPage.vue` brand mark replaced (SF-style tooth SVG `stroke-width="1.75"` + "OdontoSuite" wordmark).
- T-L02-GREEN: `.welcome-headline` updated to `text-[clamp(2.25rem,5vw,3rem)] leading-[1.05]` with `letter-spacing: -0.028em`.
- T-L03-GREEN: `.welcome-subtitle` updated to `text-base leading-relaxed`.
- T-L04-GREEN: `Button.vue` primary variant: `box-shadow: var(--elevation-3), inset 0 1px 0 rgba(255, 255, 255, 0.32)`, `:active { transform: translateY(1px); }`, `:disabled` desaturate.
- T-L05-GREEN: `Card.vue` elevated variant renders `box-shadow: var(--elevation-2)` + `border: 1px solid var(--color-hairline)`. `LoginPage.vue` switched from `variant="glass"` to `variant="elevated"`.
- T-L06-GREEN: `public/images/ui/login-hero.jpg` removed. Hero column is now editorial SVG composition (3-tile bento of small line-art SVGs + large "OdontoSuite" wordmark, on `var(--color-canvas)` background).
- T-L07-GREEN: `LoginPage.vue` adds second `useSpring({ response: 0.45, damping: 1.0 })` for hero column (slower mirror, matches iOS sheet reveal per apple-design §7).
- T-L08-GREEN: Em-dash audit on `LoginPage.vue` template text — zero `—` characters found, no edit required.

### Phase 2: VERIFY — visual + computed-style (deferred to verify-report.md)

### Phase 3: COMMIT

- T-L-COMMIT: feat commit recorded as `0cb1acf`.

### Phase 4: HOUSEKEEPING (this file)

- T-L-HOUSEKEEP: this `apply-progress.md` written.

---

## PR-hotfix-dashboard (≤ 380 lines)

### Phase 0: RED — failing tests first

All 9 RED tests written and confirmed failing before implementation:

- T-D01-RED: `SidebarEyebrowAuditTest` (RED confirmed)
- T-D02-RED: `IconInBoxAuditTest` (RED confirmed)
- T-D03-RED: `KpiNumberTabularTest` (RED confirmed)
- T-D04-RED: `HotfixDashboardSurfaceConsistencyTest` (RED confirmed)
- T-D05-RED: `HotfixDashboardQuickActionsTest` (RED confirmed)
- T-D06-RED: `HotfixDashboardEmptyStateTest` (RED confirmed)
- T-D07-RED: `HotfixDashboardDateTabularTest` (RED confirmed)
- T-D08-RED: `HotfixDashboardStaggerTest` (RED confirmed)
- T-D09-RED: `HotfixDashboardEmDashAuditTest` (RED confirmed)

### Phase 1: GREEN — implementation

- T-D01-GREEN: `AppLayout.vue` sidebar section labels removed. Replaced with 1px `var(--color-hairline)` horizontal dividers between sections. No text label.
- T-D02-GREEN: `DashboardPage.vue` icon-in-rounded-gray-box containers removed from KPI cards. 4px accent dot (`var(--color-system-blue-500)`) top-right on each KPI card.
- T-D03-GREEN: `font-feature-settings: var(--font-features-tabular-nums)` applied to KPI number elements.
- T-D04-GREEN: Quick Action cards share KPI card surface treatment (`var(--color-surface-elevated)` + `var(--elevation-1)` + hairline + `var(--radius-card-lg)`).
- T-D05-GREEN: Letter-key shortcut badges (`P`, `N`, `R`, `E`, `B`) removed from Quick Action cards.
- T-D06-GREEN: Empty state uses line-art SVG calendar (24×24, `stroke-width="1.5"`, `var(--color-label-tertiary-label)`) + `<UiButton variant="primary" size="md">Crear nueva cita</UiButton>` + subtle radial gradient backdrop.
- T-D07-GREEN: `font-variant-numeric: tabular-nums` applied to greeting date span.
- T-D08-GREEN: 4 `useSpring({ damping: 1.0, response: 0.35 })` instances added to `DashboardPage.vue` for greeting block, KPI grid, Quick Actions, Citas de Hoy. Stagger attach: 0/60/120/180 ms.
- T-D09-GREEN: Em-dash audit on `DashboardPage.vue` template text — zero `—` characters found, no edit required.

### Phase 2: VERIFY — visual + computed-style (deferred to verify-report.md)

### Phase 3: COMMIT

- T-D-COMMIT: feat commit recorded as `0cb1acf` (combined with login per user direction).

### Phase 4: HOUSEKEEPING (this file)

- T-D-HOUSEKEEP: this `apply-progress.md` written.

---

## Cross-PR

### Files changed (single feat commit `0cb1acf`)

| File | Action | Lines (approx) |
|---|---|---|
| `resources/js/modules/auth/LoginPage.vue` | modified | ~120 |
| `resources/js/modules/dashboard/DashboardPage.vue` | modified | ~210 |
| `resources/js/components/ui/Button.vue` | modified | ~30 |
| `resources/js/components/ui/Card.vue` | modified | ~20 |
| `resources/js/components/layout/AppLayout.vue` | modified | ~25 |
| `public/images/ui/login-hero.jpg` | deleted | n/a |
| `tests/Feature/Ui/*Hotfix*.php` (17 files) | new | ~1100 |
| `openspec/changes/ui-rollout-hotfix-premium-2026-08/screenshots/*` (5 files) | new | binary |

### Chain strategy

`stacked-to-main` (matches the vertical slice + Lote 1 precedent). No tracker PR.

### Co-authored-by

Omitted per global CLAUDE.md rule and HOTFIX-CROSS-006.

### Risk registry status

| Risk | Mitigation in place |
|---|---|
| Brand mark change | "OdontoSuite" wordmark remains visible |
| Hero image replacement | SVG composition (no image-gen dependency) |
| Sidebar eyebrow removal | 1px `var(--color-hairline)` dividers between sections |
| KPI icon removal | 4px accent dot top-right |
| Empty state illustration | Line-art SVG (clean placeholder) |

---

## Next

`chore(sdd)` commit for apply-progress housekeeping, then `sdd-verify` for the 17/17 GREEN confirmation and Playwright manual review.
