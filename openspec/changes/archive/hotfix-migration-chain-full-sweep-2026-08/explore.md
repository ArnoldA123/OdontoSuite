# explore — hotfix-migration-chain-full-sweep-2026-08

**Phase**: explore (synthesis of 4-lens sweep)
**Trigger**: NEW-001, NEW-002, NEW-003 found sequentially; user-approved full-sweep strategy
**Stack on top of**: `d811f1a` (hotfix-audit-log-immutable-2026-08), `d4f34b2` (hotfix-migration-eloquent-softdeletes-2026-08) — both source-correct, unlanded.

---

## 1. Severity-ordered canonical findings (deduplicated)

### Blocker (1)

| ID | Lenses | Migration | One-line | Fix shape |
|---|---|---|---|---|
| **NEW-003** | A, B (ref), C (ref), **D (empirical)** | `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` | Re-adds `reminder_schedules.error_message` already created by `2025_09_20_082355_create_reminder_schedules_table` line 22. Aborts `migrate:fresh --seed` on fresh MySQL with `SQLSTATE[42S21]: Duplicate column name 'error_message'`. The companion `channel` add is legitimately new and must be retained. | **`Schema::hasColumn` guard** wrapping both `channel` and `error_message` `add()` calls in `up()`, mirrored in `down()` via `array_filter` + `hasColumn`. Idempotent pattern matches the `2025_10_25_030052` precedent set in d4f34b2. |

**Override of lens proposed_fix_shape**: Lens A proposed "drop duplicate". **Rejected.** Dropping the entire `error_message` line would regress the seed-time error backfill (no `error_message` column would exist if `up()` is the only path that creates it on a fresh chain — but here the base `2025_09_20_082355` already creates it, so functionally drop-vs-guard are equivalent). However, the `hasColumn` guard is **safer for replay** (idempotent for partial-state databases after a prior failed run) and matches the project convention established in d4f34b2. Keep `channel` add; guard `error_message`. **Lens D's proposed_fix_shape accepted.**

### Out-of-scope (tech debt, all predating the 2026-08-05 SddCheckMigrationsTest guard cutoff)

These are real defects per their lenses but are **explicitly documented AGENTS.md §6 tech debt** and do **not** block the canonical MySQL gate (verified empirically by Lens D against `odontosuite_migtest`). They are routed to a future separate change.

| Canonical ID | Lenses | Migration | Class | Severity |
|---|---|---|---|---|
| **NEW-A01** | A, B (as `NEW-LENSB-003`) | `2026_06_07_001200_make_odontogram_records_color_nullable.php` | `->change()` on `odontogram_records.color` (length 7 → 32 + nullable toggle) without driver guard | high |
| **NEW-A02** | A, B (as `NEW-LENSB-007`) | `2026_06_13_140001_change_gateway_config_to_text.php` | Raw `MODIFY gateway_config TEXT` without driver guard | high |
| **NEW-A03** | A, B (as `NEW-LENSB-004`) | `2026_06_07_002000_add_proposed_to_treatment_plan_items_status.php` | Raw `MODIFY COLUMN ... ENUM(...)` without driver guard | medium |
| **NEW-A04** | A, B (as `NEW-LENSB-001`) | `2025_10_24_201039_make_reminder_template_id_nullable_in_reminder_schedules_table.php` | `->change()` nullability toggle without driver guard | medium |
| **NEW-LENSB-002** | B | `2025_10_25_030053_add_additional_performance_indexes.php` | `indexExists()` helper queries `information_schema.statistics` (MySQL-only) | medium |
| **NEW-LENSB-005** | B | `2026_06_10_100100_add_specialty_id_to_procedure_catalog_table.php` | `UPDATE ... INNER JOIN` MySQL-only backfill | medium |
| **NEW-LENSB-006** | B | `2026_06_10_100200_create_user_specialties_table.php` | `INSERT ... SELECT ... NOW()` MySQL-only backfill | medium |
| **NEW-A05** | A | `2025_10_25_030053_add_additional_performance_indexes.php` | Redundant non-unique indexes over already-unique columns (`patients.document_number`, `patients.email`, `patients.phone`) | low |
| **NEW-004-SEEDER** | D | `database/seeders/DatabaseSeeder.php` (NOT a migration) | References `EnvironmentSeeder::class` which only lives in `database/seeders/_legacy/EnvironmentSeeder.php`. Aborts `migrate:fresh --seed` after migrations pass. | medium |

**All Lens A, B, C "severity high/medium" labels are valid as raw defect labels**, but they are out of scope for THIS change because:
1. They do **not** block the canonical MySQL gate (Lens D empirical proof on `odontosuite_migtest` MariaDB 10.4).
2. They **predate** the `SddCheckMigrationsTest` `GUARD_CUTOFF_PREFIX` (2026-08-05) and are explicitly scoped out by AGENTS.md §6.
3. Including them would balloon the change to 7+ commits, far over the 400-line budget.

---

## 2. Lens coverage map

| Lens | Coverage | New canonical findings |
|---|---|---|
| **A — column/table lifecycle** | 7 findings | NEW-003 (shared), NEW-A01..A05 (tech debt) |
| **B — Eloquent + driver** | 7 findings | NEW-LENSB-001..007 (tech debt); confirms NEW-002-era guards still hold (no new Eloquent-in-migration regressions) |
| **C — ordering/dependency** | 0 new findings | Confirms dependency graph intact; references NEW-001/NEW-002/NEW-003 only |
| **D — empirical dry-run** | 2 findings | NEW-003 (reproduces blocker on fresh DB), NEW-004-SEEDER (post-migration seeder crash, not a migration defect) |

**Deduplication actions taken**:
- `NEW-A01` ≡ `NEW-LENSB-003` → single canonical record.
- `NEW-A02` ≡ `NEW-LENSB-007` → single canonical record.
- `NEW-A03` ≡ `NEW-LENSB-004` → single canonical record.
- `NEW-A04` ≡ `NEW-LENSB-001` → single canonical record.
- `NEW-LENSB-002`, `NEW-LENSB-005`, `NEW-LENSB-006`, `NEW-A05` → single records each.
- `NEW-003` appears in A, B (reference), C (reference), D (empirical) → one canonical record, all four lenses acknowledge.

**Already-known defect references (do NOT re-report)**:
- NEW-001 (d811f1a) — referenced by Lens C only as a "do-not-touch" anchor.
- NEW-002 (d4f34b2) — referenced by Lens C; Lens B confirms no new Eloquent-in-migration regressions.

---

## 3. In-scope vs out-of-scope decision

### In-scope (THIS change)
- **NEW-003**: `Schema::hasColumn` guard for `error_message` (and `channel` for symmetry) in `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php`. Optionally also extend `tests/Unit/SddCheckMigrationsTest.php` with a new static guard `no_migration_re_adds_already_known_column` that scans migrations for column adds where the same column was created earlier in the chain without a `hasColumn` guard.

### Out-of-scope (route to separate changes)
- All `NEW-A01..A05` and `NEW-LENSB-001..007` items → `techdebt-migration-driver-guards-2026-08` (proposed; not opened).
- `NEW-A05` redundant-index cleanup → `techdebt-migration-index-cleanup-2026-08` (proposed; not opened).
- `NEW-004-SEEDER` → `hotfix-seeder-environment-seeder-2026-08` (proposed; not opened).
- Pre-existing SQLite test-env failures (~28-104) → already documented AGENTS.md §6; canonical MySQL gate is unaffected.

---

## 4. Commit grouping (minimum self-contained)

### Commit 1 — `fix(migration): guard reminder_schedules.error_message duplicate add`
**File**: `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php`
**Change**:
```php
// up()
if (! Schema::hasColumn('reminder_schedules', 'channel')) {
    $table->string('channel')->nullable()->after('status');
}
if (! Schema::hasColumn('reminder_schedules', 'error_message')) {
    $table->text('error_message')->nullable()->after('status');
}

// down()
$dropColumns = array_filter(
    ['channel', 'error_message'],
    fn ($col) => Schema::hasColumn('reminder_schedules', $col)
);
if (! empty($dropColumns)) {
    $table->dropColumn($dropColumns);
}
```
**Self-contained**: Yes. The chain from `migrate:fresh` through `migrate:fresh --seed` survives this commit alone (migrations pass; the EnvironmentSeeder crash is independent and out of scope).
**Risk**: Minimal. Pattern matches `2025_10_25_030052` precedent set in d4f34b2.

### Optional Commit 2 — `test(migration): guard against duplicate column adds`
**File**: `tests/Unit/SddCheckMigrationsTest.php`
**Change**: Add static check `no_migration_re_adds_already_known_column` that scans migration files (post-2026-08-05 only, mirroring `GUARD_CUTOFF_PREFIX`) for `$table->...->add(...)` patterns where a prior migration in the chain already creates the same column and no `Schema::hasColumn` guard wraps the add.
**Self-contained**: Yes. Independent test, no runtime impact.
**Risk**: Low. Mirrors existing test structure.

**Decision**: Include Commit 2 by default because the static guard prevents the exact failure mode that produced NEW-003 from recurring, and it costs ~30-50 LOC. If the user prefers the absolute minimum, drop Commit 2 — the migration fix alone is sufficient.

### Commits explicitly NOT included (and why)
- Driver-guard rewrites of `2025_10_24_201039`, `2026_06_07_001200`, `2026_06_07_002000`, `2026_06_10_100100`, `2026_06_10_100200`, `2026_06_13_140001` → these are documented AGENTS.md §6 tech debt, predating the guard cutoff. They would each be a separate commit with its own risk surface; bundling them with NEW-003 would explode the change.
- Redundant-index cleanup → separate change.
- EnvironmentSeeder fix → separate change (not a migration defect).

---

## 5. Change-level estimate

| Metric | Value | Notes |
|---|---|---|
| **Commits** | 1 (or 2 with optional test guard) | Self-contained; chain passes at each boundary |
| **Total LOC** | ~12 lines migration + ~40 lines test = ~52 LOC (single-commit: ~12) | Well under 400-line budget |
| **Review risk** | low | Idempotent guard pattern already established in d4f34b2; static guard mirrors existing `SddCheckMigrationsTest` structure |
| **Empirical proof** | Lens D verified on `odontosuite_migtest` (MariaDB 10.4, utf8mb4): with the `hasColumn` guard applied, all 108 migrations complete; final `reminder_schedules` schema has both `channel` and `error_message` present | Reproducible by running `DB_DATABASE=odontosuite_migtest php artisan migrate:fresh --no-interaction` |

**400-line budget verdict**: **No size:exception required.** The single-commit change is ~12 LOC; the two-commit variant is ~52 LOC. Both are well under the 400-line preflight budget. The earlier "expected to exceed 400 lines" estimate assumed broad tech-debt inclusion; the synthesis narrows scope to the empirically-confirmed MySQL blocker only.

---

## 6. Explicit out-of-scope list (for user confirmation)

| Item | Reason | Suggested future change |
|---|---|---|
| `NEW-A01` odontogram_records.color `->change()` no driver guard | Predates 2026-08-05 cutoff; tech debt AGENTS.md §6 | `techdebt-migration-driver-guards-2026-08` |
| `NEW-A02` / `NEW-LENSB-007` payment_methods MODIFY COLUMN no driver guard | Same | Same |
| `NEW-A03` / `NEW-LENSB-004` treatment_plan_items.status ENUM MODIFY no driver guard | Same | Same |
| `NEW-A04` / `NEW-LENSB-001` reminder_schedules.reminder_template_id `->change()` no driver guard | Same | Same |
| `NEW-LENSB-002` `indexExists()` `information_schema.statistics` MySQL-only | Same | Same |
| `NEW-LENSB-005` `UPDATE ... INNER JOIN` MySQL-only backfill (procedure_catalog.specialty_id) | Same | Same |
| `NEW-LENSB-006` `INSERT ... SELECT ... NOW()` MySQL-only backfill (user_specialties) | Same | Same |
| `NEW-A05` redundant non-unique indexes on patients.document_number/email/phone | Code smell; not a runtime defect | `techdebt-migration-index-cleanup-2026-08` |
| `NEW-004-SEEDER` EnvironmentSeeder reference resolves to `_legacy/` | Not a migration defect; breaks seed only | `hotfix-seeder-environment-seeder-2026-08` |
| Pre-existing ~28-104 SQLite test failures | Documented AGENTS.md §6; canonical MySQL gate unaffected | Already tracked separately |

---

## 7. Recommendation for sdd-apply

1. **Open this change as-is**, in-scope = NEW-003 only.
2. **Optional Commit 2** (static guard test) recommended — adopt unless user objects.
3. **Confirm out-of-scope list** above before opening the proposal/spec/tasks.
4. **No size:exception needed** despite the preflight flag — synthesis narrows scope to empirical blocker.
5. **Reuse the d4f34b2 idempotent-guard pattern** as the template for `hasColumn` usage in 2026_08_05_020000.
6. **Verification gate**: `tests/Feature/Database/MigrateFreshPortabilityTest.php` (MySQL-gated) must pass after the change. CI MySQL 8.0 is the canonical gate.

---

## 8. Open questions for the user (one at a time, per global rules)

None blocking. The synthesis can proceed to proposal/spec without further input. The only soft question is whether to include Commit 2 (static guard test) — recommend yes; default to yes if no objection.

---

## Provenance

- Lens A output: column/table lifecycle sweep
- Lens B output: Eloquent + driver sweep
- Lens C output: ordering/dependency sweep
- Lens D output: empirical dry-run on `odontosuite_migtest` (MariaDB 10.4, utf8mb4)
- Synthesis: 2026-08-05
