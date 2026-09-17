# Task: CI conformance — make main green and the gates honest

Status: Slice 1 DONE and pushed. #13 DONE, observed in a real run, and
independently verified. Remaining work is tracked as GitHub issues, one per
session-sized unit — see below. Nothing here is a plan for a single sitting: the
diagnosis was bigger than one session, so it was split.

## Tracking

| Issue | Subject | State |
|---|---|---|
| #13 | `Setup Node` fails: pnpm 11 needs Node >= 22.13, the workflow pinned Node 20 | **Fixed and observed** in run `35236959947` (commit `73c7634`). Still open on GitHub; closing it is the repository owner's call. |
| #14 | ESLint: 330 real errors across 88 files, including two dead and broken components | Open, and now the first failing step of `quality` — therefore the current blocker. |
| #15 | Four of the five quality gates cannot fail | Open, with the masking confirmed in the run log (see Slice 3). |
| #16 | MySQL suite: 177 failures, all from a literal `APP_KEY` in `ci.yml` | **Fixed**: the literal is gone from the workflow. Its premise was wrong — the `APP_KEY` explained 34 of the 177, not all of them. |
| #18 | The other 143 failures, with their measured distribution | Open, created 2026-09-17 from a pre-fix measurement. |
| #17 | `AGENTS.md` documents CI behaviour that was never true | Open. |

The slices below are kept as the reasoning that produced those issues.

## Delivered (Slice 1, commit `b9cf8cf`)

`pnpm/action-setup` added before `setup-node`; `needs: quality` dropped from
`backend-tests` and `frontend-build`; `@vue/eslint-config-prettier` moved last in
`extends` and the duplicated formatting rules deleted (1981 → 330 errors, zero
source churn). The decoupling is what finally ran the MySQL suite and produced
the evidence for #16.
Created: 2026-09-17
Triggered by: the user's requirement that `main` be conformant with best practices

## The measured state of CI

### Baseline, as measured on 2026-09-17 **before Slice 1** (kept for the record)

| Signal | Value |
|---|---|
| Runs in the workflow's lifetime | **56** |
| Runs that succeeded | **0** |
| Failure point then | `Setup Node` in the `Quality gates` job (17-22 s) |
| Earliest runs (2026-06-11, day one) | **also `Setup Node`** — the workflow was born broken |
| `Backend tests (MySQL)` | **had never executed, not once** |
| `Frontend build` | **had never executed, not once** |

Those last two rows were already stale by the time this section was written:
decoupling the jobs in Slice 1 (`b9cf8cf`) ran both for the first time, in runs
`35173263470`, `35173585948` and `35173977805`.

### Current, as measured on 2026-09-17 **after #13** (run `35236959947`, commit `73c7634`)

| Signal | Value |
|---|---|
| Runs in the workflow's lifetime | **60** |
| Runs that succeeded (run level) | **0** — every one of the 60 ends in `failure` |
| Jobs that succeeded (job level) | **1** — `Frontend build`, the first green job in the workflow's history |
| Current failure point | `Lint frontend (ESLint)`, step 12 of `Quality gates` — 330 errors |
| `Backend tests (MySQL)` | Executes; fails in `Run full test suite (MySQL)` — 177 failed, 833 passed |
| `Frontend build` | **PASSES**: `pnpm build` in 7.74 s, artifact `public-build.zip` (747 078 bytes) uploaded |

At baseline, `backend-tests` and `frontend-build` both declared `needs: quality`,
and `quality` had failed on every single run, so the MySQL test suite had never
run in CI. The claim in `AGENTS.md` §6 that the suite "passes in CI with MySQL"
is therefore **unverified**: it describes an intention, never an observation. As
of run `35236959947` the suite does execute — and it does **not** pass. Those 177
failures are the distance between that claim and the truth.

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

### Slice 4 — After `quality` can pass — PARTIALLY OBSERVED

Both jobs execute now, and their outcome was observed rather than assumed:

- `frontend-build`: **passed** in run `35236959947` — `pnpm build` in 7.74 s and
the artifact uploaded. Nothing to fix.
- `backend-tests`: executes and fails with 177 failures (issue #16).
- `quality`: gets past `Setup Node`, pnpm install, PHP syntax, JSON validation
  and Pint, then stops at ESLint (issue #14).

## Constraints

- The approved candidate is already committed (`016bcf4`, `d8f8d26`) and pushed,
  so its burned receipt is preserved in history. Everything here is a NEW
  candidate and will need its own review once no further edits are pending.
- Slice 1 changes no application source file, so it cannot affect behaviour.

## Issue #13 — Node version — DONE, observed and independently verified

Applied and pushed 2026-09-17 (`73c7634`), the first unit of the issue-driven
phase.

`.nvmrc` (new file, content `22`) is now the single source for the Node version;
both jobs that set up Node read it through `node-version-file: '.nvmrc'` instead
of carrying their own literal.

Evidence, measured rather than assumed:

| Claim | Source |
|---|---|
| The floor is `>=22.13` | `%LOCALAPPDATA%\node\corepack\v1\pnpm\11.5.0\package.json:64` — `engines.node`, for the pnpm that `packageManager` pins. **Not** `node_modules/pnpm/...`: that path does not exist here. This table cited it in the first version; independent verification refuted the citation while confirming the value. |
| The two literals were the only Node declarations in the repo | repo-wide grep for `node-version` (excluding `vendor`, `node_modules`); no `engines` in `package.json`, no `.nvmrc`, nothing in `docs/` — all as measured at the parent commit `03b60df` |
| No test or document reads `ci.yml` | grep for `ci.yml` in `tests/` — zero hits across 129 test files, so no guard breaks or turns false |
| `node-version-file` is a real input | `actions/setup-node@v4` `action.yml` + `docs/advanced-usage.md:57-72`, resolved relative to the repository root |
| `22` is a resolvable spec that satisfies the floor | the action leaves `22` as the literal range (`semver.clean('22')` is null) and resolves it as `>=22.0.0 <23.0.0`, which intersects `>=22.13` |

The major line `22` is chosen over the bare floor on purpose: pointing at
`package.json` and letting `engines.node` be the spec would resolve `>=22.13` to
the newest satisfying release — a moving target that could land on an untested
major. `22` stays inside the LTS line and still receives patches. It is also the
only option available today: `package.json` has no `engines` field, so
`node-version-file: 'package.json'` would not have resolved at all.

### Observed, not assumed — run `35236959947`, exposed to `73c7634` exactly

| Check | Evidence from the log |
|---|---|
| The runner resolves the `.nvmrc` | `Resolved .nvmrc as 22` |
| It is a 22.x release | `Found in cache @ /opt/hostedtoolcache/node/22.23.2/x64` |
| The step that used to die now works | `Install pnpm dependencies`: `Lockfile passes supply-chain policies (370 entries)`, 323 packages added |
| `Setup Node` no longer fails anywhere | succeeded in both jobs that use it (`quality`, `frontend-build`) |
| First green job in the workflow's history | `Frontend build`: `success`, artifact uploaded |

### Independent verification (delegated, read-only)

A separate `gentle-ai-verify` pass reproduced every claim from the repository and
the GitHub API rather than trusting this document. HELD: the `.nvmrc` bytes and
tracking, the workflow structure (3 jobs, no `needs`, 0 bare `node-version`,
pnpm before Node in both jobs), the "no test reads `ci.yml`" claim, the
run-to-commit binding, and the adversarial checks — no other workflow pins Node,
`package.json` has no `engines` to contradict, and no dependency's `engines.node`
excludes 22.x (47 distinct ranges in the lockfile, 741 installed packages, none
unsatisfied).

It refuted three things, all corrected above or below:

1. The `node_modules/pnpm/package.json:64` citation — right value, unreproducible path.
2. The run count: the repository total is **60**, not 59. The commit message's
   "all 59 runs" was accurate at commit time (59 had failed before it), but the
   figure must not be reused as a current count.
3. Five lines of the pre-existing state table that this commit had already made
   false: the "current failure point", the lifetime run count, and the two
   "has never executed" rows. Fixed by labelling the baseline as a baseline
   instead of presenting it as the present.

### The native review for this candidate could not be opened — tooling defect

`gentle_review` START over the **committed range**
(`{"mode":"ordinary","baseRef":"03b60df…","committedOnly":true}`) is refused
with `candidate-owner-preparation-failed` / "candidate view rejected before
native START", `lineage_created: false`, `mutation_performed: none`. The four
earlier reviews of this repository all used the **workspace** projection (the
working tree, before committing) — the route that works.

Hypotheses measured and refuted one by one; none of them is the cause:

| Hypothesis | Measurement that refutes it |
|---|---|
| Long paths — the cause `AGENTS.md` §8 documents for this very error | The view prefix is 157 chars and 33 tracked paths exceed 260 inside it (max 275) — but those paths landed 2026-08-10 (`c86ac32`) and the last **successful** review was 2026-09-16, a month later, with them in the materialised view. `core.longpaths` was already `true` in `.git/config`. |
| Stale locks | `gentle-ai review status --cwd .` → `status: clean`, `entries: []`, the lock reported `"released"`, `diagnostics: []` |
| Untracked residue | `git ls-files --others --exclude-standard` → 0; `git status --porcelain` clean. The 23 MB `.tmp_chrome_profile` is ignored at `.gitignore:53` |
| Orphan worktree | `git worktree list` → `main` only; `.git/gentle-ai/candidate-views/` empty |

**What distinguishes the failure is the projection, not the repository state.**
No further guessing was attempted: composing undocumented `input` keys is
forbidden, and driving the native CLI directly would create the lineage while
skipping the per-candidate human consent — governance, not convenience. The
compensation is the path ASSESS itself prescribes when the native review outcome
is unknown: writer self-verification plus an independent verifier, both of which
ran (above).

**Consequence for the next units: review while the change is still in the
working tree.** The workspace projection is the supported route; committing
first leaves nothing to project.

## Issue #16 — the `APP_KEY` literal — fixed, with the premise corrected

### The premise was wrong, and it was measurable

#16 attributes all 177 MySQL failures to the `APP_KEY`. It explained **34 of
them (19%)**. The other 143 are a hidden backlog with their own causes,
measured from the run log and now tracked as #18 with the full distribution.

The distribution is worth stating plainly because it changes what the fix
buys: the single largest cause is not the key at all, it is 45 failures of
`Field '…' doesn't have a default value` — MySQL in strict mode rejecting
insertions that SQLite accepts silently.

### The mechanism, verified rather than assumed

The workflow exported this as an environment value:

```yaml
APP_KEY: base64:$(php -r "echo base64_encode(random_bytes(32));")
```

GitHub never shell-interpolates an `env:` value, so it was the literal string.
Evaluated by PHP: stripping the `base64:` prefix leaves
`$(php -r "echo base64_encode(random_bytes(32));")`, and
`base64_decode(..., true)` returns `false` — not a valid 32-byte key. Laravel's
`Encrypter::supported()` rejects it and every test that touched the encrypter
died at `Encrypter.php:61`.

Reproduced locally with the same command CI runs, by exporting that same
literal: `RuntimeException: Unsupported cipher or incorrect key length … at
Encrypter.php:61`, in the same test, at the same line (`KpiNumberTabularTest
.php:32`).

### Why `phpunit.xml`'s valid key did not save it — and why the obvious guard is fake

`phpunit.xml` already declares a valid testing key. PHPUnit's `<env>` only sets
variables that are not already present, so the export won. The obvious defence
is `force="true"` on that element. It was tried, and it does **not** work here.
A probe printing all four sources, with the broken literal exported, settled it:

| Source | Value |
|---|---|
| `getenv('APP_KEY')` | correct — PHPUnit set it |
| `$_ENV['APP_KEY']` | correct — PHPUnit set it |
| `$_SERVER['APP_KEY']` | **the broken literal** — where the shell export lands, because `variables_order` is `GPCS` and lacks `E` |
| `env('APP_KEY')` | **the broken literal** |
| `config('app.key')` | **the broken literal** |

`force` is honoured — `PhpHandler.php:112-120` does `putenv()` and writes
`$_ENV` — but Laravel resolves from `$_SERVER`, which the attribute never
touches. The attribute was therefore removed rather than kept: it cannot change
any outcome, and a guard that cannot fail is the exact defect this repository
has been retiring. Note also that `force` must never be added to
`DB_CONNECTION`: that variable is *supposed* to lose to the job env, or the
MySQL job would run on SQLite.

### The fix

One line deleted from `.github/workflows/ci.yml`, with a comment saying why the
variable is deliberately absent. `phpunit.xml` is the single source for the
testing key. The rejected alternative was putting a valid literal key in the
workflow: that duplicates a value in two places, which is the drift class this
project keeps paying for.

Locally: `tests/Feature/Ui` stays at 36 passing, and the two tests that
reproduced the CI symptom pass now (`OK (3 tests, 10 assertions)`). Locally
there was never an export to remove, so the fix's effect is only observable in
a run.

**Observation pending, and deliberately not claimed:** how many of the 34 now
pass. They may fail for a different reason once the encrypter stops being the
first wall they hit. The number belongs to the next run.
