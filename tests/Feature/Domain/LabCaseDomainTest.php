<?php

namespace Tests\Feature\Domain;

use App\Models\DentalPiece;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5 (#285) — the prosthetic lab case.
 *
 * The entity is its own thing: the laboratory works with a patient, a piece, a
 * job, a material and two dates, and it never needs the clinical record to do
 * it. This test pins the persisted shape and the five phases the issue names,
 * in the order the work happens.
 */
class LabCaseDomainTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function piece(): DentalPiece
    {
        return DentalPiece::create([
            'fdi_number' => '36',
            'name' => 'Primer molar inferior izquierdo',
            'type' => 'molar',
            'quadrant' => 'inferior_izquierdo',
            'position' => 6,
            'is_permanent' => true,
            'is_active' => true,
        ]);
    }

    public function test_a_case_records_the_patient_piece_job_material_and_dates(): void
    {
        $professional = $this->staff('odontologo');
        $technician = $this->staff('tecnico_dental');
        $patient = Patient::factory()->create();
        $piece = $this->piece();

        $case = LabCase::create([
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'technician_id' => $technician->id,
            'dental_piece_id' => $piece->id,
            'work' => 'Corona metal-cerámica',
            'material' => 'Zirconia',
            'sent_at' => '2026-10-01',
            'promised_at' => '2026-10-10',
            'notes' => 'Margen subgingival en mesial.',
            'created_by' => $professional->id,
        ]);

        $stored = $case->fresh();

        $this->assertSame('Corona metal-cerámica', $stored->work);
        $this->assertSame('Zirconia', $stored->material);
        $this->assertSame('2026-10-01', $stored->sent_at->toDateString());
        $this->assertSame('2026-10-10', $stored->promised_at->toDateString());
        $this->assertSame('Margen subgingival en mesial.', $stored->notes);
        $this->assertSame(LabCase::PHASE_IN_PREPARATION, $stored->status);
        $this->assertNull($stored->delivered_at);
        $this->assertNull($stored->placed_at);

        $this->assertSame($patient->id, $stored->patient->id);
        $this->assertSame($professional->id, $stored->professional->id);
        $this->assertSame($technician->id, $stored->technician->id);
        $this->assertSame($piece->id, $stored->dentalPiece->id);
        $this->assertSame($professional->id, $stored->createdBy->id);
    }

    public function test_the_five_phases_keep_the_documented_order(): void
    {
        $this->assertSame([
            'in_preparation',
            'in_laboratory',
            'try_in',
            'delivered',
            'placed',
        ], LabCase::PHASES);
    }
}
