# Task: Audit phase 0 — make the baseline executable (issues #22, #24)

Status: both work units committed on `chore/audit-phase0-baseline`. One half of
#22's acceptance criterion is unverified for an environment reason, and three
decisions are waiting on the repository owner; both are named at the end.

Phase 0 of plan #12 (`docs/mejoras/12-programa-auditoria-integral-2026-08.md` §3)
is axis **A1** (#24), a single reproducible baseline. #24 states in its own body
that its MySQL half depends on **#22**, so #22 went first.

## Work units

| # | Issue | Deliverable | Commit |
| --- | --- | --- | --- |
| WU-1 | #22 | `docker-compose.yml`, `phpunit.mysql.xml` pinned to it, `tests/Unit/Tooling/MySQLTestEngineContractTest.php` | `6950208` |
| WU-2 | #24 | `scripts/audit/baseline.sh` | `13824e0` |

Independent verification of WU-1 exists (agent `gentle-ai-verify`, task
`mu5ta5um-1-mta5`): CI mirror fidelity, pin effectiveness, override escape hatch,
XML/YAML validity and port freedom all CONFIRMED; the container half UNVERIFIED;
three defects reported, all addressed in the table below. It also recorded that
the tree was not frozen while it verified, which this branch's commits now fix.

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
- **D4 — the acceptance criterion is verified only as far as the environment
  allows.** `docker compose config` is not a container run; the artifact says so.
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

## Unverified, blocked, or waiting on the owner

1. **Unverified (environment, not repository):** `docker compose up -d mysql`,
   the `healthy` state, and a real MySQL 8.0 suite run. The compose file is
   valid and resolves; whether the image pulls and the health gate passes cannot
   be shown on a machine whose Docker engine cannot start.
2. **Handover to #21:** `AGENTS.md` §6/§8 still tell the reader to run
   `php artisan test --group=mysql`, which executes on SQLite. #22 does not
   authorise editing `AGENTS.md`.
3. **For #18:** the MySQL runner reports 71 failures against MariaDB 10.4 (and the
   default runner 45 on SQLite). One is identified: `AuditLogMigrationTest:74`
   asserts the migration source still contains `->after('user_agent')`, an
   engine-independent assertion that fails on a real engine too.
4. **Owner decision:** whether the guard test (D5) stays, given #22 did not ask
   for it.
5. **Owner decision:** whether to append these findings to the issue bodies
   (#22, #24) and close them, or leave that to the repository owner.

## Next step per plan #12 §3

Phase 1 is **A2 (#18), A4 (#26), A3 (#25)**, in that order of defect yield. A2 can
now group its failures by root class using the baseline's MySQL half, which is
blocked on the same environment blocker as item 1.
