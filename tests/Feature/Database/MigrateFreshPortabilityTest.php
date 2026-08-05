<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * NEW-002 — CI gate proving `php artisan migrate:fresh --seed` is portable
 * on MySQL.
 *
 * The historical bug was that `2025_10_25_030052_add_document_number_to_patients_table.php`
 * called `\App\Models\Patient::whereNull(...)` from inside a migration. Eloquent
 * injected `and patients.deleted_at is null` into the WHERE clause, but the
 * `patients.deleted_at` column is added 8 months later in the chain. Fresh MySQL
 * databases aborted with `SQLSTATE[42S22] Column not found: 1054 Unknown column
 * 'patients.deleted_at'`.
 *
 * This test exercises the full chain end-to-end via Artisan on a real
 * `mysql` connection. The sibling `hotfix-audit-log-immutable-2026-08` test
 * (`AuditLogMigrationTest`) can ONLY pass once this gate is green.
 *
 * The test is intentionally skipped on SQLite because `MODIFY COLUMN` semantics
 * differ between MySQL and SQLite — running this on SQLite would not actually
 * exercise the bug. CI runs MySQL 8.0 (see AGENTS.md §6).
 *
 * @group mysql
 */
class MigrateFreshPortabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('migrate:fresh portability is MySQL-specific — covered by CI backend-tests job');
        }
    }

    /**
     * Asserts that `migrate:fresh --seed` exits 0 on MySQL and that the resulting
     * schema contains the columns and tables that the entire dependency chain
     * depends on (including `patients.deleted_at` and `audit_logs`, which are
     * the unblock criteria for the sibling `hotfix-audit-log-immutable-2026-08`).
     *
     * @test
     */
    public function migrate_fresh_creates_document_number_deleted_at_and_audit_logs(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();

        $this->assertTrue(
            Schema::hasColumn('patients', 'document_number'),
            'patients.document_number column must exist after migrate:fresh'
        );
        $this->assertTrue(
            Schema::hasColumn('patients', 'deleted_at'),
            'patients.deleted_at column must exist after migrate:fresh'
        );
        $this->assertTrue(
            Schema::hasTable('audit_logs'),
            'audit_logs table must exist after migrate:fresh'
        );
    }
}
