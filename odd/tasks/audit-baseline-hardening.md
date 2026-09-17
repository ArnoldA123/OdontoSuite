# Task: harden the audit baseline against the three risk findings

Status: on branch `fix/audit-baseline-hardening`, seeded from `8136345` (the tip
of `chore/audit-phase0-baseline`, whose candidate was reviewed and acknowledged).
One revision has been independently verified and its findings are folded in below.

## Why this exists

The native review of the phase-0 candidate (lineage `review-82b603bc072e80c6`,
target `sha256:662ec641…`) closed **approved** with 18 advisory findings, all
`disposition: informational`. The review forbids re-running it on that candidate:
each fix is separate later work with its own candidate. This document covers the
three findings that can lose or expose data.

## Findings in scope

| ID | Lens | Location | What it says |
| --- | --- | --- | --- |
| `R1-data-loss-guard-fail-open` | risk | `scripts/audit/baseline.sh` | The guard that stops the audit from running `migrate:fresh` against the application database read `.env` with a literal `^DB_DATABASE=` match. A quoted value, a CRLF ending, spaces around the separator, an absent key or an unreadable file evaded it, and the suite then wipes whatever database the test name points at. |
| `R1-db-network-exposure` | risk | `docker-compose.yml` | The container published its port on every interface with a fixed development password. |
| `R1-db-password-in-artifact` | risk | `scripts/audit/baseline.sh` | The command recorded next to the MySQL counters could carry `DB_PASSWORD` into the artifact, which is written to disk and pasted into documents. |

Two independent observers had already reached the same conclusion about the first
one: the second verification pass of the previous candidate, by code inspection.

## Decisions

- **D1 — the guard's rule, in two sources and three parts.** The suite runs
  `migrate:fresh` on `odontosuite_test`. Two sources can name the application
  database: the env file, and an exported `DB_DATABASE`. (a) If *either* source
  names `odontosuite_test`, the application's data may live in the database the
  suite will wipe: refuse, whichever engine is used. (b) If the run would use the
  developer's live engine and *neither* source provides a name, the two cannot be
  proven different: refuse. (c) Otherwise proceed, always exporting
  `DB_DATABASE=odontosuite_test` so the run cannot drift to another database.
- **D2 — failing closed is scoped to the dangerous path.** A container engine
  holds no application data, so an unreadable env file is not a reason to refuse
  there; the developer's live engine is, and that is where (b) applies.
- **D3 — parsing follows the framework, not grep.** Quotes stripped, CRLF
  tolerated, whitespace around the separator tolerated. The parse reads
  `BASELINE_ENV_FILE` when set, which is the seam that makes it testable without
  touching the real `.env`.
- **D4 — the rule lives in one function.** `database_guard_decision` is consumed by
  `measure_mysql` and printed by `--explain-database-guard`, so the rule that is
  tested is the rule that runs; a data-loss guard whose decision cannot be tested
  is not a guard.
- **D5 — credentials are masked in the recorded command.** That breaks the
  "verbatim" property on purpose, for one field. The script header and the
  artifact itself now both say so.
- **D6 — one raw directory per run, not per day.** Reusing a daily directory let a
  run that refused the MySQL half still list the previous run's `mysql.txt` as its
  own raw output, so a reader could conclude the suite ran when it had not.
- **D7 — the exported `DB_DATABASE` counts, and it was the residual hole.** An
  exported value beats the file inside the framework, because Laravel loads
  `.env` immutably. The first version of this fix read only the file, so a shell
  exporting `DB_DATABASE=odontosuite_test` left the guard saying `proceed` while
  the application's live database was the one the suite wipes. Found by the
  independent verification of this candidate, not by the original review, and
  closed by D1(a).

## Out of scope

The other 15 advisory findings (`R2-*`, `R3-*`, `R4-*`) stay as later work: they
are reliability, resilience and readability observations on the same two files,
none of which can lose or expose data. Fixing them here would mix a data-safety
candidate with style work and inflate the review.

## Checks

- `vendor/bin/phpunit --filter='MySQLTestEngineContractTest|AuditBaselineGuardTest'`
  → **17 tests, 54 assertions, OK**, including the loopback binding, the eleven
  env-file decisions, the two export cases and the source-disagreement case.
- **Proven able to fail:** restoring the legacy parse verbatim (as it stood at
  `8136345:250`) and removing the loopback prefix produces **13 failures out of
  17**. A narrower injection that only drops the trimming and quote handling
  produces 5. Both are recorded, because they are different experiments.
- `bash scripts/audit/baseline.sh` → COMPLETE, exit 0, 28 counters, none empty.
- `docker compose config` → `host_ip: 127.0.0.1`, `published: "3307"`.

## Evidence log

| Step | Command | Observed |
| --- | --- | --- |
| before | `grep -n 'app_db=' scripts/audit/baseline.sh` | `app_db=$(grep -E '^DB_DATABASE=' .env … \| cut -d'=' -f2-)` — literal match, no quote or CR handling, nothing when absent |
| before | `sed -n '36,41p' docker-compose.yml` | `- '${MYSQL_PORT:-3307}:3306'` — every interface |
| after | `bash scripts/audit/baseline.sh --explain-database-guard` | `app_database_env_file=odontosuite`, `app_database_exported=(unset)`, `decision=proceed` on the real `.env` |
| after | `DB_DATABASE=odontosuite_test bash scripts/audit/baseline.sh --explain-database-guard` | `app_database_exported=odontosuite_test`, `decision=**refuse**` — D7 demonstrated live, and it is the case that used to pass |
| after | `docker compose config` | `host_ip: 127.0.0.1`, `published: "3307"`, `target: 3306` |
| after | `vendor/bin/phpunit --filter='MySQLTestEngineContractTest\|AuditBaselineGuardTest'` | 17 tests, 54 assertions, OK |
| after | `vendor/bin/pint --test tests/Unit/Tooling/` | PASS |
| after | `bash scripts/audit/baseline.sh` | COMPLETE, exit 0, 28 counters, none empty |
| after | `grep -c 'DB_PASSWORD=[^ *]' <artifact>` | `0`; the recorded command reads `DB_PASSWORD=***`, and the artifact now states that this one field is masked |
| **refusal exercised** | `printf 'DB_DATABASE=odontosuite_test\n' > refusal.env; BASELINE_ENV_FILE=refusal.env bash scripts/audit/baseline.sh` | status **PARTIAL**, exit **3**, `mysql_engine=refused`, `mysql_status=refused-app-database-is-test-database`, no `mysql.txt` in that run's raw directory, and no `mysql_*` counters |
| falsification (narrow) | drop the trimming and quote handling, remove the loopback prefix | 5 failures naming the defects |
| falsification (legacy parse) | restore `env_value`'s body verbatim from `8136345:250`, remove the loopback prefix | **13 failures of 17**, method names listing every quoted, spaced and export variant |
| restoration | md5 before/after both falsifications | script `11ebdb219fa238f8ae2f2af09c2bfb34`, compose `e8a285e72771ab7e32015e320ce353ff`, identical both times; `git status --porcelain` clean afterwards |

## Corrections this document carries

The first version of this file was itself independently verified, and three of its
claims did not survive:

1. **"no test command ran" in the refusal path — false as written.** The unit suite
   runs *before* the guard, inside the same capture: the refusal run's own
   `unit.txt` is 413 KB and the artifact lists it. What the refusal suppresses is
   the **MySQL** half, and that is the claim that matters: no `mysql.txt`, no
   `mysql_*` counters, no `php artisan test --configuration=phpunit.mysql.xml`
   call site reached, and therefore no `migrate:fresh` in that path.
2. **The falsification count was short by one, then by eight.** The first figure
   (5) came from a narrower injection than the one the finding describes; the
   legacy parse reaches 13. Both numbers are above.
3. **A quoted md5 was stale.** The fixed script had moved on since that hash was
   taken. The hashes above were all re-measured after the last edit.

## Not covered here

- The refusal branch is exercised through `BASELINE_ENV_FILE`, which is the same
  code path a live `.env` takes. A real `.env` naming `odontosuite_test` is not
  used, deliberately: proving that would require a database to destroy.
- Masking a non-empty `DB_PASSWORD` is exercised at function level, because the
  local `.env`'s password is empty and a "search for the secret" would be vacuous.
- The CRLF variant stays green even under falsification, because MSYS `grep`
  normalises line endings in text mode. That trim is defence for environments that
  do not, and the variant is a regression check rather than a local discriminator.
  Saying otherwise would overstate the evidence.
