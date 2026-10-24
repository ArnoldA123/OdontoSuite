# Tooling guard follow-ups

Closes the two open issues of the tooling area: #360 (a fixture that would turn
the `security` job red the day someone touches it) and #359 (the reviewer's
suggestions on the personal-path guard plus the prose findings of the ODD import).
Delivery: one branch and one PR per issue, both cut from `main`, in a new
worktree `.worktrees/tooling-guard-followups`.

## Specs

- S1 — #360: the fixture in `tests/Unit/Tooling/AuditBaselineCheckTest.php` no
  longer publishes a value `project-db-password` can capture, and the test still
  proves the masking: it must fail if `mask_secrets()` stops masking. Expected
  output stays `DB_PASSWORD=***`.
- S2 — #360: `gitleaks dir tests/Unit/Tooling --config .gitleaks.toml` with the
  version CI pins (`8.30.1`) reports no finding.
- S3 — #359: `git grep` exits 1 when there is no match and ≥2 when it could not
  run; the guard reads 1 as green and fails on the rest with its own message,
  never reporting "a tracked file publishes a personal absolute path" for a
  command that did not run.
- S4 — #359: a positive control seeds a path in a tracked probe file
  (`git add`), proves the guard detects it, and leaves neither the file nor an
  index entry behind.
- S5 — #359: the pattern's self-exclusion contract is written down (why the
  pattern cannot match its own source text and what would break that), per
  `R2-self-pass-sentinel` / `R3-002`.
- S6 — #359: the prose findings of the ODD import are closed or discarded with a
  reason: `odd/tasks/appointment-types-audit.md:39-40` (`R2-001`),
  `odd/tasks/environments-audit.md:15-16` (`R2-002`),
  `odd/tasks/private-dashboard-channel.md:43-48`
  (`R1-doc-public-channel-fanout`) and `AGENTS.md` §12 (`R3-001`).
- S7 — Regression: `vendor/bin/phpunit --filter 'PersonalPathLeakGuardTest|AuditBaselineCheckTest'`
  and `vendor/bin/pint --test` green; `AGENTS.md` counts unchanged
  (`scripts/audit/doc-drift.mjs`).

## Tasks

- T1 · S1, S2 · inline · `fix/tooling-mask-fixture-secret` · `6a8d6d6`
- T2 · S3, S4, S5 · inline · `test/tooling-guard-exit-codes` · `06ad044`
- T3 · S6 · inline · `test/tooling-guard-exit-codes` · commit 3
- T4 · S7 · inline (verification) · —

## Log

L1. User request (verbatim): "has las issues restantes en un worktree nuevo"
L2. Inventory: only #360 and #359 are open, both `area:tooling`. Deliverable
decision follows the precedent of #183-#206: one branch and one PR per issue,
both cut from `main`, worked in a single new worktree.
L3. #359 item 4 says "las tres sugerencias de la tabla que son de redacción",
and the table carries four prose rows: three from #355 and one from #356. All
four are addressed under S6; the fourth (AGENTS §12, `R3-001`) is examined and
either fixed or discarded with its reason recorded below.
L4. The full text of each reviewer finding lives in the review receipt, and no
receipt on disk keeps it (`.git/gentle-ai` holds consumption records only). The
locations and lenses in #359 are the working input; each fix below cites the
finding id it closes.

L5. S6 disposition, one row per prose finding (no receipt on disk keeps the
finding text; the location and the lens in #359 are the input):
`R2-001` — the F12/F13 rows of `appointment-types-audit.md` read as open defects
while the same note closes F12 in T8 (`7900ca6`) and #154 wired the reminder
pipeline F13 called unreachable; each row now says so.
`R2-002` — `environments-audit.md` listed S9 between S7 and S8, so the section
read out of order; the two bullets swap places and no S# changes, so T7 keeps
pointing at S8.
`R1-doc-public-channel-fanout` — L4 of `private-dashboard-channel.md` left "21
events still publish to public channels"; #249 moved the remaining 27 to private
ones. Log entries are append-only, so L8 records the closure instead of
rewriting L4.
`R3-001` — the AGENTS §12 entry pointed at
`security-secret-gate.md:24,59,84`; the numbers no longer name the three
redactions and contradict the repository's own rule of naming symbols. The entry
now names T1 (paths), L2 (local key) and F3 (the fixture value).
L6. Evidence: `phpunit --filter` 4/4 and 14/14; RED in both directions (a
pattern that matches nothing fails the positive control with 2 failures; reading
`<= 1` as green fails the unreadable-tree case); the unit suite 1469 tests /
9678 assertions with the 51 documented skips; Pint and Prettier green;
`doc-drift` 14/14; `gitleaks dir tests/Unit/Tooling` clean.
