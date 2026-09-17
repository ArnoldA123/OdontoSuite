# Task: CI conformance — make main green and the gates honest

Status: Slice 1 DONE and pushed. Remaining work is tracked as GitHub issues,
one per session-sized unit — see below. Nothing here is a plan for a single
sitting: the diagnosis was bigger than one session, so it was split.

## Tracking

| Issue | Subject |
|---|---|
| #13 | `Setup Node` still fails: pnpm 11 needs Node >= 22.13, the workflow pins Node 20 |
| #14 | ESLint: 330 real errors across 88 files, including two dead and broken components |
| #15 | Four of the five quality gates cannot fail |
| #16 | MySQL suite: 177 failures, all from a literal `APP_KEY` in `ci.yml` |
| #17 | `AGENTS.md` documents CI behaviour that was never true |

The slices below are kept as the reasoning that produced those issues.

## Delivered (Slice 1, commit `b9cf8cf`)

`pnpm/action-setup` added before `setup-node`; `needs: quality` dropped from
`backend-tests` and `frontend-build`; `@vue/eslint-config-prettier` moved last in
`extends` and the duplicated formatting rules deleted (1981 → 330 errors, zero
source churn). The decoupling is what finally ran the MySQL suite and produced
the evidence for #16.
Created: 2026-09-17
Triggered by: the user's requirement that `main` be conformant with best practices

## The measured state of CI (not the documented one)

| Signal | Value |
|---|---|
| Runs in the workflow's lifetime | **56** |
| Runs that succeeded | **0** |
| Current failure point | `Setup Node` in the `Quality gates` job (17-22 s) |
| Earliest runs (2026-06-11, day one) | **also `Setup Node`** — the workflow was born broken |
| `Backend tests (MySQL)` | **has never executed, not once** |
| `Frontend build` | **has never executed, not once** |

`backend-tests` and `frontend-build` both declare `needs: quality`, and `quality`
has failed on every single run, so the MySQL test suite has never run in CI. The
claim in `AGENTS.md` §6 that the suite "passes in CI with MySQL" is therefore
**unverified**: it describes an intention, never an observation.

## Root causes (all measured, one per gate)

1. **`Setup Node` fails.** The workflow uses `actions/setup-node@v4` with
   `cache: 'pnpm'` but **never installs pnpm** — there is no `pnpm/action-setup`
   step. `setup-node` shells out to `pnpm store path` to build the cache key and
   fails with "Unable to locate executable file". This is the current blocker and
   has been since the first commit that introduced the workflow.

2. **ESLint is the next blocker** — and the reason is configuration, not code.
   `.eslintrc.cjs` extends `@vue/eslint-config-prettier` **first**, then
   `plugin:vue/vue3-essential|strongly-recommended|recommended`, which re-enable
   every formatting rule the Prettier config just turned off. `rules` then
   re-enables four more explicitly. ESLint `extends` is last-wins, so the
   Prettier config is being undone. Measured: **1981 errors in 137 files**, of
   which ~1644 (83%) are pure formatting (`vue/html-indent` 1071,
   `vue/max-attributes-per-line` 265, `space-before-function-paren` 113,
   `vue/html-self-closing` 100, `vue/script-indent` 67, `operator-linebreak` 28).
   With the formatting rules off, **337 errors in 90 files** remain — and those
   are real (`no-unused-vars` 161, `no-empty` 77, `vue/no-parsing-error` 12,
   `no-undef` 8, `vue/valid-attribute-name` 8, `vue/prefer-true-attribute-shorthand` 8, ...).

3. **Prettier cannot run at all.** `pnpm format:check` dies with
   `Couldn't resolve parser "php"`: `.prettierrc` declares a `*.php` override
   using the `php` parser, but `@prettier/plugin-php` is not installed. The CI
   step hides this behind `|| echo "Prettier issues found — non-blocking for now"`.

4. **Pint fails** (many files, real style violations) and is also hidden behind
   `|| echo "Pint not configured yet — skipping"`.

5. **The PHP syntax step can never fail either**: it pipes through
   `grep -v "No syntax errors" || true`.

So four of the five "quality gates" are cosmetic: only ESLint can fail, and it
is currently misconfigured. The gates that cannot fail are the same disease this
project has been fighting — a guard that asserts nothing.

## Slices

### Slice 1 — Unblock CI and reconcile the ESLint/Prettier boundary — DONE

- [x] 1.1 `.github/workflows/ci.yml`: added `pnpm/action-setup@v4` before
      `actions/setup-node@v4` in both jobs that use pnpm (`quality`,
      `frontend-build`). The version is read from `packageManager`
      (`pnpm@11.5.0`), so there is no second pin to drift.
- [x] 1.2 `.eslintrc.cjs`: `@vue/eslint-config-prettier` moved to the END of
      `extends` (ESLint resolves last-wins) and the ~35 formatting rules that
      `rules` re-enabled on top of it were deleted. Prettier owns formatting;
      ESLint keeps correctness and naming.
- [x] 1.3 Measured locally: **1981 → 330 errors** (warnings 2368 → 1399) with
      **zero source-file churn**. The change touches configuration only, so it
      cannot alter runtime behaviour.
- [x] 1.4 `.github/workflows/ci.yml`: dropped `needs: quality` from
      `backend-tests` and `frontend-build`. Both declared it, `quality` has never
      passed, so both were skipped on all 56 runs — the reason the MySQL suite
      has never executed. A failing lint gate must not decide whether the tests
      are allowed to report. Verified afterwards with a YAML parse: three jobs,
      `needs` absent from all of them, `pnpm/action-setup` present in the two
      jobs that use pnpm.

### Slice 1 finding — ESLint caught two dead, broken components

Both are imported by NOTHING (verified with a repo-wide grep over `.vue`, `.js`,
`.php` and `.json`, plus the global registration plugin, which registers only
`UiButton`/`UiInput`/`UiCard`/`UiRadioGroup`/`UiSelect`):

| Component | Errors | What is wrong |
|---|---|---|
| `resources/js/components/ui/ToothSelector.vue` | 20 (`vue/no-parsing-error` 12, `vue/valid-attribute-name` 8) | The template carries a duplicated, truncated `:class` binding: `:class="[` immediately followed by `:class="`. A botched edit that survived because nothing imports the file. |
| `resources/js/components/ValidatedForm.vue` | 8 (`no-undef`) | Uses `ref(...)` and `watch(...)` but the script block never imports them from `vue`. It would throw at runtime on first use. |

They are residue of abandoned work, never rendered and never tested — which is
exactly why no test caught them. Resolution belongs to Slice 2, and for dead code
the cheapest correct fix is deletion, not repair.

### Slice 2 — The residual 337 real lint errors (90 files)

Deliberately NOT in Slice 1: it is source-code churn across 90 files and needs
its own review. Requires a user decision on whether to fix all of them or split
further. Note `vue/no-parsing-error` (12) and `no-undef` (8) are potential real
bugs, not style.

### Slice 3 — Make the cosmetic gates honest

- [ ] 3.1 Prettier: either install `@prettier/plugin-php` or drop the `*.php`
      override, then remove the `|| echo` and let the gate fail for real.
- [ ] 3.2 Pint: remove the `|| echo` and fix (or explicitly document) the
      violations.
- [ ] 3.3 PHP syntax: the `grep -v ... || true` makes the step unable to fail;
      make it a real check.
- [ ] 3.4 `AGENTS.md` §6 and §10: replace the CI claims with what CI actually
      does today.

### Slice 4 — After `quality` can pass

`backend-tests` (full suite on MySQL) and `frontend-build` run for the **first
time ever**. Their outcome is unknown and must be observed, not assumed.

## Constraints

- The approved candidate is already committed (`016bcf4`, `d8f8d26`) and pushed,
  so its burned receipt is preserved in history. Everything here is a NEW
  candidate and will need its own review once no further edits are pending.
- Slice 1 changes no application source file, so it cannot affect behaviour.

## Issue #13 — Node version (change applied, observation pending)

Applied 2026-09-17, first run of the issue-driven phase.

`.nvmrc` (new file, content `22`) is now the single source for the Node version;
both jobs that set up Node read it through `node-version-file: '.nvmrc'` instead
of carrying their own literal.

Evidence, measured rather than assumed:

| Claim | Source |
|---|---|
| The floor is `>=22.13` | `node_modules/pnpm/package.json:64` — `engines.node`, pnpm 11.5.0 |
| The two literals were the only Node declarations in the repo | repo-wide grep for `node-version` (excluding `vendor`, `node_modules`); no `engines` in `package.json`, no `.nvmrc`, nothing in `docs/` |
| No test or document reads `ci.yml` | grep for `ci.yml` in `tests/` — zero hits, so no guard breaks or becomes false |
| `node-version-file` is a real input | `actions/setup-node@v4` `action.yml` + `docs/advanced-usage.md:57-72`, resolved relative to the repository root |
| `22` is a resolvable spec that satisfies the floor | `semver.validRange('22')` → `>=22.0.0 <23.0.0`, intersects `>=22.13` |

The major line `22` is chosen over the bare floor on purpose: pointing at
`package.json` and letting `engines.node` be the spec would resolve `>=22.13` to
the newest satisfying release — a moving target that could land on an untested
major. `22` stays inside the LTS line and still receives patches.

**Observation pending, and deliberately not claimed.** CI can only be observed
from a run, and the local machine cannot produce one. The exact thing to confirm
on the next run is that `Setup Node` resolves a `22.x` version and that the
`pnpm store path` cache-key step no longer dies on `node:sqlite`. Everything
after that point (`quality` reaching ESLint, `frontend-build` reaching
`pnpm build`) is a different issue's business (#14, #15).
