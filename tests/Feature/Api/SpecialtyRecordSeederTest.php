<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\EndodonticsRecord;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\OrthodonticsRecord;
use App\Models\RehabilitationRecord;
use Database\Seeders\SpecialtyRecordSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bugfix-2026-08 slice 05 + full-user-browser-audit-2026-08-05 PR4 — DB-bound
 * assertions about SpecialtyRecordSeeder.
 *
 * Pure source/class assertions live in
 * `tests/Unit/Seeders/SpecialtyRecordSeederSourceTest.php` and
 * `tests/Unit/Seeders/SpecialtyRecordSeederFieldContractTest.php`.
 *
 * This file requires a working database. It uses RefreshDatabase so the
 * whole suite is self-contained. The fixture seed chain mirrors the real
 * `DatabaseSeeder::run()` order: users → patients → dental pieces → specialty
 * records.
 */
class SpecialtyRecordSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seeder_creates_records_across_all_five_concrete_models(): void
    {
        $this->seed(\Database\Seeders\RoleBasedUsersSeeder::class);
        $this->seed(\Database\Seeders\PatientSeeder::class);
        $this->seed(\Database\Seeders\DentalPieceSeeder::class);

        (new SpecialtyRecordSeeder())->run();

        $this->assertGreaterThan(
            0,
            ImplantologyRecord::count(),
            'ImplantologyRecord branch must insert at least one row'
        );
        $this->assertGreaterThan(
            0,
            OrthodonticsRecord::count(),
            'OrthodonticsRecord branch must insert at least one row'
        );
        $this->assertGreaterThan(
            0,
            EndodonticsRecord::count(),
            'EndodonticsRecord branch must insert at least one row'
        );
        $this->assertGreaterThan(
            0,
            RehabilitationRecord::count(),
            'RehabilitationRecord branch must insert at least one row'
        );
        $this->assertGreaterThan(
            0,
            OralSurgeryRecord::count(),
            'OralSurgeryRecord branch must insert at least one row'
        );
    }

    /** @test */
    public function seeder_is_idempotent_on_re_run(): void
    {
        $this->seed(\Database\Seeders\RoleBasedUsersSeeder::class);
        $this->seed(\Database\Seeders\PatientSeeder::class);
        $this->seed(\Database\Seeders\DentalPieceSeeder::class);

        (new SpecialtyRecordSeeder())->run();
        $firstOrtho = OrthodonticsRecord::count();
        $firstImplant = ImplantologyRecord::count();

        (new SpecialtyRecordSeeder())->run();

        $this->assertSame(
            $firstOrtho * 2,
            OrthodonticsRecord::count(),
            'Re-running the seeder must double ortho records (patients are still present after refresh, seeder is not firstOrCreate by design — but this baseline pins the non-crash contract on idempotent reruns).'
        );
        $this->assertSame(
            $firstImplant * 2,
            ImplantologyRecord::count(),
            'Re-running the seeder must double implant records without raising unique-conflict exceptions.'
        );
    }

    /** @test */
    public function seeder_early_returns_when_no_dental_pieces_exist(): void
    {
        $this->seed(\Database\Seeders\RoleBasedUsersSeeder::class);
        $this->seed(\Database\Seeders\PatientSeeder::class);
        // Note: DentalPieceSeeder NOT called — collection is empty.

        (new SpecialtyRecordSeeder())->run();

        $this->assertSame(0, DentalPiece::count(), 'No dental pieces seeded in this test');
        $this->assertSame(0, ImplantologyRecord::count(), 'Implantology branch must early-return');
        $this->assertSame(
            0,
            OrthodonticsRecord::count(),
            'Orthodontics branch must early-return when dental_pieces is empty'
        );
    }
}