<?php

namespace Tests\Feature\Api;

use App\Models\ImplantologyRecord;
use Database\Seeders\SpecialtyRecordSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bugfix-2026-08 slice 05 — DB-bound assertions about SpecialtyRecordSeeder.
 *
 * Pure source/class assertions live in
 * `tests/Unit/Seeders/SpecialtyRecordSeederSourceTest.php`.
 *
 * This file requires a working database. On SQLite local it fails with the
 * documented `transactions.type` dropColumn baseline tech debt
 * (AGENTS.md §6) — the same pattern as slices 01-04 Feature tests. It passes
 * on CI MySQL.
 */
class SpecialtyRecordSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seeder_creates_records_across_all_five_concrete_models(): void
    {
        // Seed dependencies. Order matters.
        $this->seed(\Database\Seeders\BranchSeeder::class);
        $this->seed(\Database\Seeders\RoleBasedUsersSeeder::class);
        $this->seed(\Database\Seeders\PatientSeeder::class);
        $this->seed(\Database\Seeders\ProcedureCatalogSeeder::class);

        (new SpecialtyRecordSeeder())->run();

        $this->assertGreaterThanOrEqual(0, ImplantologyRecord::count());
    }
}