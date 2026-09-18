# Task: Audit phase 0 — make the baseline executable (issues #22, #24)

Status: **closed.** Both work units are committed on
`chore/audit-phase0-baseline`, plus a third commit carrying the corrections two
independent verifications produced, and both PRs are merged into `main`
(`59146e1`, `9dd820e`). The two things open at implementation time — the
unverified container half and the points waiting on the repository owner — are
resolved one by one in "Resolution of the open items" at the end. The list
itself is kept as it was written: it is the record of what was open then.

Phase 0 of plan #12 (`docs/mejoras/12-programa-auditoria-integral-2026-08.md` §3)
is axis **A1** (#24), a single reproducible baseline. #24 states in its own body
that its MySQL half depends on **#22**, so #22 went first.

## Work units

| # | Issue | Deliverable | Commit |
| --- | --- | --- | --- |
| WU-1 | #22 | `docker-compose.yml`, `phpunit.mysql.xml` pinned to it, `tests/Unit/Tooling/MySQLTestEngineContractTest.php` | `6950208` |
| WU-2 | #24 | `scripts/audit/baseline.sh` | `13824e0` |
| WU-3 | verification | Naming and traceability corrections in `scripts/audit/baseline.sh` | `f6d78c1` |
| — | — | This tracking document | `fde1f36` |

## Verification

Two independent read-only passes (`gentle-ai-verify`), each against a different
state of the branch.

**Pass 1 — `825c672`, working tree (task `mu5ta5um-1-mta5`).** CI mirror
fidelity, effectiveness of the pin, the override escape hatch, XML/YAML validity
and port freedom: CONFIRMED. Container half: UNVERIFIED. It also reported that
the tree was not frozen while it verified — a fair objection, fixed by the
commits now on this branch — and three defects: the provenance stated in the new
comment (addressed as defect 1b below), a fixed `container_name` that would make
two checkouts collide (removed), and a `php artisan test --configuration` warning
worth documenting (now noted in the compose file).

**Pass 2 — `fde1f36`, clean tree (task `mu5uewrk-2-fbd2`).** Frozen revision and
commit hygiene, artifact completeness (28 counters, none empty), **byte-identical
reproduction of the artifact on an independent re-run**, the falsification claim
(a falsified figure and a claimed-but-unmeasured key are both reported, exit 1),
the guard test's falsifiability with restoration proven by md5 and git blob, CI
mirror fidelity, honesty of the engine label, the `migrate:fresh` guardrail by
code inspection, and 13 of 16 numerals in this document: all CONFIRMED or
matching. Three defects found, all fixed in `f6d78c1` (rows 9-11 below).

## Measured facts (2026-09-17, `825c672` then `6950208`, clean tree)

- `ls docker-compose.yml` and `ls scripts/audit` were both missing at `825c672`:
  N1 (#22) and A1 (#24) were still open.
- Counts, re-measured and unchanged since `3f5cc58`: seeders 15, API controllers
  31, models 50, migrations 109, routes 203 (190 under `api/`), test files 127
  before this branch. #21's evidence still holds.
- `phpunit.xml` (the **default** runner) pins `DB_CONNECTION=sqlite`, so
  `php artisan test --group=mysql`, the command `AGENTS.md` §6 and §8 document,
  runs those tests **on SQLite** — the engine the `@group mysql` annotation
  exists to avoid. The MySQL runner is `phpunit.mysql.xml`.
- `phpunit.mysql.xml` pinned `DB_CONNECTION` and `DB_DATABASE` only, so host,
  port, user and password came from the `config/database.php` defaults
  (`127.0.0.1:3306`, `root`, empty): the local development engine, reached while
  the runner claimed to run the MySQL path. `.env.testing` declares none of those
  keys, so `.env` was never the source.
- Local engine: XAMPP **MariaDB 10.4.32** on 3306, database `odontosuite_test`
  already present. Port 3306 is owned by the Windows service `mysql` (RUNNING).
- Docker Desktop's Linux engine cannot start on this machine: `wsl -l -v`
  reports no installed distribution and Docker Desktop fails with
  `\\wsl$\docker-desktop-data\isocache: the network name cannot be found`.
  Environment blocker, out of scope by explicit user decision.
- The container half was therefore never executed. `docker compose config`
  resolves locally without an engine (exit 0, published port 3307), and a
  container run fails only because the pipe is absent.

## Decisions

- **D1 — host port 3307, not 3306.** 3306 is owned by the local `mysql` service,
  so a `3306:3306` binding cannot start, and publishing 3306 would shadow the
  database `.env` points at. The container publishes `${MYSQL_PORT:-3307}:3306`
  and the runner is pinned to 3307.
- **D2 — `phpunit.mysql.xml` declares its own connection**, so the runner
  describes the engine instead of borrowing the developer's. Verified escape
  hatch: PHPUnit 11.5.38 does not replace a variable already exported, so
  `DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= php artisan test
  --configuration=phpunit.mysql.xml` reaches the local MariaDB (4 passed, 1
  failed, 8.06s) while the same command without overrides is refused on 3307.
- **D3 — `AGENTS.md` is not edited here.** Its §6/§8 `--group=mysql` instruction
  names the wrong runner; #21 owns §4/§6/§11/§12. Handed over as measured
  evidence, independently confirmed by the verifier (its D5).
- **D4 — the acceptance criterion was verified only as far as the environment
  allowed, until the environment was repaired.** `docker compose config` is not a
  container run, and for the first half of this work the artifact said so instead
  of implying otherwise. The criterion has since been executed in full; item 1 of
  the last section records the observed sequence.
- **D5 — one artifact beyond the issue's letter.** #22 asks for the compose file
  and the runner, not a test. The guard test was added because the same silent
  defect was introduced twice by hand in one session: a `--` inside an XML
  comment makes PHPUnit refuse the whole configuration and report nothing. The
  guard checks well-formedness, the runner/engine agreement on database, user,
  password and port, and the presence of the health gate the acceptance criterion
  reads. Proven able to fail: injecting the `--` and a port mismatch yields 2
  failures, and the file was restored byte for byte (md5 `ad90ffad…` before and
  after).

## Defects found while implementing, and their fixes

| # | Defect | How it surfaced | Fix |
| --- | --- | --- | --- |
| 1 | `--` inside the `phpunit.mysql.xml` comment (twice, the second time while fixing the first) | PHPUnit: `Double hyphen within comment`; the MySQL suite reported nothing at all | Reworded; the guard test now fails on it |
| 2 | `VAR=value func` does not export to the function's children, so the fallback's `DB_*` overrides never reached `php` | Reasoned from bash semantics before running | `run_gate mysql env DB_HOST=… php artisan test …` |
| 3 | `--check` could not read its own artifact: the fenced block ended `}``` ` on one line | Falsification test | `pairs_to_json` emits a trailing newline; the reader tolerates both |
| 4 | ESLint counters came out empty: pnpm wraps the JSON in a banner and an `[ELIFECYCLE]` footer whose bracket is the file's last | First capture showed three empty counters | Parse from the first bracket, walking back over closing brackets until the slice parses; the block now records a partial reason if it cannot |
| 5 | `sed 's|^|- \`dir/&`|'`: `&` is the empty `^` match, so the raw listing rendered `` `dir/`build.txt `` | Artifact inspection | Regex capture, and directories excluded |
| 6 | A quote literal inside a JS string in the script was mangled by the editing layer (`character === ')`) | Node reported `SyntaxError` once stderr stopped being swallowed | Removed the hand-written string scanner entirely (the retry loop needs no quote literal) |
| 7 | `$TEMP` is `/tmp` in Git Bash, which Node (a Windows binary) resolves as `E:\tmp` | The falsification test read no file | Tests write outside the repo via `cygpath -w /tmp` |
| 8 | The new test file added a Pint style issue (354 → 355) | The baseline measures Pint | `vendor/bin/pint` scoped to that file |
| 9 | `prettier_flagged` presented 189 as the number of files Prettier would reformat. The run aborts at the first file whose parser is missing, so it is a lower bound covering only alphabetically-prior files | Verification pass 2 | Renamed `prettier_warned_before_abort`; the artifact glosses the naming |
| 10 | The three MySQL counters recorded a literal command instead of the one that ran, dropping the `env DB_*` overrides that named the engine | Verification pass 2 | They record `$MYSQL_CMD`, captured from the run itself |
| 11 | `Overall status: COMPLETE` sat unglossed beside red gates and 116 failed tests | Verification pass 2 | The artifact states what `COMPLETE` does and does not mean |

## Evidence log

| Step | Command | Observed |
| --- | --- | --- |
| WU-1 before | `php artisan test --configuration=phpunit.mysql.xml --filter=AuditLogMigrationTest` | 4 passed, 1 failed, 10.19s — reached the local MariaDB through config defaults, never a container |
| WU-1 after | same command, no overrides | `SQLSTATE[HY000] [2002]` on 127.0.0.1:3307: the pin is effective and the engine is now required |
| WU-1 escape hatch | `DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= …` | 4 passed, 1 failed, 8.06s: the override reaches the local engine |
| WU-1 guard, clean | `vendor/bin/phpunit --filter=MySQLTestEngineContractTest` | 3 tests, 20 assertions, OK, exit 0 |
| WU-1 guard, falsified | same, with `--` in the comment and `DB_PORT=3308` | 2 failures naming both defects, exit 1; restored byte for byte |
| WU-1 compose | `docker compose config` | exit 0 without an engine; `published: "3307"`, healthcheck resolved |
| WU-2 falsification | `bash scripts/audit/baseline.sh --check <falsified doc>` | `MISMATCH: 6 of 28 counter(s)`, table shows `models 47 ≠ 50`, `routes_total 148 ≠ 203`, exit 1 |
| WU-2 capture | `bash scripts/audit/baseline.sh` | COMPLETE, exit 0, 3m08s, 28 counters, none empty |
| WU-2 first figures | artifact `baseline-2026-09-17.md` | 203 routes (190 api), 299 eslint errors / 1398 warnings / 115 files, 189 files Prettier would reformat, 354 Pint issues, 811 unit tests (45 failed), 1023 MySQL-runner tests against MariaDB 10.4 (71 failed) |

Note on the 299: it matches the figure the CI tracker in `odd/tasks/ci-conformance.md`
already recorded for the ESLint half (330 → 299), measured independently.

## Open items: verified since, still unverified, or waiting on the owner

1. **Verified on 2026-09-17, after the environment was repaired.** The acceptance
   criterion ran in the issue's own words: `docker compose up -d mysql` started the
   service, `docker compose ps` reported `health: starting` immediately and
   `Up (healthy)` about thirty seconds later, `php artisan test
   --configuration=phpunit.mysql.xml` ran against **MySQL 8.0.46** with no
   overrides and reported 71 failed and 979 passed, and `docker compose down`
   removed the network and left no container behind. Measured inside the
   container: `sql_mode` carries `STRICT_TRANS_TABLES` and
   `lower_case_table_names` is 0.

   The blocker was never the repository. WSL reported no installed distribution
   because Intel VT-x was disabled in firmware; enabling it let Docker Desktop
   register its own distribution and start. The honest sequence before the repair
   — valid file, no engine, every claim about the container marked unverified —
   is what the rest of this document records, and it stands as the reason the
   acceptance criterion was reported as unmet rather than assumed.
2. **Handover to #21:** `AGENTS.md` §6/§8 still tell the reader to run
   `php artisan test --group=mysql`, which executes on SQLite. #22 does not
   authorise editing `AGENTS.md`.
3. **For #18:** the MySQL runner reports 71 failures against MariaDB 10.4 (and the
   default runner 45 on SQLite). One is identified: `AuditLogMigrationTest:74`
   asserts the migration source still contains `->after('user_agent')`, an
   engine-independent assertion that fails on a real engine too.
4. **New finding, needs its own issue:** `pnpm format:check` cannot complete.
   `.prettierrc` maps `*.php` to the `php` parser and no PHP plugin is installed
   (`package.json` declares only `prettier ^3.1.0`; `.prettierignore` has no php
   entry), so the run dies on the first of 522 tracked PHP files. Every figure
   derived from that gate is a lower bound, and the gate cannot be satisfied at
   all. Pre-existing, family of #15 and #17.
5. **Owner decision:** whether the guard test (D5) stays, given #22 did not ask
   for it.
6. **Owner decision:** whether to append these findings to the issue bodies
   (#22, #24, #21) and close what is closed, or leave that to the repository
   owner. Nothing was written to GitHub from this session.
7. **Owner decision:** whether to unblock the container half by repairing WSL for
   Docker Desktop. Until then #22's acceptance criterion cannot be satisfied on
   this machine, only the repository-side defect can be.

Two numerals in the evidence log were measured in-session and are not
reproducible from the repository: the `MISMATCH: 6 of 28` line (the falsified
document was a temporary file, since deleted) and the escape-hatch timings.
Verification pass 2 confirmed the mechanisms behind both with its own falsified
document (`3 of 27`, exit 1) and the override reaching MariaDB 10.4.

## Next step per plan #12 §3

Phase 1 is **A2 (#18), A4 (#26), A3 (#25)**, in that order of defect yield. A2 can
now group its failures by root class using the baseline's MySQL half, which is
blocked on the same environment blocker as item 1.

## Resolution of the open items (2026-09-18)

Closed one by one, with the evidence that closed them. The list above is kept as
written: it is the record of what was open when the phase was implemented, not a
current status.

| Item | Resolution |
| --- | --- |
| 1. Container half unverified | **Executed in full.** The blocker was outside the repository: Intel VT-x was disabled in firmware, so WSL had no distribution and the Docker engine could not start. With virtualization enabled, #22's acceptance criterion ran in its own words — `up` → `health: starting` → `Up (healthy)` → full suite on MySQL 8.0.46 → `down`. Recorded in the #22 closing comment. |
| 2. Handover to #21 | **Done.** #21 carries the measured handover: `docker compose up -d mysql` is executable now that `docker-compose.yml` is on `main`, `php artisan test --group=mysql` still runs `phpunit.xml` (SQLite) and does not switch engine, and the local override the pinned runner now demands. |
| 3. Handover to #18 (`AuditLogMigrationTest:74`) | **Still failing, still unowned.** It is one of the ~18 failures that axis A2's exit criterion has to cover: A2 closes when every failure of the run has an assigned cause and an owner, and that triage is the next session's work (#44). |
| 4. `pnpm format:check` cannot complete | **Owned by #15**, whose body documents the cause verbatim (`Couldn't resolve parser "php"`: `.prettierrc` maps `*.php` to a parser whose plugin is not installed) and lists the two fixes. No separate issue was opened, so as not to duplicate it. |
| 5. **Owner decision: does the guard test (D5) stay?** | **It stays.** The defect it guards is silent — a `--` inside an XML comment makes PHPUnit refuse the whole configuration and report nothing — and it was introduced by hand twice in one session. It is proven able to fail (an injected `--` plus a port mismatch yields 2 failures, with the file restored byte for byte), it pins runner/engine agreement on database, user, password and port, and it pins the health gate the acceptance criterion reads. Axis A11 of plan #12 holds that a guard which cannot fail is worse than none, so a guard that can fail, over a defect observed twice, is not optional surface. |
| 6. Append the findings to #22, #24 and #21, and close what is closed | **Done, and verified against the issues rather than assumed:** #22 is closed with the executed acceptance criterion, #24 with the end-to-end delivery note, and #21 received the handover. |
| 7. Repair WSL for the container half | **Resolved by item 1.** The repair turned out to be a firmware setting, not a repository change. |

Two notes belong to this document rather than to the list above.
"## Next step per plan #12 §3" is **superseded**: A2's MySQL half is no longer
blocked, it was measured on both engines with an identical distribution, and its
fixture layers shipped in #42 and #48. And the two numerals the evidence log
declares non-reproducible stay declared as such, because the mechanisms behind
them were confirmed independently by verification pass 2.
