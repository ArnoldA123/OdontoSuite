<?php

namespace Tests\Unit\Migrations;

use PHPUnit\Framework\TestCase;

/**
 * bugfix-2026-08 slice 05 — verifies that migrations touching the
 * `appointments` table which contain MySQL-only constructs (DATE_SUB,
 * raw MODIFY COLUMN) are gated on driver so SQLite local tests do not
 * crash.
 *
 * Acceptance: any migration dated >= 2026_08_05 that uses MySQL-only
 * SQL inside its `up()` method must include a driver guard.
 *
 * Pre-existing migrations (dated before 2026_08_05) are exempted per
 * AGENTS.md §6 — they remain tech debt until the CI MySQL job catches up.
 */
class MigrationsDriverGuardTest extends TestCase
{
    /** @test */
    public function timezone_offset_migration_is_driver_guarded_after_slice_05(): void
    {
        $file = realpath(__DIR__ . '/../../../database/migrations/2026_06_02_173228_fix_appointments_timezone_offset.php');
        $this->assertFileExists($file, 'timezone offset migration should still exist (slice 05 does not delete it)');

        $source = file_get_contents($file);

        // Driver guard required.
        $hasGuard = (bool) preg_match("/DB::getDriverName\s*\(\s*\)\s*===?\s*['\"]mysql['\"]/", $source)
            || (bool) preg_match("/DB::getDriverName\s*\(\s*\)\s*===?\s*['\"]sqlite['\"]/", $source)
            || str_contains($source, "DB::getDriverName() === 'sqlite'")
            || str_contains($source, 'DB::getDriverName() === "sqlite"');

        $this->assertTrue(
            $hasGuard,
            "timezone_offset migration must be driver-conditional so SQLite local tests do not crash on DATE_SUB."
        );
    }

    /** @test */
    public function status_enum_migration_remains_driver_guarded(): void
    {
        $file = realpath(__DIR__ . '/../../../database/migrations/2025_10_14_123001_fix_appointments_status_enum.php');
        $this->assertFileExists($file);

        $source = file_get_contents($file);
        $this->assertStringContainsString(
            "DB::getDriverName() === 'sqlite'",
            $source,
            'status_enum migration must keep its SQLite guard (precedent from slice 02).'
        );
    }
}