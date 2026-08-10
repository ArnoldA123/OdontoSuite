# Slice 06 — UX Visual Tokens

> Findings: UXV-001..014 (14 visual-token fixes)
> Cluster: visual-tokens
> LOC est: ~180 · Budget risk: Low · Depends on: —
> Spec: [../specs/06-ux-visual-tokens.md](../specs/06-ux-visual-tokens.md)

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

## Acceptance Criteria

- `resources/js/design-system/tokens.js` exists and exports colors, spacing, radius, typography, shadow, breakpoint.
- `tailwind.config.js` palette re-sourced from tokens.js (single source of truth).
- 14 components stop using hard-coded hex values.
- New states: warning / danger / info / success palettes (50/100/300/500/700/900).
- `/opacity` variants (`border-theme-soft`, `bg-theme-surface-translucent`) exposed.
- `info`/`neutral`/`motion`/`glass`/`ease` tokens audited: declared-only tokens either documented as public API or pruned.
- `LoginPage` brand unifies to `OdontoSuite`.
- `pnpm lint:check && pnpm build` green.
- ESLint rule `no-hardcoded-colors` blocks hex literals outside tokens.

## Tasks

- [x] **T-06.1** Create `resources/js/design-system/tokens.js` exporting object with colors, spacing, radius, typography, shadow, breakpoint. Description: Single source of truth. Files: `resources/js/design-system/tokens.js` (new). AC: `node -e "import('./resources/js/design-system/tokens.js').then(m => console.log(typeof m.colors))"` prints `object`. Estimated LOC: ~120. Depends on: —. Parallelizable: no (foundation).
- [x] **T-06.2** Define `/opacity` variants in tokens (e.g., `border-theme-soft`, `bg-theme-surface-translucent`) or alias `bg-theme-surface/80`. Description: Tailwind 3 supports `/<opacity>` modifier natively. Files: `resources/js/design-system/tokens.js`. AC: `pnpm build` green; visual diff shows correct alpha. Estimated LOC: ~10. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-06.3** Replace `bg-green-600/700`, `bg-red-600/700`, hardcoded green/red → `bg-success-500/600`, `bg-error-500/600`. Description: 14 components. Files: across `resources/js/components/**/*.vue`. AC: `grep -rn "bg-green-600\|bg-red-600" resources/js/components/` returns 0; `pnpm build` green. Estimated LOC: ~30. Depends on: T-06.1. Parallelizable: yes (per-component).
- [x] **T-06.4** Backdrop modal: remove duplicate `bg-black`; keep only `bg-black/50`. Description: Visual regression fix. Files: `resources/js/components/ui/UiModal.vue`. AC: visual diff shows single backdrop layer. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-06.5** Unify brand in `LoginPage.vue` (`EasyDent` → `OdontoSuite`). Description: Copy fix. Files: `resources/js/pages/LoginPage.vue`. AC: `grep "EasyDent" resources/js/pages/LoginPage.vue` returns 0. Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-06.6** Replace `text-success-text`/`text-warning-text` → `text-success-700`, `text-warning-700`. Description: Token conformance. Files: across components. AC: grep returns 0. Estimated LOC: ~10. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-06.7** Status badges: `bg-{color}-100/800` → tokens (`bg-success-50/700`, `bg-warning-50/700`). Description: Same conformance sweep. Files: across components. AC: grep returns 0. Estimated LOC: ~15. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-06.8** Define `--color-primary-light` in `resources/css/app.css` (or replace usages). Description: Token resolution. Files: `resources/css/app.css`. AC: grep `--color-primary-light` resolves; visual diff correct. Estimated LOC: ~5. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-06.9** Audit `info`/`neutral` declared-but-unused tokens → either podar or document as public API. Description: Token surface cleanup. Files: `tailwind.config.js`, `tokens.js`. AC: documentation comment per token OR deletion. Estimated LOC: ~10. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-06.10** Audit `motion`/`glass`/`ease` declared-but-unused tokens → same. Description: Same. Files: same. AC: documented OR pruned. Estimated LOC: ~10. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-06.11** Replace native HTML `<select>` with `UiSelect` in `PatientsPage.vue`, `AiAnalysisPage.vue`. Description: A11y + token consistency. Files: pages. AC: `grep -n "<select" resources/js/pages/PatientsPage.vue resources/js/pages/AiAnalysisPage.vue` returns 0. Estimated LOC: ~20. Depends on: T-06.3. Parallelizable: yes.
- [x] **T-06.12** Re-source `tailwind.config.js` palette from `tokens.js` import (Vite alias). Description: Cascade guarantee. Files: `tailwind.config.js`. AC: `pnpm build` green; `grep "colors:" tailwind.config.js` shows import. Estimated LOC: ~30. Depends on: T-06.1..T-06.11. Parallelizable: no.
- [x] **T-06.13** ESLint rule `no-hardcoded-colors` blocks hex literals outside `tokens.js`. Description: Custom rule. Files: `eslint.config.js`, `.eslint-rules/no-hardcoded-colors.js` (new). AC: `pnpm lint:check` fails on a test fixture with hex literal. Estimated LOC: ~25. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-06.14** Write `tests/visual/tokens.smoke.mjs` and snapshot `tests/visual/tokens.test.mjs`. Description: Visual regression. Files: `tests/visual/tokens.smoke.mjs`, `tokens.test.mjs`. AC: snapshot stable. Estimated LOC: ~30. Depends on: T-06.12. Parallelizable: no.
- [x] **T-06.15** Update `AGENTS.md` reference from broken `tokens.js` link to actual path. Description: Doc sync. Files: `AGENTS.md`. AC: link checker green. Estimated LOC: ~3. Depends on: T-06.1. Parallelizable: yes.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| `tokens.js` palette diverges from `tailwind.config.js` | Snapshot test + `pnpm build` gate |
| `/opacity` Tailwind modifier misfires | Manual visual diff per component |
| ESLint rule too aggressive | Allow `transparent` + `currentColor` + named CSS colors |
| `LoginPage` brand unification has downstream copy | grep whole repo for `EasyDent`; coordinate copy |
