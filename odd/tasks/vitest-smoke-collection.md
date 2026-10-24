# Vitest smoke collection (#186)

Feature: confirm whether the nine smoke files still fail to collect, and give the suite a reproducible result.
Branch: none (verification only) · Issue: #186.

## Specs

S1. "Los nueve archivos colectan y ejecutan sus casos."

S2. "La suite completa no reporta archivos fallidos."

S3. "CI ejecuta exactamente lo mismo que la corrida local."

## Verification record

| Check | Command | Result |
| --- | --- | --- |
| Clean install, same as CI | `pnpm install --frozen-lockfile` in a fresh worktree | 4.5 s, no drift |
| The nine files | `npx vitest run` with the nine paths | 9 files / 149 tests passed |
| Full suite | `pnpm test:js` | 90 files passed, 745 tests passed, 1 unrelated file failed under load (see below) |
| CI on main | `gh run list --branch main` | Every run since `b3c168a` succeeded, including the Vitest smoke step |

Conclusion: on `main@3cc3bd9` the nine files collect and run. The CI run at the
commit named in the issue was already green, so the reported collection failure
was a local environment state (stale install or worktree), not a defect in the
suite or the pages.

Known difference with S3: CI reads Node from `.nvmrc` (22); the local machine
runs Node 26.10.0. The command is identical (`pnpm test:js`).

## New finding (out of scope)

`tests/js/smoke/business-intelligence-reports.test.js > renders a retryable
error state and never leaves the previous table mounted` failed once while
three suites ran concurrently on the machine; isolated it passed 5/5 runs and
the same file passed in the main worktree. Load-sensitive single
`flushPromises()` after the retry click, same family as #206. Not one of the
nine files, so it is not fixed here.

## Log

L1. User request (verbatim): "quiero hacer issues #183 #184 #185 #186 #206, en un worktree nuevo".
L2. Grouping decision (user): five branches, five PRs, one per issue.
L3. No branch is opened for #186: there is no defect to fix on main.

L4. Hallazgo posterior mapeado a la issue #250: el smoke de BI
(`business-intelligence-reports.test.js`) falló una vez bajo carga concurrente y
no se reprodujo en 13 corridas. La issue fija el disparador (segunda aparición)
y la mitigación candidata (`vi.waitFor` acotado); no se cambia el test mientras
pase.
