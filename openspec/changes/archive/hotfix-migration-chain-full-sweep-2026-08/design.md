# Design: hotfix-migration-chain-full-sweep-2026-08

## Technical Approach

Two self-contained commits that together remediate NEW-003 (chain-blocking duplicate column add) and prevent recurrence. Commit A wraps the two `addColumn` calls in `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` with `Schema::hasColumn(...)` guards and mirrors them in `down()` via `array_filter + hasColumn`. Commit B adds a static guard test (`no_migration_re_adds_already_known_column`) in `tests/Unit/SddCheckMigrationsTest.php` that scans all migrations for unguarded re-adds. No runtime, schema, or API surface changes; the only observable effect is that `migrate:fresh` now completes all 108 migrations on a clean MySQL/MariaDB chain.

## Architecture Decisions

### Decision 1: `Schema::hasColumn` guard vs. drop-duplicate-line

| Option | Tradeoff | Decision |
|---|---|---|
| `hasColumn` guard (current on-disk state, d4f34b2 precedent) | Idempotent on half-applied DBs; matches project convention; no semantic loss | **Chosen** |
| Drop the `error_message` line | Functionally equivalent on a fresh chain; regresses replay-safety for dev DBs that previously failed mid-line; breaks d4f34b2 precedent | Rejected (lens A originally proposed; lens D + synthesis overrode) |

### Decision 2: Guard shape with two adds → two guards

The `Schema::table(...)` callback contains two `addColumn` calls (`channel` legitimately new, `error_message` duplicate). Each must be independently guarded because a half-applied state can leave one column present and the other absent. The `down()` mirrors with `array_filter(['channel','error_message'], fn ($c) => Schema::hasColumn(...))` plus a non-empty guard before `dropColumn($cols)`. Shape matches `2025_10_25_030052_add_document_number_to_patients_table.php` lines 35-39 (precedent set in d4f34b2).

### Decision 3: Guard test scope — ALL migrations, no `GUARD_CUTOFF_PREFIX`

The existing Eloquent guard (`no_migration_references_eloquent_models`) deliberately bypasses the 2026_08_05 cutoff because the bug class is retroactive. NEW-003 is identical in shape: the offending `error_message` add is dated 2026_08_05, but the `error_message` CREATE it duplicates is dated 2025_09_20 — the regression window is any post-cutoff migration that re-adds a column already created earlier in the chain. Using `allMigrations()` (the helper at `SddCheckMigrationsTest.php:70`) keeps the two retroactively-scoped guards structurally consistent.

### Decision 4: Test body — line-by-line walk, not regex over whole file

A regex over the whole source cannot express "an `addColumn` inside a `Schema::table(...)` closure that lacks a `hasColumn` guard in the same closure" without a full parser. The chosen shape walks lines, strips strings + comments with the same `$stripPatterns` array used by the Eloquent guard (empirically battle-tested), and flags the offending `filename:line` for reintroduction detection. Detection rule: a line containing `->string(`, `->text(`, `->addColumn(`, `->integer(`, `->bigInteger(`, `->json(`, `->dateTime(`, `->timestamp(`, `->boolean(`, `->foreignId(` (column-add call shapes) where the same `Schema::table(...)` closure (matched by brace counting) does not contain a `Schema::hasColumn(` guard for that column.

### Decision 5: Two-commit order — fix first, test second

| Commit | State after commit |
|---|---|
| A (fix) | Migration runs cleanly on `migrate:fresh`; chain passes; no test enforces it yet |
| B (guard) | Static guard active; passes against A's fixed tree; would fail against pre-A broken tree |

**Justification**: applying the guard test before the fix would make CI red on the broken file (correct behavior), but breaks the "each commit leaves the tree in a green state" contract. Fix-first is the only order that satisfies: (1) chain green at every boundary; (2) test in commit B demonstrably catches the pre-A state if reintroduced. If commit B is reverted alone, commit A still leaves the tree green (idempotent guard, no static enforcement). If commit A is reverted alone, commit B's guard test fails immediately on the reintroduced un-guarded line — making the regression self-blocking on the very next CI run.

## Data Flow

N/A — this is a migration-chain fix; no data flow changes. The chain progresses as:

    2025_09_20_082355 (CREATE reminder_schedules incl. error_message)
         │
         ▼
    ...other migrations...
         │
         ▼
    2026_08_05_020000 (Schema::hasColumn check — skip if column already present)
         │
         ▼
    rest of chain completes (incl. d811f1a audit_logs.is_immutable, d4f34b2 patients.document_number backfill)

## File Changes

| File | Action | Description |
|---|---|---|
| `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` | Modify (Commit A) | Wrap `up()` and `down()` with `Schema::hasColumn` guards per Decision 2. ~12 LOC. |
| `tests/Unit/SddCheckMigrationsTest.php` | Modify (Commit B) | Add `no_migration_re_adds_already_known_column` method. ~40 LOC. |

## Interfaces / Contracts

**Migration contract** (unchanged to runtime consumers):

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

**Static guard test shape** (mirrors `no_migration_references_eloquent_models`):

```php
/** @test */
public function no_migration_re_adds_already_known_column(): void
{
    $violations = [];
    $stripPatterns = [
        '/\/\/.*$/m',
        '/\/\*.*?\*\//s',
        "/'(?:\\\\.|[^'\\\\])*'/s",
        '/"(?:\\\\.|[^"\\\\])*"/s',
    ];
    $addColumnCallShapes = '/->(?:string|text|integer|bigInteger|json|dateTime|timestamp|boolean|foreignId|addColumn)\s*\(/i';

    foreach (self::allMigrations() as $file) {
        $name = basename($file);
        $source = file_get_contents($file);
        if ($source === false) continue;
        $lines = explode("\n", $source);

        // Collect CREATE columns per table from earlier migrations to know
        // whether the column-being-added was already known.
        // (Walk all migrations once; track created columns per table.)

        // For each line, if it is a column-add call shape and the closure it
        // lives in (brace-counted forward to matching `})`) does not contain
        // a Schema::hasColumn(<table>, <column>) guard, fail with
        // ":<lineNumber> adds column '<col>' without hasColumn guard".
    }

    $this->assertSame([], $violations, "...");
}
```

## Testing Strategy

| Layer | What | How |
|---|---|---|
| Static guard | Migration pattern correctness | New `no_migration_re_adds_already_known_column` unit test in `SddCheckMigrationsTest.php`; pure string scan, runs on SQLite in-memory |
| Migration gate | End-to-end chain on clean MySQL 8.0 | Existing CI `backend-tests` job: `php artisan migrate:fresh --force` then `php artisan test`; expected 0 fails excluding documented AGENTS.md §6 SQLite debt |
| Reintroduction proof | Guard test catches un-guarded line | Local: revert `hasColumn` wrapper in 2026_08_05_020000 → re-run `php artisan test --filter no_migration_re_adds_already_known_column` → expect failure with `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php:<line>` |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary changes. Pure migration PHP and PHPUnit string-scan test.

## Migration / Rollout

**Apply sequence** (apply agent on clean MySQL 8.0 scratch DB `odontosuite_migtest`):

```
git checkout -b fix/migration-new-003-2026-08
# Apply commit A
git add database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php
git commit -m "fix(migration): guard reminder_schedules.error_message duplicate add"
# Verify commit A alone makes the chain pass
DB_DATABASE=odontosuite_migtest php artisan migrate:fresh --force
# Apply commit B
git add tests/Unit/SddCheckMigrationsTest.php
git commit -m "test(migration): guard against unguarded column re-adds"
# Verify both commits
php artisan test --filter SddCheckMigrationsTest
DB_DATABASE=odontosuite_migtest php artisan migrate:fresh --force
```

**Empirical proof** (Lens D, already executed): pre-fix chain aborts at `2026_08_05_020000` with `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'`. Post-fix chain completes all 108 migrations; final `reminder_schedules` schema verified to contain `channel VARCHAR(20) NULL` and `error_message TEXT NULL` at expected `after(...)` positions.

**Rollback**:

| Revert order | State | Verdict |
|---|---|---|
| Revert A first (then B) | B's test still passes (no migration file re-adds a known column); chain breaks again | OK — symmetric revert, no orphaned state |
| Revert B first (then A) | A's fix present, no test enforcement; chain still passes | OK |
| Revert A only | Guard test fails immediately on the reintroduced un-guarded line | Self-blocking on next CI run |
| Revert B only | Fix present; idempotent guard still works; no regression | OK |

## Open Questions

None blocking. Both commits are self-contained; the only open question (whether to include commit B) was resolved by user decision: **apply both**.
