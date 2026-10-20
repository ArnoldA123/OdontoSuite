<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read contract for the specialty-record surfaces the redesigned page uses
 * (issue #212).
 *
 * `GET /patient/{id}/all` feeds the five tabs and `GET /patient/{id}/{specialty}`
 * feeds a single tab. The oral-surgery query ordered by `surgery_date`, a
 * column the table never had, so the tab answered 500 before this contract
 * existed.
 */
class SpecialtyRecordReadContractTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Patient $patient;

    private DentalPiece $piece;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->patient = Patient::factory()->create();
        $this->seed(DentalPieceSeeder::class);
        $this->piece = DentalPiece::query()->where('fdi_number', '16')->firstOrFail();
    }

    private function implantology(): ImplantologyRecord
    {
        return ImplantologyRecord::create([
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece->id,
            'created_by' => $this->admin->id,
            'implant_brand' => 'Straumann',
            'implant_model' => 'BLT',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'L-1',
            'placement_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    private function oralSurgery(string $procedure): OralSurgeryRecord
    {
        return OralSurgeryRecord::create([
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece->id,
            'created_by' => $this->admin->id,
            'procedure_type' => $procedure,
            'surgery_site' => 'Tercer molar inferior izquierdo',
            'status' => 'completed',
        ]);
    }

    public function test_all_returns_the_five_groups_the_tabs_render(): void
    {
        $this->implantology();
        $this->oralSurgery('extraction');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/specialty-records/patient/{$this->patient->id}/all");

        $response->assertOk();

        $this->assertSame(
            ['implantologia', 'ortodoncia', 'endodoncia', 'rehabilitacion', 'cirugia_oral'],
            array_keys($response->json('data'))
        );
        $this->assertCount(1, $response->json('data.implantologia'));
        $this->assertCount(1, $response->json('data.cirugia_oral'));
        $this->assertCount(0, $response->json('data.ortodoncia'));
    }

    public function test_oral_surgery_listing_returns_newest_first_without_a_surgery_date_column(): void
    {
        $older = $this->oralSurgery('extraction');
        $newer = $this->oralSurgery('biopsy');

        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/specialty-records/patient/{$this->patient->id}/cirugia_oral");

        $response->assertOk();
        $this->assertSame(
            [$newer->id, $older->id],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_stats_returns_the_count_of_the_requested_specialty(): void
    {
        $this->implantology();
        $this->implantology();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/specialty-records/patient/{$this->patient->id}/implantologia/stats");

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total_records'));
    }
}
