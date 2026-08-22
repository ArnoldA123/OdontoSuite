# Tasks: ui-rollout-hotfix-premium-2026-08

> 2 chained PRs (`PR-hotfix-login`, `PR-hotfix-dashboard`), strict TDD ordering.
> Every task: RED (failing test) → GREEN (implementation) → VERIFY (Playwright visual + computed-style assertion) → COMMIT (conventional, no AI attribution).
> Each PR ≤ review budget (login 280 lines, dashboard 380 lines).

---

## PR-hotfix-login (≤ 280 lines)

### Phase 0: RED — write failing tests first

- **T-L01-RED**: Write `tests/Feature/Ui/HotfixLoginBrandMarkTest.php`. Asserts: header contains "OdontoSuite" text, contains `<svg stroke-width="1.75">`, does NOT contain `<img src="easy_dent.png">`. **Run: FAIL (RED).**
- **T-L02-RED**: Write `tests/Feature/Ui/HotfixLoginHeadlineTrackingTest.php`. Asserts computed `letter-spacing` of `<h1 id="login-headline">` ≤ `-0.025em × font-size` at 1440 viewport. **Run: FAIL.**
- **T-L03-RED**: Write `tests/Feature/Ui/HotfixLoginSubtitleTest.php`. Asserts computed `font-size` of `.welcome-subtitle` ≥ 16px. **Run: FAIL.**
- **T-L04-RED**: Write `tests/Unit/DesignSystem/ButtonConstructionTest.php`. Asserts `<UiButton variant="primary">` renders `box-shadow` containing `var(--elevation-3)` AND `inset 0 1px 0 rgba(255, 255, 255, X)` where X ≥ 0.25. **Run: FAIL.**
- **T-L05-RED**: Write `tests/Feature/Ui/HotfixLoginCardSurfaceTest.php`. Asserts `<Card>` in LoginPage does NOT have `variant="glass"`, computes `box-shadow` containing `var(--elevation-2)`. **Run: FAIL.**
- **T-L06-RED**: Write `tests/Feature/Ui/HotfixLoginHeroSourceTest.php`. Asserts hero column does NOT contain `<img src="...login-hero.jpg">`. **Run: FAIL.**
- **T-L07-RED**: Write `tests/Feature/Ui/HotfixLoginMirrorSpringTest.php`. Asserts two distinct `useSpring` instances on the login page (form + hero), hero spring has `response` between 0.40 and 0.50. **Run: FAIL.**
- **T-L08-RED**: Write `tests/Feature/Ui/HotfixLoginEmDashAuditTest.php`. Asserts zero `—` characters in visible text nodes. **Run: FAIL.**

### Phase 1: GREEN — implement to make tests pass

- **T-L01-GREEN**: In `LoginPage.vue`, replace PNG brand mark with inline SVG glyph (24×24, `stroke-width="1.75"`) + `<p>OdontoSuite</p>` wordmark.
- **T-L02-GREEN**: Update `.welcome-headline` to `text-[clamp(2.25rem,5vw,3rem)] leading-[1.05]` with `letter-spacing: -0.028em`.
- **T-L03-GREEN**: Update `.welcome-subtitle` to `text-base leading-relaxed`.
- **T-L04-GREEN**: In `resources/js/components/ui/Button.vue`, add `box-shadow: var(--elevation-3), inset 0 1px 0 rgba(255, 255, 255, 0.32)` to the primary variant. Add `:active { transform: translateY(1px); }`.
- **T-L05-GREEN**: Change `<Card variant="glass">` → `<Card variant="elevated">` in LoginPage. In `resources/js/components/ui/Card.vue`, ensure `variant="elevated"` renders `box-shadow: var(--elevation-2)` + `border: 1px solid var(--color-hairline)`.
- **T-L06-GREEN**: Replace `public/images/ui/login-hero.jpg` reference with editorial SVG composition: 3-tile bento of small line-art SVGs (tooth, chair, calendar) + large "OdontoSuite" wordmark, on `var(--color-canvas)` background. Keep `var(--radius-card-lg)` corners. Delete `login-hero.jpg` from `public/images/ui/`.
- **T-L07-GREEN**: In `LoginPage.vue`, add a second `useSpring({ response: 0.45, damping: 1.0, cssVar: '--spring-hero-o' })` for the hero column. Attach it on mount alongside the existing form spring.
- **T-L08-GREEN**: Audit all template text in LoginPage.vue for `—`. Replace any with `-` or restructure (already clean expected).

### Phase 2: VERIFY — visual + computed-style

- **T-L-VERIFY-1**: Run `php artisan test --filter=Hotfix`. All 8 tests GREEN.
- **T-L-VERIFY-2**: Playwright at 1440x900 + 390x844. Capture screenshots of /login into `openspec/changes/ui-rollout-hotfix-premium-2026-08/screenshots/login-desktop.png` + `login-mobile.png`.
- **T-L-VERIFY-3**: Playwright with `prefers-reduced-motion: reduce` emulation. Assert no `transform` on form-wrap or hero column.
- **T-L-VERIFY-4**: Playwright with `prefers-reduced-transparency: reduce` emulation. Assert hero overlay is solid (no `linear-gradient`).
- **T-L-VERIFY-5**: Playwright with `prefers-contrast: more` emulation. Assert headline + subtitle + footer link contrast ratios ≥ 7:1 (AAA).

### Phase 3: COMMIT

- **T-L-COMMIT**: Single feat commit on a stacked-to-main branch:
  - `feat(ui): premium hotfix login (HOTFIX-LOGIN-001..009)`
  - Co-authored-by line omitted per global rule.
  - Files: `LoginPage.vue`, `Button.vue`, `Card.vue`, 8 new test files. Public asset delete: `public/images/ui/login-hero.jpg`. New asset: inline SVG in LoginPage.

### Phase 4: HOUSEKEEPING (separate commit)

- **T-L-HOUSEKEEP**: `chore(sdd): record hotfix-login apply progress`. Update `openspec/changes/ui-rollout-hotfix-premium-2026-08/apply-progress.md`.

---

## PR-hotfix-dashboard (≤ 380 lines)

### Phase 0: RED — write failing tests first

- **T-D01-RED**: Write `tests/Feature/Ui/SidebarEyebrowAuditTest.php`. Asserts NO element in `AppLayout.vue` template has `tracking-[0.18em]` or `uppercase tracking-` for section labels. **Run: FAIL.**
- **T-D02-RED**: Write `tests/Feature/Ui/IconInBoxAuditTest.php`. Asserts KPI cards in `DashboardPage.vue` do NOT contain `<div>` or `<span>` with combined `rounded` + `bg-system-gray-*` icon container classes. **Run: FAIL.**
- **T-D03-RED**: Write `tests/Feature/Ui/KpiNumberTabularTest.php`. Asserts computed `font-feature-settings` of each KPI number element contains `"tnum" 1`. **Run: FAIL.**
- **T-D04-RED**: Write `tests/Feature/Ui/HotfixDashboardSurfaceConsistencyTest.php`. Asserts Quick Action cards and KPI cards share `border-radius`, `box-shadow` token reference, surface color. **Run: FAIL.**
- **T-D05-RED**: Write `tests/Feature/Ui/HotfixDashboardQuickActionsTest.php`. Asserts NO Quick Action card contains a single-uppercase-letter shortcut badge. **Run: FAIL.**
- **T-D06-RED**: Write `tests/Feature/Ui/HotfixDashboardEmptyStateTest.php`. Asserts empty state contains SVG with `stroke-width="1.5"` AND a primary button with text `/crear.*cita/i`. **Run: FAIL.**
- **T-D07-RED**: Write `tests/Feature/Ui/HotfixDashboardDateTabularTest.php`. Asserts computed `font-feature-settings` of greeting date includes `"tnum"`. **Run: FAIL.**
- **T-D08-RED**: Write `tests/Feature/Ui/HotfixDashboardStaggerTest.php`. Asserts 4 distinct `useSpring` instances on Dashboard page sections. **Run: FAIL.**
- **T-D09-RED**: Write `tests/Feature/Ui/HotfixDashboardEmDashAuditTest.php`. Asserts zero `—` characters in visible text. **Run: FAIL.**

### Phase 1: GREEN — implement to make tests pass

- **T-D01-GREEN**: In `AppLayout.vue`, remove `text-xs uppercase tracking-[0.18em]` from sidebar section labels ("OPERACIONES", "CONFIGURACIÓN"). Replace with 1px `var(--color-hairline)` horizontal divider between sections. No replacement text label.
- **T-D02-GREEN**: In `DashboardPage.vue`, remove icon-in-rounded-gray-box containers from KPI cards. Replace with subtle 4px accent dot (`var(--color-system-blue-500)`) top-right OR remove entirely. Keep label, number, caption.
- **T-D03-GREEN**: Apply `font-variant-numeric: tabular-nums` (via `font-feature-settings: var(--font-features-tabular-nums)`) to KPI number elements. Use Tailwind `tabular-nums` utility if generated correctly, else scoped CSS.
- **T-D04-GREEN**: Update Quick Actions cards to share KPI card surface (`var(--color-surface-elevated)` + `var(--elevation-1)` + hairline + `var(--radius-card-lg)`).
- **T-D05-GREEN**: Remove letter-key badges (`P`, `N`, `R`, `E`, `B`) from Quick Action cards.
- **T-D06-GREEN**: In Dashboard empty state, replace phone-icon-in-circle with clean line-art SVG calendar (24×24, `stroke-width="1.5"`, `var(--color-label-tertiary-label)`). Add `<UiButton variant="primary" size="md">Crear nueva cita</UiButton>` below. Add subtle radial gradient backdrop.
- **T-D07-GREEN**: Apply `font-variant-numeric: tabular-nums` to the greeting date span.
- **T-D08-GREEN**: Add 4 `useSpring({ damping: 1.0, response: 0.35, cssVar: '--spring-dash-{greeting,kpi,quick,empty}-o' })` instances in `DashboardPage.vue` for greeting block, KPI grid, Quick Actions, Citas de Hoy. Stagger attach on mount: `setTimeout(..., 0/60/120/180)`.
- **T-D09-GREEN**: Audit all visible text in DashboardPage.vue for `—`. Replace any.

### Phase 2: VERIFY — visual + computed-style

- **T-D-VERIFY-1**: Run `php artisan test --filter=Hotfix`. All 9 dashboard tests GREEN.
- **T-D-VERIFY-2**: Playwright at 1440x900 + 390x844. Capture screenshots of /dashboard into `screenshots/dashboard-desktop.png` + `dashboard-mobile.png`.
- **T-D-VERIFY-3**: Playwright `:hover` simulation on a KPI card. Assert computed `transform: translateY(-1px)` AND `box-shadow` references `var(--elevation-2)`.
- **T-D-VERIFY-4**: Playwright with `prefers-reduced-motion: reduce` emulation. Assert no transforms on any of the 4 staggered sections.
- **T-D-VERIFY-5**: Playwright with `prefers-contrast: more` emulation. Assert greeting + KPI label + date contrast ≥ 7:1.

### Phase 3: COMMIT

- **T-D-COMMIT**: Single feat commit on a stacked-to-main branch:
  - `feat(ui): premium hotfix dashboard (HOTFIX-DASH-001..011)`
  - Co-authored-by line omitted per global rule.
  - Files: `AppLayout.vue`, `DashboardPage.vue`, 9 new test files.

### Phase 4: HOUSEKEEPING (separate commit)

- **T-D-HOUSEKEEP**: `chore(sdd): record hotfix-dashboard apply progress`. Update `openspec/changes/ui-rollout-hotfix-premium-2026-08/apply-progress.md`.

---

## Final: VERIFY + ARCHIVE

### Phase 5: Cross-PR verification

- **T-X-VERIFY-1**: Run full test suite. `php artisan test --testsuite=Unit` + `--filter=Hotfix` all GREEN.
- **T-X-VERIFY-2**: Side-by-side Playwright screenshots: before (`.playwright-cli/login-current.png`, `.playwright-cli/dashboard-real.png`) vs after (`screenshots/login-desktop.png`, `screenshots/dashboard-desktop.png`). Manual review against design-taste §14 Pre-Flight Check.
- **T-X-VERIFY-3**: Manual review against apple-design §1, §4, §7, §12, §14, §15 checklist. Document any deferred items in verify-report.md.

### Phase 6: Archive

- **T-X-ARCHIVE**: Move `openspec/changes/ui-rollout-hotfix-premium-2026-08/` to `openspec/changes/archive/2026-08-22-ui-rollout-hotfix-premium-2026-08/`. Write `archive-report.md` summarizing the 18 MUST rows, 17 tests, and 2 PRs.

---

## Ultracode orchestration (apply phase)

Per user direction, the apply phase uses the `Workflow` tool with multi-agent orchestration:

- **Scan agent (parallel)**: One agent per MUST row cluster (Login cluster A, Login cluster B, Dashboard cluster A, Dashboard cluster B). Each reads spec.md + relevant files, returns exact edit locations.
- **RED agent (parallel)**: One agent per test file. Writes the failing test, runs `php artisan test --filter=Hotfix` and confirms RED.
- **GREEN agent (parallel, isolated in worktree)**: One agent per Phase 1 task. Implements the change, runs the corresponding test, confirms GREEN. Worktree isolation prevents parallel writers from clashing.
- **VERIFY agent (parallel)**: One agent per Playwright check. Captures screenshots, runs computed-style assertions, returns pass/fail.
- **COMMIT agent**: One agent per PR. Stages exactly the reviewed paths, validates pre-commit, conventional commit, returns commit SHA.

**Total**: ~5-6 agents × 2 PRs = ~10-12 orchestrated agents. Single Workflow invocation, multi-phase.

---

## Risk registry (inherited from proposal §9)

1. Brand mark change → mitigated by keeping "OdontoSuite" wordmark visible.
2. Hero image replacement → mitigated by SVG composition (no image-gen dependency).
3. Sidebar eyebrow removal → mitigated by hairline dividers between sections.
4. KPI icon removal → mitigated by 4px accent dot top-right.
5. Empty state illustration → mitigated by line-art SVG (clean placeholder).
