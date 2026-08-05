<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Slice 01 / T-01.2 — migration-portability regression guard for
 * database/migrations/2026_08_05_000000_add_audit_log_immutable.php.
 *
 * The hotfix replaced `->after('description')` (column absent from base
 * `audit_logs` schema) with `->after('user_agent')` (existing nullable text
 * column). On MySQL the broken anchor produced SQLSTATE[42S22] and blocked
 * `migrate:fresh --seed` on every clean setup. SQLite silently drops the
 * `after()` clause from generated SQL, so the runtime Schema assertions
 * alone cannot reproduce the failure locally. The migration source check
 * below pins the anchor contract so a future regression re-introducing
 * `description` (or any other non-existent column) fails this gate.
 *
 * Acceptance:
 *  - `php artisan migrate` runs cleanly against the test DB.
 *  - `Schema::hasColumn('audit_logs', 'is_immutable')` is true.
 *  - `Schema::hasColumn('audit_logs', 'description')` is false (base schema
 *    never contained `description`; this guards against a future additive
 *    migration re-introducing it).
 *  - `Schema::hasColumn('audit_logs', 'user_agent')` is true (the new
 *    anchor exists in the base schema).
 *  - The migration source uses `->after('user_agent')` and never uses
 *    `->after('description')` or any other non-existent column.
 *
 * @group mysql
 */
class AuditLogMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_adds_is_immutable_column(): void
    {
        Artisan::call('migrate');

        $this->assertTrue(
            Schema::hasColumn('audit_logs', 'is_immutable'),
            'audit_logs.is_immutable must exist after running migrations'
        );
    }

    public function test_migrate_does_not_add_description_column(): void
    {
        Artisan::call('migrate');

        $this->assertFalse(
            Schema::hasColumn('audit_logs', 'description'),
            'audit_logs.description must never exist (base schema has no such column)'
        );
    }

    public function test_migrate_preserves_user_agent_anchor(): void
    {
        Artisan::call('migrate');

        $this->assertTrue(
            Schema::hasColumn('audit_logs', 'user_agent'),
            'audit_logs.user_agent must exist (used as the ->after() anchor)'
        );
    }

    public function test_migration_source_anchors_on_existing_user_agent_column(): void
    {
        $code = $this->migrationUpBody();

        $this->assertStringContainsString(
            "->after('user_agent')",
            $code,
            'Migration up() must anchor is_immutable on the existing user_agent column'
        );
    }

    public function test_migration_source_does_not_anchor_on_nonexistent_columns(): void
    {
        $code = $this->migrationUpBody();

        $this->assertStringNotContainsString(
            "->after('description')",
            $code,
            'Migration up() must not anchor on `description` (column absent from base audit_logs schema)'
        );
        $this->assertStringNotContainsString(
            "->after('metadata')",
            $code,
            'Migration up() must not anchor on `metadata` (column absent from base audit_logs schema)'
        );
    }

    /**
     * Extract the body of the migration's `up()` method with comments and
     * string literals stripped, so docblock references to historical anchors
     * (used as context for reviewers) do not trip the source assertions.
     */
    private function migrationUpBody(): string
    {
        $source = file_get_contents(
            base_path('database/migrations/2026_08_05_000000_add_audit_log_immutable.php')
        );

        $this->assertIsString($source, 'Migration file must be readable');

        if (!preg_match(
            '/public function\s+up\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n\s*\}/s',
            $source,
            $m
        )) {
            $this->fail('Could not extract up() method body from migration');
        }

        $body = $m[1];

        // Strip line comments, block comments, and string literals so the
        // assertion matches only executable code (mirrors SddCheckMigrationsTest).
        $stripped = preg_replace([
            '/\/\/.*$/m',
            '/\/\*.*?\*\//s',
            "/'(?:\\\\.|[^'\\\\])*'/s",
            '/"(?:\\\\.|[^"\\\\])*"/s',
        ], '', $body) ?? $body;

        return $stripped;
    }
}
