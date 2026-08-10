# Delta for Visual Tokens — Slice 06

Resolves 14 visual-token findings (UXV-001..014). Recreates the missing `resources/js/design-system/tokens.js` (the file linked from `AGENTS.md` does not exist), and replaces hard-coded hex values across components with token references.

## ADDED Requirements

### Requirement: tokens.js Exists and Exports Canonical Tokens

The system MUST publish `resources/js/design-system/tokens.js` exporting `colors`, `spacing`, `radius`, `typography`, `shadow`, `breakpoint` objects sourced from `tailwind.config.js`. The file MUST be the single source of truth.

Evidence: `AGENTS.md` references `resources/js/design-system/tokens.js` but the file is absent; components hard-code hex (`#1A73E8`, `#34A853`, etc.).

#### Scenario: file present and importable

- WHEN `import tokens from '@/design-system/tokens.js'` runs
- THEN the module exports `tokens.colors.primary` and other keys

#### Scenario: parity with tailwind.config

- WHEN tokens are diffed against `tailwind.config.js` palette
- THEN no mismatched hex values exist

Test obligation: Vitest (or simple Node import smoke test) + visual regression diff.

---

### Requirement: Component Color References Use Tokens

The system MUST replace hard-coded hex literals in component templates and `<style>` blocks with token references (`text-primary`, `bg-success-50`, etc., backed by `tokens.colors.*`).

Evidence: 14 components contained hex literals outside the token palette.

#### Scenario: no hard-coded hex in components

- WHEN `grep -nE "#[0-9A-Fa-f]{3,8}" resources/js/components/` runs (excluding tokens.js)
- THEN only opacity-disabled states and a documented legacy list remain

Test obligation: Static lint rule + manual review.

---

### Requirement: Token Coverage Includes New States

The system MUST add `colors.warning`, `colors.danger`, `colors.info`, `colors.success` palettes (50/100/300/500/700/900) to `tokens.js`. Components referencing these states MUST consume the tokens.

#### Scenario: warning token available

- WHEN component reads `tokens.colors.warning[500]`
- THEN the value matches the design palette in `tailwind.config.js`

Test obligation: Unit test importing tokens and snapshotting values.

---

### Requirement: Spacing and Radius Tokens

The system MUST publish `spacing` (0, 1, 2, 4, 6, 8, 12, 16, 24, 32, 48) and `radius` (none, sm, md, lg, full) tokens. Components MUST NOT use raw `px`/`py`/`rounded` shortcuts when a token exists.

#### Scenario: spacing token available

- WHEN component reads `tokens.spacing[4]`
- THEN the value is `1rem` (or `4` per Tailwind scale)

Test obligation: Unit + lint.

---

### Requirement: Tailwind Config Consumes Tokens

`tailwind.config.js` MUST source its palette from `tokens.js` (either via direct import or generated JSON) so any token change cascades to the build.

#### Scenario: build reflects token change

- WHEN `tokens.js` color is changed
- THEN `pnpm build` regenerates CSS with the new value

Test obligation: Build check + visual regression.

---

## MODIFIED Requirements

### Requirement: AGENTS.md Reference Resolves

The reference to `resources/js/design-system/tokens.js` in `AGENTS.md` MUST point to a file that exists. (Previously: broken link.) If the path is renamed, AGENTS.md MUST be updated to match.

#### Scenario: link resolves

- WHEN the file is opened at the documented path
- THEN the tokens module loads

Test obligation: CI doc-link check.

---

## REMOVED Requirements

None for this slice.

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| tokens.js Exists | Node import smoke | `tests/visual/tokens.smoke.mjs` |
| Component Color References | Lint | custom eslint rule |
| Token Coverage New States | Unit | `tests/visual/tokens.test.mjs` |
| Spacing and Radius Tokens | Unit + Lint | `tests/visual/tokens.test.mjs` |
| Tailwind Config Consumes | Build | `pnpm build` |
| AGENTS.md Reference | Doc link check | CI |
