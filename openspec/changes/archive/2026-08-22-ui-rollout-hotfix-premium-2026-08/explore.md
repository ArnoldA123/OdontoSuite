# Explore: ui-rollout-hotfix-premium-2026-08

**Change**: ui-rollout-hotfix-premium-2026-08 (SDD phase: explore). OdontoSuite Laravel 12 + Vue 3.3 SPA.

**Intent**: Close the gap between TOKEN COMPLIANCE and APPLE PREMIUM FEEL on the two flagship screens (Login + Dashboard) before resuming the all-modules rollout. Vertical slice `ui-premium-microdetail-2026-08` (archived 2026-08-11) shipped correct tokens (systemBlue ramp, hairline rgba(60,60,67,0.12), elevation rungs rgba(60,60,67,α), focus-ring, motion.duration fast/normal/slow, tabular-nums) and 10 primitives, but the rendered pages still read "Bootstrap/Material" not "iOS" — flat buttons, generic icon-in-box KPI tiles, uppercase eyebrow labels (banned by design-taste-frontend §9.F), stock hero photo on login, plain empty state on dashboard.

## Visual audit (Playwright @ 1440x900)

Logged in as `ever` (administrador) on `http://127.0.0.1:8765/`.

### Login (resources/js/modules/auth/LoginPage.vue)

| Surface | Current state | Apple-premium target |
|---|---|---|
| Brand mark | Blue circle `var(--color-terracotta-500)` (= #007aff) with inverted PNG of `easy_dent.png`. Reads as generic favicon. | Typography-led brand wordmark + small monogram glyph, or remove brand mark entirely. Apple.com sign-in has NO logo — just "Apple ID" wordmark in SF Pro. |
| Headline | "Gestiona tu clínica con calma" at `text-3xl sm:text-4xl font-medium leading-[1.1]` with `letter-spacing: -0.022em`. | `text-[clamp(2.5rem,5vw,3.5rem)]` with proper optical sizing; tracking `-0.025em` to `-0.03em` at display scale; tighter leading (`1.05`). |
| Subtitle | `text-sm leading-relaxed` at `var(--color-label-secondary-label)`. | Increase to `text-base` or `text-lg`; consider a second line of supporting copy. |
| Form card | `<Card variant="glass">` but the glass variant on a white canvas page renders nearly identical to a flat surface — no perceptible blur or translucency. | Glass variant only works on canvas-with-content-behind. On solid `var(--color-canvas)` use `elevation-2` + 1px hairline + soft inset highlight at top edge to read as "lifted". |
| Inputs | `var(--color-hairline)` border, focus shadow `0 0 0 3px var(--color-accent-light)`. Focus state is correct. Hover state lifts to tertiary label. | Keep focus; consider adding a subtle `prefers-reduced-transparency` override that removes blur. |
| Submit button | `<UiButton variant="primary" size="lg" :full-width="true">` — flat solid blue rectangle, NO elevation, NO inset highlight, NO tinted shadow. | Apple primary CTA: `box-shadow: var(--elevation-3), inset 0 1px 0 rgba(255,255,255,0.32), 0 0 0 1px rgba(0,0,0,0.04);` per the tokens.js elevation rungs + a tinted shadow that matches Apple's blue-glow on the primary key. Add `:active` translate-y(1px) and `:disabled` desaturate. |
| Hero column | Dental chair photo `public/images/ui/login-hero.jpg` with 180deg gradient overlay. Caption "OdontoSuite / Una consola clínica sobria, hecha para el consultorio." | Hero is a generic stock image. Apple-premium replacement options: (a) editorial SVG composition (abstract dental silhouette + serif/sans pairing), (b) tasteful gradient with the OdontoSuite wordmark large, (c) generated image via image-gen tool with the right mood. **Hard ban: another stock dental photo.** |
| Entrance spring | `useSpring` with `response: 0.35, damping: 1.0` on the form-wrap. Already correct. | Keep. Possibly add a second spring for the hero column with a `response: 0.45` (slower right column, faster left, mirror iOS sheet reveal). |
| Reduced-motion | `@media (prefers-reduced-motion: reduce) { .login-form-wrap { transform: none !important; transition: none !important; } }` — kills spring. | Keep. Add `prefers-reduced-transparency: reduce` flatten of hero overlay (already present). |
| High contrast | `@media (prefers-contrast: more)` lifts colors. | Keep. |

### Dashboard (resources/js/modules/dashboard/DashboardPage.vue)

| Surface | Current state | Apple-premium target |
|---|---|---|
| Sidebar | Eyebrow labels in `text-xs uppercase tracking-[0.18em]` for "OPERACIONES" and "CONFIGURACIÓN" — **banned by design-taste-frontend §9.F "Section-Numbering Eyebrows" + "Eyebrow Restraint" rule**. | Remove uppercase tracking. Use plain `text-xs` with `var(--color-label-tertiary-label)` and weight 500. ONE eyebrow per 3 sections at most. |
| Page header | "Dashboard" + "Resumen general del sistema" — clean. | Keep; verify optical sizing matches login headline. |
| Greeting block | "Buenas noches, Ever" + "viernes, 21 de agosto de 2026" — date is in a non-tabular style (es long-form). | Date should be tabular (`font-variant-numeric: tabular-nums` on the date — token exists). Greeting could be larger and more typographic. |
| KPI cards (5) | Each card: uppercase label (`text-xs uppercase tracking-[0.18em]`), large number (`font-medium`), small caption, **icon-in-rounded-gray-box** at right. The icon-in-box pattern is the **single biggest Material-leak** in the app — reads like Material 3 / Bootstrap, not Apple. | Remove the icon-in-box. Apple would put a small subtle glyph top-right or no glyph at all. The number should be `font-variant-numeric: tabular-nums` (token exists). Card surface should be `var(--color-surface-elevated)` (#ffffff) with `box-shadow: var(--elevation-1)` (subtle). Add `transition: transform 200ms ease, box-shadow 200ms ease` and `:hover { transform: translateY(-1px); box-shadow: var(--elevation-2); }`. |
| Estado de Caja badge | "● Abierta" green pill. Functional. | Keep; ensure systemGreen-50/700 contrast ≥ 4.5:1. |
| Quick Actions | 5 cards in 3-col grid. Each: icon-in-rounded-gray-box + title + subtitle + **letter-key shortcut in a separate small badge** ("P", "N", "R", "E", "B"). The letter-key badges are the second Material-leak — looks like keyboard-shortcut reference, not navigation. | Remove the letter-key badges entirely (or move to `<kbd>` element in a hidden help tooltip). Cards: same surface treatment as KPI cards. |
| "Citas de Hoy" empty state | Large phone icon in a circle + "Sin citas para hoy" + 1-line caption. Functional but minimal. | Apple-premium empty state: either a small SVG illustration (clean line-art of a calendar), or a softer icon treatment, plus a primary CTA "Crear nueva cita" button. Add subtle radial-gradient backdrop. |
| Recent activity | Below the fold — could not audit in this pass. | Audit in design phase. |
| Entrance spring | Per-card or per-section springs? Need to check. | Add per-section springs on the page; stagger entrance so the greeting lands first, KPIs cascade, then Quick Actions, then empty state. |
| Reduced-motion + High contrast | Need to verify present. | Audit in design phase. |

## What this hotfix is NOT

- NOT changing tokens (tokens.js stays frozen).
- NOT adding primitives (10 primitives stay frozen; Card `variant="glass"` is the only one in scope for the login card surface).
- NOT touching the dashboard KPI data shape (no new API fields).
- NOT touching auth flow, route names, or the 17 un-migrated modules.
- NOT touching 404 (excluded by user direction).
- NOT dark mode (still light-only by design).
- NOT the sidebar's overall structure (icons + labels stay; only the eyebrow uppercase-tracking labels are dropped).

## What this hotfix IS

- Login + Dashboard only.
- Composition-level redesign (typography, button construction, icon treatment, eyebrow removal, hero editorial).
- Each change MUST have a corresponding test that pins the RULE (no example-pin defect from previous sessions, see `obs-a155ea80fcc1834d`).
- Visual verification via Playwright at 1440x900 and 390x844 (mobile).
- Per PR ≤ 400 lines (chained-pr skill budget).

## Recommended slice shape (2 chained PRs)

1. **PR-hotfix-login** (~250 lines): brand mark → typography, headline optical sizing, button construction (elevation + inset highlight + tinted shadow), form card lift, hero image editorial replacement (SVG or generated).
2. **PR-hotfix-dashboard** (~350 lines): sidebar uppercase eyebrow removal, KPI icon-in-box removal + tabular-nums + hover-lift, Quick Actions letter-key badge removal, empty state illustration + CTA, per-section staggered spring entrance.

Total ~600 lines. Both under the 400-line review budget when measured individually.

## Top risks

1. **Brand mark change**: removing the brand mark entirely or replacing with typography may be perceived as "lost branding". Mitigation: keep `OdontoSuite` wordmark visible; the small monogram is decorative, not load-bearing.
2. **Hero image replacement**: generating an editorial hero is a taste call. Mitigation: provide 3 candidate compositions (SVG gradient + wordmark, generated mood image, abstract pattern) in the spec for user pick.
3. **Sidebar eyebrow removal**: may leave the sidebar feeling like an undifferentiated list. Mitigation: replace with subtle section divider hairlines (1px `var(--color-hairline)` between groups, no text label).
4. **KPI card icon removal**: numbers alone may feel sparse. Mitigation: a single subtle 1px hairline at top of card OR a small accent dot (`var(--color-system-blue-500)` 4px circle) to anchor visual interest.
5. **Empty state illustration**: not having a real illustration is the historical default. Mitigation: ship a clean line-art SVG of a calendar/clock as the placeholder, with a comment marking it as illustrative.

## Open questions for sdd-propose

1. Hero replacement direction: (a) typography-only (no image), (b) generated image via tool, (c) abstract gradient SVG, (d) keep photo + better caption?
2. Brand mark: (a) keep current circle + icon, (b) replace with small monogram + wordmark only, (c) wordmark only (no glyph)?
3. Sidebar section dividers: hairlines, or thin colored dot, or no separator?
4. Empty state CTA: "Crear nueva cita" inline button, or link to calendar page?
5. KPI card hover: lift transform, or color tint, or both?
6. Per-section staggered springs: required for dashboard, optional for login?

## Deliverables

- File: `openspec/changes/ui-rollout-hotfix-premium-2026-08/explore.md` (this).
- Next: `sdd-propose` writes `proposal.md` resolving OQ#1-6 above.
- Verify at apply + verify phases via Playwright @ 1440x900 + 390x844, capture before/after screenshots in `screenshots/` for the verify-report.
