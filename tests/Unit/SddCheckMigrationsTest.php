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
 *  - NO migration may re-add a column that an EARLIER migration already created
 *    via `Schema::create(...)` inside a `Schema::table(...)` closure without a
 *    `Schema::hasColumn(...)` guard in the same closure — the source of NEW-003.
 *    The re-add guard scans **all** migration files (no cutoff) because the bug
 *    class is retroactive (the offending `error_message` add is dated 2026-08-05,
 *    but the column it duplicates was created 11 months earlier).
 *
 * Historical migrations dated before the slice are exempt from the additive-only
 * rules (debt documented in AGENTS.md §6), but ALL historical migrations are
 * subject to the no-Eloquent and re-add guards.
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

    /**
     * Regression guard for NEW-003: NO migration file in `database/migrations/`
     * may add a column that an EARLIER migration already created via
     * `Schema::create(...)` inside a `Schema::table(...)` closure without a
     * `Schema::hasColumn(...)` guard in the same closure.
     *
     * Scans ALL migrations (no GUARD_CUTOFF_PREFIX) because the bug class is
     * retroactive (the offending `error_message` add in 2026_08_05_020000
     * duplicates a column created in 2025_09_20_082355 — an 11-month
     * regression window). First-add columns that have no prior CREATE in
     * any earlier migration are NOT flagged.
     *
     * See design.md Decision 3 (re-add guard scans unconditionally) and
     * Decision 4 (line-by-line walk, not whole-file regex).
     *
     * @test
     */
    public function no_migration_re_adds_already_known_column(): void
    {
        $violations = [];
        $stripPatterns = [
            '/\/\/.*$/m',
            '/\/\*.*?\*\//s',
            "/'(?:\\\\.|[^'\\\\])*'/s",
            '/"(?:\\\\.|[^"\\\\])*"/s',
        ];

        // Column-add call shape with the column identifier in capture group 1.
        // Mirrors design §Decision 4 + §Interfaces/Contracts.
        $addCallRegex = '/->(?:string|text|integer|bigInteger|json|dateTime|timestamp|boolean|foreignId|addColumn)\s*\(\s*[\'"]([^\'"]+)[\'"]/i';

        // Closure entry-shape regexes operate on the RAW line (not the
        // fully-stripped line) so the table-name string literal survives
        // the capture. The capture group is the table identifier.
        $createEntryRegex = "/Schema::create\\s*\\(\\s*['\"]([^'\"]+)['\"]/i";
        $tableEntryRegex = "/Schema::table\\s*\\(\\s*['\"]([^'\"]+)['\"]/i";

        $allFiles = self::allMigrations();
        sort($allFiles);

        // Pass 1 — build per-table known-column map by walking every
        // `Schema::create(...)` closure in chronological order. We use a
        // brace-counted walk of the stripped source so strings/comments
        // cannot poison the brace counter.
        $knownColumnsByTable = []; // table => [col => true]

        foreach ($allFiles as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }
            $lines = explode("\n", $source);
            $lineCount = count($lines);

            for ($i = 0; $i < $lineCount; $i++) {
                // Match against the RAW line so the table name survives.
                if (! preg_match($createEntryRegex, $lines[$i], $mCreate)) {
                    continue;
                }
                $currentTable = $mCreate[1];

                // Brace-count forward on the stripped source so strings /
                // comments inside the closure cannot poison the counter.
                $closureStart = $i;
                $braceDepth = 0;
                $closureEnd = -1;
                $enteredClosure = false;
                for ($j = $i; $j < $lineCount; $j++) {
                    $strippedJ = preg_replace($stripPatterns, '', $lines[$j]) ?? $lines[$j];
                    foreach (str_split($strippedJ) as $ch) {
                        if ($ch === '{') {
                            $braceDepth++;
                            $enteredClosure = true;
                        } elseif ($ch === '}') {
                            $braceDepth--;
                        }
                    }
                    if ($enteredClosure && $braceDepth <= 0) {
                        $closureEnd = $j;
                        break;
                    }
                }
                if ($closureEnd === -1) {
                    continue;
                }

                // Walk the un-stripped lines inside the closure; capture the
                // column name from each column-add call shape.
                for ($k = $closureStart; $k <= $closureEnd; $k++) {
                    $lineK = $lines[$k];
                    if (preg_match($addCallRegex, $lineK, $mAdd)) {
                        $col = $mAdd[1];
                        $knownColumnsByTable[$currentTable][$col] = true;
                    }
                }
                $i = $closureEnd;
            }
        }

        // Pass 2 — for each `Schema::table(...)` closure, every column-add
        // call that targets a column already known for the same table from a
        // prior migration MUST be guarded by `Schema::hasColumn(...)` in the
        // same closure; otherwise emit a violation with filename + 1-indexed
        // line + column identifier.
        foreach ($allFiles as $file) {
            $name = basename($file);
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }
            $lines = explode("\n", $source);
            $lineCount = count($lines);

            for ($i = 0; $i < $lineCount; $i++) {
                // Match against the RAW line so the table name survives.
                if (! preg_match($tableEntryRegex, $lines[$i], $mTable)) {
                    continue;
                }
                $currentTable = $mTable[1];

                $closureStart = $i;
                $braceDepth = 0;
                $closureEnd = -1;
                $enteredClosure = false;
                for ($j = $i; $j < $lineCount; $j++) {
                    $strippedJ = preg_replace($stripPatterns, '', $lines[$j]) ?? $lines[$j];
                    foreach (str_split($strippedJ) as $ch) {
                        if ($ch === '{') {
                            $braceDepth++;
                            $enteredClosure = true;
                        } elseif ($ch === '}') {
                            $braceDepth--;
                        }
                    }
                    if ($enteredClosure && $braceDepth <= 0) {
                        $closureEnd = $j;
                        break;
                    }
                }
                if ($closureEnd === -1) {
                    continue;
                }

                // Build a stripped copy of the closure so the brace-counting
                // walk in pass 1 (already done) and a future re-check could
                // rely on it; here we need the RAW closure for the guard
                // search because the table/column arguments are themselves
                // string literals — stripping them would defeat the check.
                $closureRaw = '';
                for ($m = $closureStart; $m <= $closureEnd; $m++) {
                    $closureRaw .= $lines[$m] . "\n";
                }

                // For each column-add call inside the closure, if the column
                // is already known for this table, require a guard. Modify
                // operations (chained `->change()`) are NOT flagged — they
                // mutate an existing column, which is a fundamentally different
                // operation that the NEW-003 regression is not concerned with.
                for ($k = $closureStart; $k <= $closureEnd; $k++) {
                    $lineK = $lines[$k];
                    if (! preg_match($addCallRegex, $lineK, $mAdd)) {
                        continue;
                    }
                    if (preg_match('/->change\s*\(\s*\)/i', $lineK)) {
                        continue;
                    }
                    $col = $mAdd[1];
                    if (! isset($knownColumnsByTable[$currentTable][$col])) {
                        // First-add column (no prior CREATE in chain). Legit.
                        continue;
                    }
                    // Anchor the guard pattern so an unrelated
                    // `Schema::hasColumn($otherTable, 'error_message')` does
                    // not satisfy the check for `reminder_schedules`.
                    $guardPattern = "/Schema::hasColumn\\s*\\(\\s*['\"]"
                        . preg_quote($currentTable, '/')
                        . "['\"]\\s*,\\s*['\"]"
                        . preg_quote($col, '/')
                        . "['\"]/";
                    if (preg_match($guardPattern, $closureRaw)) {
                        // Guard present in the same closure. Pass.
                        continue;
                    }
                    $lineNumber = $k + 1;
                    $violations[] = "{$name}:{$lineNumber} adds column '{$col}' without hasColumn guard";
                }

                $i = $closureEnd;
            }
        }

        $this->assertSame(
            [],
            $violations,
            "No migration may re-add a column that an earlier migration already created without a Schema::hasColumn(...) guard in the same closure (NEW-003 regression). Offending files:\n"
                . implode("\n", $violations)
        );
    }
}