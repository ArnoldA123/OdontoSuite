# Slice 01 — Fix audit_logs.is_immutable migration anchor

## T-01.1 — Replace `description` with `user_agent` in migration

**File**: `database/migrations/2026_08_05_000000_add_audit_log_immutable.php`
**Action**: Modify

Change line:
```php
$table->boolean('is_immutable')->nullable()->default(false)->after('description');
```
to:
```php
$table->boolean('is_immutable')->nullable()->default(false)->after('user_agent');
```

Acceptance criteria:
- [x] Migration file diff: only the `->after(...)` clause changed (a docblock explaining the hotfix was added alongside the anchor change; executable code is otherwise identical)
- [x] `php artisan migrate:status` reports the migration as pending (not run) — verified before apply; after apply on dev MySQL it shows `Ran` in batch 9
- [x] No other lines modified (only the anchor's string argument and a top-of-file docblock were added; the `up()` / `down()` body, type, nullability, default, and dropColumn all unchanged)

## T-01.2 — Add migration-portability feature test

**File**: `tests/Feature/Api/AuditLogMigrationTest.php` (new)
**Action**: Create

Write a feature test that:
- [x] Runs `php artisan migrate` against the test DB
- [x] Asserts `Schema::hasColumn('audit_logs', 'is_immutable') === true`
- [x] Asserts `Schema::hasColumn('audit_logs', 'description') === false` (regression guard: the bad anchor never existed)
- [x] Asserts `Schema::hasColumn('audit_logs', 'user_agent') === true` (the new anchor exists)
- [x] Uses `RefreshDatabase` trait; marks `@group mysql` per project SQLite-tech-debt convention

Acceptance criteria:
- [x] Test passes on MySQL CI (CI gate: `php artisan test` against the `odontosuite_test` MySQL service — see CI workflow `backend-tests` job)
- [x] Test follows existing SddCheckMigrationsTest style (string-stripping regex mirrors `SddCheckMigrationsTest::methodBody`, `@group mysql` matches existing convention)
