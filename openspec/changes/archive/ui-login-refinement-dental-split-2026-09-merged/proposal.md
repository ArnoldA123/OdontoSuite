# Proposal: Login Refinement — Dental Editorial Split

Change: `ui-login-refinement-dental-split-2026-09`
Phase: proposal
Date: 2026-09-08

---

## Intent

The current login is functionally complete (PR1+PR2 of `ui-login-premium-motion-2026-08`): 6 microinteractions, glassmorphism, headline with optical sizing, brand glyph morph. It is **compositionally a single-column form on a translucent surface** — readable, but visually flat and underwhelming for a clinical admin product that competes for trust in the first second of use.

This change re-shapes the login into an **editorial split card**: an outer card (radius 32px, soft shadow, hairline) wraps a 45/55 form/image split. The right column carries a Pexels dental still with 3 floating UI overlays (stats, today's appointments, professionals) populated from real seeder data. The submit button and the form Card become polymorphic — the same piece, multiple identities across the auth cycle — and the footer closes the wayfinding loop with Terms and a "no account" path. The 6 existing microinteractions are preserved. The 7th (polymorphism) is added because the user requested it and because the auth lifecycle genuinely has distinct states worth distinguishing. **No new tokens. No new dependencies. No new primitives.** Everything fits inside the existing iOS clinical design system.

## Scope

### IN SCOPE

| # | Feature | Mechanism |
|---|---|---|
| 1 | **Outer card shell** | `<div class="login-page-shell">` + `<div class="login-split-card">` with `rounded-[32px]`, `box-shadow: 0 24px 64px rgba(0,0,0,0.08)`, hairline. `bg: #ffffff`. Wraps the existing 2-column grid. Mobile: full-width, radius 0. |
| 2 | **Right column hero image** | `<img src="/images/pexels/auth/login/6812463_modern-dental_p2.jpg" loading="lazy" decoding="async" alt="Interior de una clínica dental moderna">` filling the right column. `object-cover`. `aspect-ratio: 4/3` reserved on the wrapper so layout never collapses while loading. |
| 3 | **Floating overlay cards** | 3 absolutely-positioned `<Card variant="glass" compact>` overlays on the hero. Each card fetches real data on mount: `/api/dashboard/stats`, `/api/dashboard/appointments-today?per_page=3`, `/api/users/active`. Graceful empty-state per card. Stagger entrance via `v-motion` initial/enter. |
| 4 | **Footer row** | Below the form Card, inside the outer card: "Términos y Condiciones" + "¿No tienes cuenta? Contacta al administrador" links. Subtle, no chrome. |
| 5 | **Submit button polymorphism** | New `useShapeMorph({ states: ['idle','validating','authenticating','success','error'], initial: 'idle' })` composable. Submit renders one `<UiButton>` with 5 inner `<span>` branches (one per state) controlled by `v-show`. Crossfade via `v-motion` enter/leave. State machine guards `validating → authenticating` only after 200ms minimum, `→ success` only on API 2xx, `→ error` on 4xx/5xx. |
| 6 | **Form Card polymorphism (form ↔ mini-summary)** | On `success` state, the existing `<form>` crossfades to a `<MiniSummary>` block: avatar (initials from user.name), display name, role label, "Ir al dashboard" button. `router.push('/dashboard')` fires 600ms after the crossfade starts. The Multi-stage loading block (currently below the Card) is removed in favor of the polymorphic button. |
| 7 | **Reduced-motion + reduced-transparency** | `useShapeMorph` collapses every transition to opacity-only under `prefers-reduced-motion: reduce`. Outer card flattens to `bg: var(--color-system-background)` under `prefers-reduced-transparency: reduce`. |
| 8 | **Source-inspection tests + pure-math tests** | 6 new `LoginPageRenderTest` assertions + 6 `ShapeMorphTest` pure-math cases. |

### OUT OF SCOPE

- New design tokens. The outer card uses `rounded-[32px]` + an ad-hoc `box-shadow` (per AGENTS.md: ad-hoc visual values for one-screen deliverables are acceptable; promote to a token only when reused in 3+ surfaces).
- New ui/ primitives. `Button.vue`, `Card.vue`, `Sheet.vue` are untouched. The submit uses `<UiButton>` as today; the polymorphic content lives in slotted `<span>` children.
- OAuth row. OdontoSuite does not have OAuth providers; adding Apple/Google buttons would be theatre.
- Sound, haptics, parallax, confetti.
- Dark mode. Project is light-only by decision (Decision 6, `ui-refresh-apple-clinical-2026-08`).
- Backend changes. Zero new endpoints. The three overlay fetches already exist.
- Top-right "X" close affordance. That is a modal affordance; the login is a full page, not a modal.

## Approach

Single PR, stacked-to-main, no chained PRs (user explicit choice despite the 400-line budget). Strict TDD at per-task granularity. Two new composable files, one LoginPage rewrite, two test files. Bundle delta target: 0 kB (composition only — no new dependencies).

Forecast:
- LoginPage.vue: +350 net
- useShapeMorph.js: +80
- shapeMorphMath.js: +50
- ShapeMorphTest.php: +200 (PHPUnit docblock weight)
- LoginPageRenderTest.php: +6 cases ≈ +60

Total net: ~740 lines (of which ~400 are test docblocks). Login + composables = ~480 lines, above the 400-line guideline. **User-approved exception**, recorded as deviation D1 in `apply-progress.md`.

## Capabilities

### New
- `login-editorial-split`: outer card + hero image + 3 floating overlays + footer.
- `login-shape-morph`: polymorphic submit + polymorphic form Card.

### Modified
- `auth/login-page`: visual composition (split card, hero, overlays, footer) + polymorphic state machine.

## Risks and Rollback

| Risk | Severity | Mitigation |
|---|---|---|
| 480 net lines exceeds 400-line budget | High | User-approved exception; D1 deviation. |
| Image asset not committed | High | `git ls-files` test gate; fallback to a static SVG placeholder. |
| Overlay API returns 401 (no token) on login page | High | `useApi` already handles 401; overlays fetch but ignore failures (try/catch + empty state). |
| Overlay API returns 200 with empty arrays (seeders not run) | Medium | Each card has its own empty-state: "Sin datos para mostrar" copy. |
| Submit state machine races with API response | Medium | State machine validates that `validating` has held ≥200ms before allowing `authenticating`. `success` only after API 2xx; `error` only after API 4xx/5xx. Cancelled transitions return to `idle`. |
| Form Card polymorphism hides the form too quickly for the user to read the success | Low | 600ms dwell on `success` state before `router.push`. The mini-summary button is an alternative path. |
| Outer card radius 32px + split grid breaks on iOS Safari < 16 | Low | `rounded-[32px]` is plain CSS; no iOS-specific failure mode. |
| `v-motion` directive inside a `v-for` (overlay cards stagger) | Low | Each overlay card mounts independently with its own `v-motion` initial/enter. |

**Rollback**: `git revert <sha>` restores the entire change in one step. The 3 overlay endpoints already exist; reverting leaves them untouched. The polymorphic submit falls back to the single-state button.

## Success Criteria

- [ ] `vendor/bin/phpunit tests/Unit/Composables/ShapeMorphTest.php` → 6/6 GREEN.
- [ ] `vendor/bin/phpunit tests/Unit/DesignSystem/LoginPageRenderTest.php` → existing 18 + 6 new = 24/24 GREEN.
- [ ] `php artisan test --testsuite=Unit` regression check (existing baseline of failures untouched).
- [ ] `pnpm build` succeeds.
- [ ] `pnpm lint:check` succeeds (or the lint baseline is the same as main).
- [ ] `git ls-files public/images/pexels/auth/login/6812463_modern-dental_p2.jpg` returns 1 line.
- [ ] `grep -rE "#[0-9a-fA-F]{6}" resources/js/modules/auth/LoginPage.vue` returns 0 lines (no new hand-written hex literals).
- [ ] Playwright manual sweep at 1440x900 + 390x844 confirms:
  - Outer card visible, split 45/55 desktop, single column mobile.
  - 3 overlay cards visible with real data (or graceful empty state).
  - Submit cycles idle → validating → authenticating → success → /dashboard.
  - Failure path: shake + revert to idle.
  - Form Card on success: crossfade to mini-summary.
  - `prefers-reduced-motion: reduce`: every transition collapses to opacity-only.
  - `prefers-reduced-transparency: reduce`: outer card flattens.
- [ ] Bundle delta: 0 kB (composition only).
