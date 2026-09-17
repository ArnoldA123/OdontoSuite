# Task: harden the audit baseline against the three risk findings

Status: in progress. Branch `fix/audit-baseline-hardening`, from `8136345` (the tip
of `chore/audit-phase0-baseline`, whose candidate was reviewed and acknowledged).

## Why this exists

The native review of the phase-0 candidate (lineage `review-82b603bc072e80c6`,
target `sha256:662ec641…`) closed **approved** with 18 advisory findings, all
`disposition: informational`. The review forbids re-running it on that candidate:
each fix is separate later work with its own candidate. This document covers the
three findings that can lose or expose data.

## Findings in scope

| ID | Lens | Location | What it says |
| --- | --- | --- | --- |
| `R1-data-loss-guard-fail-open` | risk | `scripts/audit/baseline.sh:250-260` | The guard that stops the audit from running `migrate:fresh` against the application database reads `.env` with a literal `^DB_DATABASE=` match. A quoted value, a CRLF line ending, or a value that is not there at all evades it, and the suite then wipes whatever database the test name points at. |
| `R1-db-network-exposure` | risk | `docker-compose.yml:36-41` | The container publishes its port on every interface with a fixed development password. |
| `R1-db-password-in-artifact` | risk | `scripts/audit/baseline.sh:288-290` | The command recorded next to the MySQL counters can carry `DB_PASSWORD` into the artifact, which is written to disk and pasted into documents. |

The second verification pass of the previous candidate reached the same conclusion
about the first finding independently, by code inspection: "the comparison is a
literal string match against the first `^DB_DATABASE=` line, so a quoted or CRLF
value would evade the guard; `DB_DATABASE` exported in the shell rather than
written in `.env` is not seen."

## Decisions

- **D1 — the guard's rule, stated once, in three parts.** The suite runs
  `migrate:fresh` on `odontosuite_test`. (a) If `.env` names the application
  database and that name *is* `odontosuite_test`, the application's data lives in
  the database the suite will wipe: refuse, always. (b) If the run will use the
  developer's live engine and the application database name cannot be read, the
  two cannot be proven different: refuse. (c) Otherwise proceed, always exporting
  `DB_DATABASE=odontosuite_test` so the run cannot drift to another database.
- **D2 — failing closed is scoped to the dangerous path.** A container engine
  holds no application data, so an unreadable `.env` is not a reason to refuse
  there; the developer's live engine is, and that is where (b) applies.
- **D3 — parsing follows the framework, not grep.** Quotes stripped, CRLF
  tolerated, whitespace around the separator tolerated. The parse reads
  `BASELINE_ENV_FILE` when set, which is the seam that makes it testable without
  touching the real `.env`.
- **D4 — a data-loss guard whose decision cannot be tested is not a guard.** A
  small diagnostic mode prints the parsed name and the decision, so a test can
  drive every variant the finding names instead of trusting a reading.
- **D5 — credentials are masked in the recorded command.** That breaks the
  "verbatim" property on purpose, for one field, and the comment says so.
- **D6 — one raw directory per run, not per day.** Reusing a daily directory let
  a run that refused the MySQL half still list the previous run's `mysql.txt` as
  its own raw output, so a reader could conclude the suite ran when it had not.
  The per-run directory removes the possibility of that claim being made.

## Out of scope

The other 15 advisory findings (`R2-*`, `R3-*`, `R4-*`) stay as later work: they
are reliability, resilience and readability observations on the same two files,
none of which can lose or expose data. Fixing them here would mix a data-safety
candidate with style work and inflate the review.

## Checks

- `vendor/bin/phpunit --filter=MySQLTestEngineContractTest` passes, including the
  new assertions that the published port is bound to loopback only.
- The guard decision test passes for: plain value, double-quoted, single-quoted,
  CRLF, spaces around `=`, absent key, missing file.
- **Proven able to fail:** each variant is falsified once and the test must report
  the difference; the tree is restored and the restoration proven by hash.
- `bash scripts/audit/baseline.sh` still captures every counter with none empty,
  and never writes a password into the artifact.
- `docker compose config` resolves the loopback binding.

## Evidence log

| Step | Command | Observed |
| --- | --- | --- |
| before | `grep -n 'app_db=' scripts/audit/baseline.sh` | `app_db=$(grep -E '^DB_DATABASE=' .env … \| cut -d'=' -f2-)` — literal match, no quote or CR handling, nothing when absent |
| before | `sed -n '36,41p' docker-compose.yml` | `- '${MYSQL_PORT:-3307}:3306'` — every interface |
| after | `bash scripts/audit/baseline.sh --explain-database-guard` | `app_database=odontosuite`, `fallback_path=proceed` on the real `.env`: the application database is not the one the suite wipes |
| after | `docker compose config` | `host_ip: 127.0.0.1`, `published: "3307"`, `target: 3306` — loopback only |
| after | `vendor/bin/phpunit --filter='MySQLTestEngineContractTest\|AuditBaselineGuardTest'` | 15 tests, 46 assertions, OK |
| after | `vendor/bin/pint --test tests/Unit/Tooling/` | PASS (the new test file needed `phpdoc_align` first) |
| after | `bash scripts/audit/baseline.sh` | COMPLETE, exit 0, 4m05s, 28 counters, none empty |
| after | `grep -c 'DB_PASSWORD=[^ *]' .atl/qa-evidence/audit/baseline-2026-09-17.md` | `0`; the recorded MySQL command reads `DB_PASSWORD=***` |
| **refusal exercised end to end** | `printf 'DB_DATABASE=odontosuite_test\n' > /tmp/refusal.env; BASELINE_ENV_FILE=/tmp/refusal.env bash scripts/audit/baseline.sh` | status **PARTIAL**, exit **3**, `mysql_engine=refused`, `mysql_status=refused-app-database-is-test-database`, partial reason names the file, and **no test command ran** |
| falsification | strip the robust parse and the loopback prefix, then run the tests | **5 failures naming the defects**: four guard variants (`double quoted`, `single quoted`, `spaces around the separator`, `trailing spaces`) and `runner_and_compose_agree_on_the_engine`. Restoration proven by md5 `6623594f…` (script) and `e8a285e7…` (compose), identical before and after |

Two observations worth keeping:

- The **CRLF variant still passed under falsification**: MSYS `grep` normalises line
  endings in text mode, so the explicit trim is defence for environments that do
  not, and the variant stays as a regression check rather than a local
  discriminator. Saying otherwise would overstate the evidence.
- The falsification is what exposed the stale `mysql.txt`: the refusal run's raw
  directory held 681,604 bytes from the previous run. That is fixed by D6 and the
  fix is proven by the refusal run above, whose raw directory has no `mysql.txt`
  and whose artifact lists none.

## Not covered here

The refusal branch is exercised through `BASELINE_ENV_FILE`, which is the same
code path the live `.env` takes. What is not exercised is a real `.env` whose
application database is `odontosuite_test` — deliberately: proving that would
require a database to destroy.
