# Spec: Login Refinement — Bundle Budget

Change: `ui-login-refinement-dental-split-2026-09`

## B1 — Zero new dependencies

- `package.json` MUST NOT change in this slice.
- `pnpm-lock.yaml` MUST NOT change in this slice.
- No new `@vueuse/*`, no new `motion`, no new `gsap`, no new anything.

## B2 — Zero new tokens

- `resources/js/design-system/tokens.js` MUST NOT change in this slice.
- `tailwind.config.js` MUST NOT change in this slice.
- `resources/css/tokens.generated.css` MUST be regenerated (no semantic changes; only parity verification).

## B3 — Build success

- `pnpm build` MUST exit 0.
- Bundle delta vs main SHOULD be ≤ 0 kB gzip (composition only — measured at -X.X kB if anything).
- No Vite warnings about missing chunks or unresolved imports.

## Acceptance criteria

- AC1: `git diff main -- package.json pnpm-lock.yaml resources/js/design-system/tokens.js tailwind.config.js` returns empty.
- AC2: `pnpm build` exits 0.
- AC3: The `app-*.js` chunk size is within ±1 kB gzip of main.
