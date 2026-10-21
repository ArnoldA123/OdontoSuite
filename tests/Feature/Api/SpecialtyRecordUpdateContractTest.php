<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\EndodonticsRecord;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\OrthodonticsRecord;
use App\Models\Patient;
use App\Models\RehabilitationRecord;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Update contract for specialty records (issue #212).
 *
 * The redesigned edit form offers the same columns as the create form, so the
 * PUT endpoint must persist them. Previously it validated a fixed subset and
 * silently discarded statuses, phases and most notes, which made an edit look
 * saved while nothing changed.
 *
 * The rule pinned here: every field the create request validates is writable
 * through the update request, `patient_id` stays untouched, and an invalid
 * enum is rejected with 422 instead of being stored.
 */
class SpecialtyRecordUpdateContractTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->patient = Patient::factory()->create();
        $this->patient->forceFill(['is_active' => true])->save();
    }

    private function piece(): DentalPiece
    {
        $this->seed(DentalPieceSeeder::class);

        return DentalPiece::query()->firstOrFail();
    }

    private function implantology(): ImplantologyRecord
    {
        return ImplantologyRecord::create([
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece()->id,
            'created_by' => $this->admin->id,
            'implant_brand' => 'Nobel Biocare',
            'implant_model' => 'Replace Select',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'NB-1000',
            'placement_date' => now()->subDays(10)->toDateString(),
        ]);
    }

    private function orthodontics(): OrthodonticsRecord
    {
        return OrthodonticsRecord::create([
            'patient_id' => $this->patient->id,
            'created_by' => $this->admin->id,
            'treatment_type' => 'brackets',
            'treatment_start_date' => now()->subMonths(6)->toDateString(),
            'treatment_phase' => 'initial',
        ]);
    }

    private function endodontics(): EndodonticsRecord
    {
        return EndodonticsRecord::create([
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece()->id,
            'created_by' => $this->admin->id,
            'tooth_number' => '16',
            'canal_count' => 3,
            'treatment_status' => 'in_progress',
        ]);
    }

    private function rehabilitation(): RehabilitationRecord
    {
        return RehabilitationRecord::create([
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece()->id,
            'created_by' => $this->admin->id,
            'prosthesis_type' => 'crown',
            'material_type' => 'zirconia',
            'status' => 'impression',
        ]);
    }

    private function oralSurgery(): OralSurgeryRecord
    {
        return OralSurgeryRecord::create([
            'patient_id' => $this->patient->id,
            'created_by' => $this->admin->id,
            'procedure_type' => 'extraction',
            'surgery_site' => 'Tercer molar inferior izquierdo',
            'status' => 'scheduled',
        ]);
    }

    public function test_update_persists_the_status_field_of_every_specialty(): void
    {
        $cases = [
            ['implantologia', $this->implantology(), [
                'status' => 'loaded',
                'placement_date' => now()->subDays(10)->toDateString(),
                'healing_date' => now()->subDays(5)->toDateString(),
            ]],
            ['ortodoncia', $this->orthodontics(), ['treatment_phase' => 'completed']],
            ['endodoncia', $this->endodontics(), ['treatment_status' => 'completed']],
            ['rehabilitacion', $this->rehabilitation(), ['status' => 'cemented']],
            ['cirugia_oral', $this->oralSurgery(), ['status' => 'completed']],
        ];

        foreach ($cases as [$specialty, $record, $changes]) {
            $this->actingAs($this->admin, 'sanctum')
                ->putJson("/api/specialty-records/{$record->id}", [
                    'specialty' => $specialty,
                    ...$changes,
                ])
                ->assertOk();

            $record->refresh();

            foreach ($changes as $field => $expected) {
                $this->assertSame(
                    $expected,
                    $record->{$field} instanceof \DateTimeInterface
                        ? $record->{$field}->toDateString()
                        : $record->{$field},
                    "PUT MUST persist {$field} for {$specialty}."
                );
            }
        }
    }

    public function test_update_persists_the_notes_and_limits_of_the_create_contract(): void
    {
        $record = $this->implantology();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/specialty-records/{$record->id}", [
                'specialty' => 'implantologia',
                'surgical_notes' => 'Colocación sin incidentes.',
                'post_surgical_notes' => 'Cicatrización favorable.',
                'torque_value' => 35.5,
                'serial_number' => 'SN-2000',
            ])
            ->assertOk();

        $record->refresh();

        $this->assertSame('Colocación sin incidentes.', $record->surgical_notes);
        $this->assertSame('Cicatrización favorable.', $record->post_surgical_notes);
        $this->assertSame('SN-2000', $record->serial_number);
        $this->assertSame('35.50', $record->torque_value);
    }

    public function test_update_rejects_an_invalid_enum_and_leaves_the_row_unchanged(): void
    {
        $record = $this->endodontics();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/specialty-records/{$record->id}", [
                'specialty' => 'endodoncia',
                'treatment_status' => 'invalido',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('treatment_status');

        $this->assertSame('in_progress', $record->refresh()->treatment_status);
    }

    public function test_update_never_moves_the_record_to_another_patient(): void
    {
        $record = $this->orthodontics();
        $other = Patient::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/specialty-records/{$record->id}", [
                'specialty' => 'ortodoncia',
                'patient_id' => $other->id,
                'treatment_phase' => 'active',
            ])
            ->assertOk();

        $record->refresh();

        $this->assertSame($this->patient->id, $record->patient_id);
        $this->assertSame('active', $record->treatment_phase);
    }
}
