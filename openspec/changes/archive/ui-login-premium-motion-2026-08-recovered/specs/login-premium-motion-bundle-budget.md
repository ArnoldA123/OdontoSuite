# Spec: login-premium-motion-bundle-budget

## Summary
The new dependency `@vueuse/motion` adds ≤12kb gzipped to the production build. The plugin MUST be registered in `resources/js/app.js`. If the budget is exceeded, the change MUST NOT ship until tree-shaking is verified or the dependency is downgraded.

## Source-of-truth references
- `@vueuse/motion` v3.0.3 npm page (~10kb gzipped claim; we budget 12kb to leave room for variants/easing code we use)
- AGENTS.md §2 (no new deps without justification)

## Scenarios

### B1 — Bundle size budget
**Given** the production build is run (`pnpm build`),
**Then** the gzip size of the JavaScript bundle MUST grow by ≤12kb versus the pre-change baseline.

### B2 — Plugin registration is present
**Given** `resources/js/app.js` is committed,
**Then** the file MUST import `@vueuse/motion` (or specifically `MotionPlugin`) AND call `app.use(MotionPlugin)` (or equivalent named import).

### B3 — Plugin registration is testable
**Given** a CI PHPUnit source-inspection test exists,
**Then** it MUST assert `resources/js/app.js` contains the `MotionPlugin` registration string.

### B4 — Budget exceeded → block
**Given** `pnpm build` produces a bundle that exceeds the +12kb budget,
**Then** the change MUST NOT ship,
**And** the executor MUST either:
  - verify tree-shaking by importing only the named exports actually used,
  - downgrade to an earlier `@vueuse/motion` version,
  - or replace the dependency with the vanilla Web Animations API + `useSpring2D`.

## Acceptance criteria
- AC1: A CI PHPUnit source-inspection test (`tests/Unit/DesignSystem/AppShellTest.php` or new file) greps `resources/js/app.js` for the `MotionPlugin` registration.
- AC2: `pnpm build` succeeds; the resulting `dist/` JS files' gzipped total is ≤ baseline + 12kb (manual check at apply time; documented in the apply progress report).
- AC3: `package.json` lists `@vueuse/motion` with the exact pinned version (no caret range).
