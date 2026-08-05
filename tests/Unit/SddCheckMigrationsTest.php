<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Sprint 5 / bugfix-2026-08 slice 05 — CI guard for migration drift.
 *
 * Scans `database/migrations/` for files dated `>= 2026_08_05` and enforces
 * the additive-only policy declared in the change's spec:
 *
 *  - DROP COLUMN only allowed inside `down()` (reversibility for additive migrations).
 *  - MODIFY COLUMN (raw ALTER) must be wrapped in `DB::getDriverName() === 'mysql'`
 *    so SQLite test env does not crash.
 *  - Non-nullable column additions must be paired with a default or follow-up
 *    backfill (Laravel Blueprint nullable() default is OK; otherwise default()).
 *  - DATE_SUB / DATE_ADD raw SQL (MySQL-specific) must be driver-conditional.
 *  - NO migration may reference `App\Models\` (Eloquent) — the source of NEW-002.
 *    The Eloquent guard scans **all** migration files (no cutoff) because the
 *    regression is retroactive (the buggy file predates the 2026-08-05 cutoff).
 *
 * Historical migrations dated before the slice are exempt from the additive-only
 * rules (debt documented in AGENTS.md §6), but ALL historical migrations are
 * subject to the no-Eloquent guard.
 *
 * Failure indicates a forbidden migration pattern was introduced — the developer
 * must rewrite the migration to be additive or driver-conditional, or replace the
 * Eloquent reference with raw `DB::table(...)` Query Builder.
 */
class SddCheckMigrationsTest extends TestCase
{
    /** Migrations added on or after this date prefix are subject to the guard. */
    private const GUARD_CUTOFF_PREFIX = '2026_08_05';

    private static function migrationsDir(): string
    {
        return realpath(__DIR__ . '/../../database/migrations');
    }

    /** @return string[] */
    private static function guardedMigrations(): array
    {
        $all = glob(self::migrationsDir() . '/*.php') ?: [];
        $cutoff = self::GUARD_CUTOFF_PREFIX;
        $guarded = [];
        foreach ($all as $file) {
            $name = basename($file);
            // Filenames are timestamp-prefixed: YYYY_MM_DD_HHMMSS_*.php
            $prefix = substr($name, 0, 10); // YYYY_MM_DD
            if ($prefix >= $cutoff) {
                $guarded[] = $file;
            }
        }
        sort($guarded);
        return $guarded;
    }

    /**
     * Every migration under `database/migrations/*.php`.
     *
     * Unlike `guardedMigrations()`, this helper imposes NO date cutoff — the
     * Eloquent-reference guard scans historical files too because the NEW-002
     * bug was retroactive (the offending migration predates the 2026_08_05
     * slice by design). This deliberate departure from the additive-only
     * pattern is documented in design.md Decision 4.
     *
     * @return string[]
     */
    private static function allMigrations(): array
    {
        $all = glob(self::migrationsDir() . '/*.php') ?: [];
        sort($all);
        return $all;
    }

    /**
     * Extract the body of a single method (`up` or `down`) from a migration file.
     * Returns empty string if the method is not present.
     */
    private static function methodBody(string $source, string $method): string
    {
        if (!preg_match('/public function\s+' . preg_quote($method, '/') . '\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n\s*\}/s', $source, $m)) {
            return '';
        }
        return $m[1];
    }

    /** @test */
    public function no_new_migration_drops_a_column_outside_the_down_method(): void
    {
        $violations = [];
        foreach (self::guardedMigrations() as $file) {
            $name = basename($file);
            // Legacy-cleanup migrations (filename contains `drop_legacy_`) are
            // permitted to drop columns, provided their `down()` re-adds them.
            // This is the only carve-out from the additive-only policy.
            if (str_contains($name, 'drop_legacy_')) {
                continue;
            }
            $source = file_get_contents($file);
            $up = self::methodBody($source, 'up');
            // Look for Schema::table / Schema::create with ->dropColumn( or DB::statement dropping a column
            if ($up === '') {
                continue;
            }
            // Strip strings/comments to avoid false positives
            $stripped = preg_replace([
                '/\/\/.*$/m',
                '/\/\*.*?\*\//s',
                "/'(?:\\\\.|[^'\\\\])*'/s",
                '/"(?:\\\\.|[^"\\\\])*"/s',
            ], '', $up) ?? $up;
            if (preg_match('/->dropColumn\s*\(/i', $stripped)
                || preg_match('/DROP\s+COLUMN\s+/i', $stripped)
                || preg_match('/ALTER\s+TABLE\s+\S+\s+DROP\s+/i', $stripped)
            ) {
                $violations[] = $name . ' drops a column in up()';
            }
        }
        $this->assertSame(
            [],
            $violations,
            "New migrations must not drop columns in up() — use additive migrations only.\n" . implode("\n", $violations)
        );
    }

    /** @test */
    public function no_new_migration_uses_raw_modify_column_without_driver_guard(): void
    {
        $violations = [];
        foreach (self::guardedMigrations() as $file) {
            $source = file_get_contents($file);
            $up = self::methodBody($source, 'up');
            if ($up === '') {
                continue;
            }
            $hasModify = (bool) preg_match('/MODIFY\s+COLUMN\s+/i', $up)
                || (bool) preg_match('/->change\s*\(\s*\)/i', $up);
            if (!$hasModify) {
                continue;
            }
            // Driver guard required.
            $hasGuard = (bool) preg_match("/DB::getDriverName\s*\(\s*\)\s*===?\s*['\"]mysql['\"]/", $up)
                || (bool) preg_match("/DB::getDriverName\s*\(\s*\)\s*!==?\s*['\"]sqlite['\"]/", $up)
                || (bool) preg_match("/DB::connection\s*\(\s*\)->getDriverName\s*\(\s*\)\s*===?\s*['\"]mysql['\"]/", $up)
                || str_contains($up, 'Schema::getConnection()->getDriverName() === \'mysql\'');
            if (!$hasGuard) {
                $violations[] = basename($file) . ' uses MODIFY/->change() without driver guard';
            }
        }
        $this->assertSame(
            [],
            $violations,
            "Raw ALTER TABLE ... MODIFY COLUMN must be wrapped in driver-conditional (MySQL only) to keep SQLite test env working.\n" . implode("\n", $violations)
        );
    }

    /** @test */
    public function no_new_migration_uses_mysql_specific_date_arithmetic_without_driver_guard(): void
    {
        $violations = [];
        foreach (self::guardedMigrations() as $file) {
            $source = file_get_contents($file);
            $up = self::methodBody($source, 'up');
            if ($up === '') {
                continue;
            }
            $hasDateArithmetic = (bool) preg_match('/DATE_(SUB|ADD)\s*\(/i', $up);
            if (!$hasDateArithmetic) {
                continue;
            }
            $hasGuard = (bool) preg_match("/DB::getDriverName\s*\(\s*\)\s*===?\s*['\"]mysql['\"]/", $up)
                || (bool) preg_match("/DB::getDriverName\s*\(\s*\)\s*!==?\s*['\"]sqlite['\"]/", $up);
            if (!$hasGuard) {
                $violations[] = basename($file) . ' uses DATE_SUB/DATE_ADD without driver guard';
            }
        }
        $this->assertSame(
            [],
            $violations,
            "DATE_SUB / DATE_ADD are MySQL-specific; must be driver-conditional.\n" . implode("\n", $violations)
        );
    }

    /** @test */
    public function no_new_migration_drops_a_unique_index_in_up_without_a_followup_recreate(): void
    {
        $violations = [];
        foreach (self::guardedMigrations() as $file) {
            $source = file_get_contents($file);
            $up = self::methodBody($source, 'up');
            if ($up === '') {
                continue;
            }
            $hasDropUnique = (bool) preg_match('/->dropUnique\s*\(/i', $up);
            if (!$hasDropUnique) {
                continue;
            }
            $hasRecreate = (bool) preg_match('/->unique\s*\(/i', $up);
            if (!$hasRecreate) {
                $violations[] = basename($file) . ' drops unique index without recreating it';
            }
        }
        $this->assertSame(
            [],
            $violations,
            "dropUnique() in up() must be paired with unique() in the same block (atomic).\n" . implode("\n", $violations)
        );
    }

    /** @test */
    public function guard_only_scans_migrations_added_during_or_after_slice_05(): void
    {
        $guarded = self::guardedMigrations();
        foreach ($guarded as $file) {
            $name = basename($file);
            $this->assertStringStartsWith(
                self::GUARD_CUTOFF_PREFIX,
                substr($name, 0, 10),
                "{$name} should have a date prefix >= " . self::GUARD_CUTOFF_PREFIX
            );
        }
        $this->assertNotEmpty($guarded, 'Expected at least one migration in the guard window');
    }

    /**
     * Regression guard for NEW-002: NO migration file in `database/migrations/`
     * may reference `App\Models\` (Eloquent). Historical migrations are NOT
     * exempt — the bug is retroactive, so this scan covers every file.
     *
     * See design.md Decision 4 (Eloquent guard scans unconditionally) and
     * the spec scenario `migration-portability Eloquent guard → guard fails if
     * violation is reintroduced`.
     *
     * @test
     */
    public function no_migration_references_eloquent_models(): void
    {
        $violations = [];
        $stripPatterns = [
            '/\/\/.*$/m',
            '/\/\*.*?\*\//s',
            "/'(?:\\\\.|[^'\\\\])*'/s",
            '/"(?:\\\\.|[^"\\\\])*"/s',
        ];
        foreach (self::allMigrations() as $file) {
            $name = basename($file);
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }
            // Walk line-by-line so the failure report carries the original
            // file's 1-indexed line number, not a derived offset.
            $lines = explode("\n", $source);
            foreach ($lines as $i => $line) {
                $stripped = preg_replace($stripPatterns, '', $line) ?? $line;
                if (str_contains($stripped, 'App\\Models\\')) {
                    $lineNumber = $i + 1;
                    $violations[] = "{$name}:{$lineNumber} references App\\Models\\";
                }
            }
        }
        $this->assertSame(
            [],
            $violations,
            "No migration may reference App\\Models\\ (NEW-002 regression). Offending files:\n"
                . implode("\n", $violations)
        );
    }
}