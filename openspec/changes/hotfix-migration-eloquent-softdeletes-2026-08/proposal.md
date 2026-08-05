# Proposal: hotfix-migration-eloquent-softdeletes-2026-08

## Intent

Remediate NEW-002: `php artisan migrate` (and `migrate:fresh --seed`) is unrunnable on **any** clean MySQL database. The migration `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:19` calls `\App\Models\Patient::whereNull('document_number')` to backfill data. The model uses `SoftDeletes` (`app/Models/Patient.php:13`), so Eloquent injects `and patients.deleted_at is null` — but `patients.deleted_at` is not created until 8 months later in the chain (`2026_06_11_001034_add_soft_deletes_to_patients_table`). The migration was correct when written and broke retroactively when `SoftDeletes` was added to the model. Classic "never reference Eloquent models inside migrations" violation.

Without this fix: no new developer or CI scratch job can bootstrap the database, the sibling `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`) cannot land `audit_logs.is_immutable` in any schema, and browser QA of Especialidades, Recordatorios, Audit Log and Planes de Tratamiento stays blocked because their tables/columns do not exist.

## Scope

### In Scope
- Replace `\App\Models\Patient::whereNull(...)->orWhere(...)->get()` with `DB::table('patients')->whereNull('document_number')->orWhere('document_number', '')->get()` in `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php`.
- Convert the backfill loop to operate on stdClass rows (no `$patient->update()`), preserving the `DOC-{8-digit padded id}` format.
- Extend `tests/Unit/SddCheckMigrationsTest.php` with a new guard: fail if any migration in `database/migrations/` references `App\Models\` or `use App\Models\` (scope: all migrations, including pre-2026-08-05 historical — the regression class is fixable in one place, so the guard should be global).
- Add a feature test `tests/Feature/Database/MigrateFreshPortabilityTest.php` that asserts `php artisan migrate:fresh --seed` exits 0 against MySQL (CI gate).
- Single PR, ≤40 LOC.

### Out of Scope
- Fixing the dev DB drift (live `odontosuite` schema is half-applied; 45 migrations Pending). Operational follow-up, not code change.
- Resolving the 28 pre-existing SQLite local-test failures (documented AGENTS.md §6 tech debt; CI is MySQL).
- Modifying any other migration file (`grep -rln "Models" database/migrations/` matches exactly one).
- Changing the `SoftDeletes` trait on `Patient`, the sibling `hotfix-audit-log-immutable-2026-08`, or any archived migration in `bugfix-2026-08`.

## Capabilities

### New Capabilities
None — this is a migration-portability fix; behavior of the patient document_number column is unchanged.

### Modified Capabilities
None at the spec level. The migration produces the same schema and same data; only the implementation switches from Eloquent to Query Builder.

## Approach

| Step | File | Action |
|---|---|---|
| 1 | `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` | Replace the Eloquent query with `DB::table('patients')`; iterate rows as `stdClass`; write `document_number` via `DB::table('patients')->where('id', $row->id)->update(['document_number' => …])`. |
| 2 | `tests/Unit/SddCheckMigrationsTest.php` | Add test `no_migration_references_eloquent_models()` scanning all `database/migrations/*.php` for `\App\Models\` or `use App\Models\`; report filename + line. |
| 3 | `tests/Feature/Database/MigrateFreshPortabilityTest.php` (new) | Bootstrap-ish test gated on MySQL: `migrate:fresh` exits 0; `Schema::hasTable('patients')` and `Schema::hasColumn('patients', 'deleted_at')` both true. |
| 4 | CI | Re-run `migrate:fresh --seed` job; expect green. |

**Editing an already-shipped migration is acceptable here** because the file is provably unrunnable on any fresh database: `grep -rln 'Models' database/migrations/` matches exactly one file, and the dev DB still reports it as Pending (no environment has it recorded as Ran). If a partial apply is later discovered, `git revert` + manual `ALTER TABLE` is the only fallback.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` | Modified | `App\Models\Patient` → `DB::table('patients')`; `$patient->update(...)` → `DB::table('patients')->where('id', ...)->update(...)`. |
| `tests/Unit/SddCheckMigrationsTest.php` | Modified | New guard: no `App\Models\` references in any migration. |
| `tests/Feature/Database/MigrateFreshPortabilityTest.php` | New | `migrate:fresh` exits 0 on MySQL. |
| CI pipeline (`migrate:fresh --seed` job) | Implicit | Now green on clean checkout. |
| `hotfix-audit-log-immutable-2026-08` (sibling) | Unblocked | `audit_logs.is_immutable` can land once this lands. |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Hidden second migration referencing Eloquent (grep blind spot) | Very Low | Source-comment grep across `database/migrations/` is exhaustive; the new SddCheckMigrationsTest guard makes future regressions impossible. |
| The `deleted_at` column still missing on a partially-applied DB | Med | Migration no longer depends on `deleted_at`; uses raw DB. If a host ran the broken file once, the half-applied schema is unaffected by this edit because `down()` only drops `document_number`. |
| StdClass payload differs from Eloquent (accessors, casts) | Low | Backfill only reads `id` and writes a string; no casts/accessors were in play. |
| Reproducing the bug locally on SQLite | N/A | SQLite test env never referenced `deleted_at` because the trait is MySQL-driven; the bug only triggers on MySQL. CI runs MySQL. |
| Dev DB drift (45 Pending) blocks verification | Med | Out of scope; verification uses a fresh scratch DB (`odontosuite_migtest` pattern). Documented as operational follow-up. |

## Rollback Plan

`git revert <sha>` of the hotfix commit. `down()` still calls `dropColumn('document_number')`; no data loss (the backfill writes a deterministic `DOC-{padded-id}` string that can be regenerated). The SddCheckMigrationsTest guard is additive and removable by literal `git revert`.

## Dependencies

- Sibling: `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`) — its `migrate:fresh --seed` acceptance criterion is currently unsatisfiable. This change must land first.
- Parent: `bugfix-2026-08` (archived) — this is the second extracted hotfix from the same audit cycle.
- No new packages. CI MySQL 8.0 service remains the canonical gate.

## Success Criteria

- [ ] `php artisan migrate:fresh --seed` exits 0 on a clean MySQL DB.
- [ ] `php artisan test` exits 0 (no regression on the 178 passed / 28-104 pre-existing SQLite fails).
- [ ] `pnpm lint:check && pnpm build` exits 0.
- [ ] `tests/Unit/SddCheckMigrationsTest.php` → new `no_migration_references_eloquent_models` test passes and would fail if any future migration re-introduces `App\Models\`.
- [ ] `tests/Feature/Database/MigrateFreshPortabilityTest.php` passes on MySQL.
- [ ] `grep -rln 'App.Models' database/migrations/` returns 0 files.
- [ ] Sibling `hotfix-audit-log-immutable-2026-08` `migrate:fresh --seed` acceptance criterion becomes satisfiable (verified by running it after this hotfix).
