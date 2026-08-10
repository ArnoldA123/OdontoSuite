# Proposal: hotfix-migration-chain-full-sweep-2026-08

## Intent

Remediate **NEW-003**: `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` line 21 unconditionally re-adds `reminder_schedules.error_message`, but the base migration `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php` line 22 already creates that column. On any clean MySQL/MariaDB chain, `migrate:fresh` aborts with `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'`.

This is the third defect in the migration chain found sequentially (after NEW-001 in `d811f1a` and NEW-002 in `d4f34b2`). The companion `channel` add in the same migration is legitimately new and must be retained. The fix is idempotent and partial-state-safe: wrap both `addColumn` calls in `Schema::hasColumn(...)` guards and mirror the guard in `down()` via `array_filter(...) + hasColumn`, matching the pattern already shipped in `d4f34b2` (NEW-002 precedent at `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:35`).

Without this fix, no developer or CI scratch job can bootstrap the database; the sibling `hotfix-audit-log-immutable-2026-08` (NEW-001, `d811f1a`) and `hotfix-migration-eloquent-softdeletes-2026-08` (NEW-002, `d4f34b2`) commits hold without re-application because the chain aborts before reaching them.

## Scope

### In Scope

- **Commit 1 — fix**: wrap `up()` `channel` and `error_message` `add()` calls in `Schema::hasColumn(...)` guards; wrap `down()` `dropColumn(...)` in `array_filter([...], fn ($c) => Schema::hasColumn(...))` in `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php`. No other file changes in this commit.
- **Commit 2 — guard test**: add `no_migration_re_adds_already_known_column` test method to `tests/Unit/SddCheckMigrationsTest.php`. Mirrors the existing `no_migration_references_eloquent_models` pattern: scans ALL migrations (no `GUARD_CUTOFF_PREFIX` gate), reports filename + offending column, fails if any `->addColumn(...)` / `->string(...)` / `->text(...)` etc. appears without a preceding `Schema::hasColumn(...)` guard inside the same `Schema::table(...)` closure. Strips strings + comments before matching to avoid false positives.
- ~52 LOC total (≈12 migration + ≈40 test), 2 commits, well under the 400-line review budget. NO size:exception required.

### Out of Scope

- **9 documented AGENTS.md §6 tech-debt items** (NEW-A01..A05, NEW-LENSB-001..007): driver-guard gaps on `->change()` / raw `MODIFY COLUMN` / `DATE_SUB` / `UPDATE ... INNER JOIN` / `INSERT ... SELECT ... NOW()` / `indexExists()` information_schema. All predate the `2026_08_05` `SddCheckMigrationsTest::GUARD_CUTOFF_PREFIX`. Do not block the canonical MySQL gate (Lens D empirical proof on `odontosuite_migtest`). Route to a future `techdebt-migration-driver-guards-2026-08`.
- **NEW-A05 redundant non-unique indexes** on `patients.document_number`, `patients.email`, `patients.phone`: code smell, not a runtime defect. Route to `techdebt-migration-index-cleanup-2026-08`.
- **NEW-004-SEEDER**: `DatabaseSeeder` references `EnvironmentSeeder::class` that only lives in `database/seeders/_legacy/`. Not a migration defect; aborts `migrate:fresh --seed` AFTER the migrations pass. Route to `hotfix-seeder-environment-seeder-2026-08`.
- **Sibling `hotfix-migration-eloquent-softdeletes-2026-08`** (NEW-002, `d4f34b2`): currently `state: blocked, reason: maintainer_decision` per the runtime ledger. Unrelated to NEW-003 and not unblocked by this change; its apply decision remains separate.
- **Pre-existing ~28-104 SQLite local-test failures**: documented AGENTS.md §6; CI MySQL 8.0 is the canonical gate and is unaffected.
- **Sibling `hotfix-audit-log-immutable-2026-08`** (NEW-001, `d811f1a`): already source-correct, unlanded; this change does not modify that file.

## Capabilities

### New Capabilities

None. The migration already adds `channel` and `error_message` to `reminder_schedules`; this change only makes the addition idempotent.

### Modified Capabilities

None at the spec level. `reminder_schedules` final schema is identical (both columns present, nullable, same `after(...)` positions).

## Approach

**Fix shape** — `Schema::hasColumn` guards (chosen over the static-analysis-recommended "drop duplicate line"):

```php
// up()
Schema::table('reminder_schedules', function (Blueprint $table) {
    if (! Schema::hasColumn('reminder_schedules', 'channel')) {
        $table->string('channel', 20)->nullable()->after('scheduled_at');
    }
    if (! Schema::hasColumn('reminder_schedules', 'error_message')) {
        $table->text('error_message')->nullable()->after('status');
    }
});

// down()
Schema::table('reminder_schedules', function (Blueprint $table) {
    $cols = array_values(array_filter(
        ['channel', 'error_message'],
        fn ($c) => Schema::hasColumn('reminder_schedules', $c)
    ));
    if ($cols) {
        $table->dropColumn($cols);
    }
});
```

**Why `hasColumn` over drop-duplicate**: (1) **idempotent** — replay-safe on a partially-applied DB where the migration previously failed after creating `channel` but before the `error_message` line; (2) **partial-state safe** — the `down()` filter prevents `dropColumn` from raising on a column that was never added; (3) **convention precedent** — `d4f34b2` (`database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:35`) already uses this exact shape and the `no_migration_references_eloquent_models` test enforces it; (4) **d4f34b2's lens-A static proposal** to drop the duplicate line was rejected because it regresses replay safety and breaks the project convention.

**Guard test shape** — mirrors `no_migration_references_eloquent_models`:

```php
/** @test */
public function no_migration_re_adds_already_known_column(): void
{
    // Scan ALL migrations (no GUARD_CUTOFF_PREFIX): same reasoning as the
    // Eloquent guard — the regression is retroactive. Strip strings/comments
    // before matching. Fail if a column-add call appears without a
    // Schema::hasColumn(...) guard in the same Schema::table(...) closure.
}
```

The test scans `allMigrations()` (already defined at line 70 of `SddCheckMigrationsTest.php`), not `guardedMigrations()`, because the bug class is retroactive — the offending column was created by a pre-2026-08-05 migration, so a date-prefix gate would have missed it.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` | Modified | `up()` and `down()` wrapped in `Schema::hasColumn` guards. Schema output identical. |
| `tests/Unit/SddCheckMigrationsTest.php` | Modified | New `no_migration_re_adds_already_known_column` static guard. |
| CI pipeline (`migrate:fresh --seed` job) | Implicit | Chain now completes all 108 migrations on clean MySQL/MariaDB. |
| `d811f1a` (NEW-001 sibling) | Unblocked | `audit_logs.is_immutable` can finally land in a schema. |
| `d4f34b2` (NEW-002 sibling) | Unblocked | `patients.document_number` backfill now reaches a schema. |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| `Schema::hasColumn` returns false on a database where the column DOES exist (driver metadata lag, e.g. SQLite in-memory) | Very Low | Laravel's `Schema::hasColumn` issues `SHOW COLUMNS` / `PRAGMA table_info` and is the project-canonical check; used safely in `d4f34b2` line 35 and in the `2026_08_05_000000_add_audit_log_immutable.php` precedent. |
| Guard test false-positives on legitimate first-add columns | Low | The test scans for an unguarded add where the SAME column name also appears as a CREATE in any earlier migration in the chain. First-add columns (no prior CREATE) pass. |
| Guard test false-negatives from comment/string stripping failure | Low | Same regex array (`$stripPatterns`) as the Eloquent guard, which is empirically battle-tested. |
| New static guard breaks the 28-104 pre-existing SQLite fails baseline | Low | The test uses pure string scanning — no DB connection required. Runs cleanly on SQLite in-memory. |
| Half-applied dev DB has `channel` but no `error_message` (or vice versa) | Med | Both columns are individually guarded; `down()` filters out whichever is missing. Partial state converges on next run. |
| Sibling change `hotfix-migration-eloquent-softdeletes-2026-08` apply decision still blocked | Low | Independent of this change; tracked separately. The `migrate:fresh` gate is now satisfiable after THIS change lands; NEW-002's gate was also satisfied by `d4f34b2` directly. |

## Rollback Plan

`git revert <sha>` of each commit in reverse order (test commit first, then fix commit). Because `down()` only calls `dropColumn` for columns that actually exist (filtered), rollback is safe on a partial-state DB. The new static guard test is additive and removable by literal `git revert`. No data loss: both columns are nullable with no production writers (the slice ships the column for an upcoming consumer, not a present one).

## Dependencies

- **Sibling on-disk (unlanded)**: `d811f1a` (NEW-001, `hotfix-audit-log-immutable-2026-08`) and `d4f34b2` (NEW-002, `hotfix-migration-eloquent-softdeletes-2026-08`). Both already pass on-disk static analysis; both were unrunnable on a fresh chain because NEW-003 aborted first.
- **Parent**: `bugfix-2026-08` (archived) introduced the offending migration as slice 03 / T-03.6.
- **Pattern precedent**: `d4f34b2` `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:35` — the `hasColumn` shape and the `no_migration_references_eloquent_models` test template.
- No new packages. CI MySQL 8.0 service remains the canonical gate.

## Success Criteria

- [ ] `php artisan migrate:fresh` exits 0 on clean MySQL 8.0 (verified on `odontosuite_migtest` per Lens D empirical dry-run).
- [ ] All 108 migrations complete; final `reminder_schedules` schema has `channel` (varchar(20) nullable) and `error_message` (text nullable) present.
- [ ] `tests/Unit/SddCheckMigrationsTest.php::no_migration_re_adds_already_known_column` passes against the post-fix tree.
- [ ] `tests/Unit/SddCheckMigrationsTest.php::no_migration_references_eloquent_models` (NEW-002 guard) still passes.
- [ ] `php artisan test` exits 0 excluding the documented 28-104 pre-existing SQLite fails.
- [ ] Reintroducing the un-guarded line in `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` makes the new static guard fail with filename + line.
- [ ] `d811f1a` and `d4f34b2` hold without re-application on the same clean chain.