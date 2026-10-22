<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\OdontogramRecord;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F2 (#282) — one closure, and the appointment as its projection.
 *
 * Closing the act of an appointment creates the evolution, the findings, the
 * materials, the attachments, the amount and the quotation *from* the
 * encounter, closes it once, and then writes `status`, `completed_at`,
 * `final_amount` and `consultation_mode` on the appointment (S3, S4, S7).
 */
class ConsultationClosureTest extends TestCase
{
    use RefreshDatabase;

    private function professional(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function inConsultation(User $professional, array $attributes = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'user_id' => $professional->id,
            'status' => 'in_consultation',
            'checked_in_at' => now()->subMinutes(30),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'execution',
            'evolution' => [
                'subjective' => 'Dolor en pieza 16 desde hace tres días.',
                'objective' => 'Caries oclusal profunda, sin exposición pulpar.',
                'assessment' => 'Caries dentinaria.',
                'plan' => 'Obturación con resina compuesta.',
            ],
            'treatment_plan' => [
                'title' => 'Restauración 16',
                'items' => [
                    [
                        'procedure_name' => 'Obturación composite',
                        'dental_piece_id' => null,
                        'unit_cost' => 180,
                        'quantity' => 1,
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_the_closure_closes_the_encounter_and_projects_it_on_the_appointment(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload());

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('meta.message', 'Consulta completada.')
            ->assertJsonPath('meta.final_amount', 180)
            ->assertJsonPath('meta.consultation_mode', 'execution');

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        $this->assertTrue($encounter->isClosed());
        $this->assertSame('execution', $encounter->mode);
        $this->assertSame('180.00', $encounter->final_amount);
        $this->assertSame($professional->id, $encounter->closed_by);
        $this->assertNotNull($encounter->closed_at);
        $this->assertNotNull($encounter->medical_record_id);
        $this->assertNotNull($encounter->treatment_plan_id);
        $this->assertSame($encounter->id, $response->json('meta.encounter_id'));

        // S4: the appointment keeps the closed act as a projection written by
        // the same transaction.
        $appointment->refresh();
        $this->assertSame('completed', $appointment->status);
        $this->assertNotNull($appointment->completed_at);
        $this->assertSame('180.00', $appointment->final_amount);
        $this->assertSame('execution', $appointment->consultation_mode);
        $this->assertSame($encounter->treatment_plan_id, $appointment->treatment_plan_id);
    }

    public function test_every_clinical_row_the_act_produced_hangs_from_the_encounter(): void
    {
        Storage::fake('public');

        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $category = ProductCategory::create(['name' => 'Restauración', 'code' => 'REST']);
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'cost_price' => 20,
            'sale_price' => 35,
        ]);

        $payload = $this->payload([
            'odontogram' => [[
                'dental_piece_id' => \App\Models\DentalPiece::create([
                    'fdi_number' => '16',
                    'name' => 'Primer molar superior derecho',
                    'type' => 'molar',
                    'quadrant' => 'superior_derecho',
                    'position' => 6,
                ])->id,
                'condition_code' => 'caries',
                'condition_name' => 'Caries',
            ]],
            'materials' => [[
                'product_id' => $product->id,
                'quantity_used' => 2,
                'unit_cost' => 20,
            ]],
            'attachments' => [[
                'category' => 'radiografia',
                'file' => UploadedFile::fake()->image('rx16.jpg'),
            ]],
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $payload)
            ->assertOk();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        $this->assertSame(1, ClinicalEvolution::where('clinical_encounter_id', $encounter->id)->count());
        $this->assertSame(1, OdontogramRecord::where('clinical_encounter_id', $encounter->id)->count());
        $this->assertSame(1, $encounter->procedureMaterials()->count());
        $this->assertSame(1, ClinicalAttachment::where('clinical_encounter_id', $encounter->id)->count());

        // The appointment id is kept as the schedule projection of the same row.
        $this->assertSame(1, ClinicalEvolution::where('appointment_id', $appointment->id)->count());
        $this->assertSame($encounter->id, ClinicalEvolution::sole()->clinical_encounter_id);
    }

    public function test_the_quotation_of_the_closure_points_at_the_act_and_the_appointment(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload());

        $response->assertOk()->assertJsonPath('meta.quotation_generated', true);

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();
        $quotation = Quotation::sole();

        $this->assertSame($encounter->id, $quotation->clinical_encounter_id);
        $this->assertSame($appointment->id, $quotation->appointment_id);
        $this->assertSame('180.00', $quotation->total_amount);
    }

    public function test_the_act_is_closed_exactly_once(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);
        $payload = $this->payload();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $payload)
            ->assertOk();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'encounter_not_open');

        $this->assertSame(1, ClinicalEvolution::count());
        $this->assertSame(1, Quotation::count());
    }

    public function test_the_appointment_cannot_be_closed_before_the_professional_starts_the_act(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional, ['status' => 'arrived']);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('error.error_code', 'appointment_not_in_consultation');

        $this->assertSame('arrived', $appointment->fresh()->status);
        $this->assertSame(0, ClinicalEncounter::count());
    }

    public function test_a_completed_appointment_always_has_a_closed_encounter(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload(['mode' => 'consultation']))
            ->assertOk();

        $completed = Appointment::where('status', 'completed')->get();

        $this->assertCount(1, $completed);

        foreach ($completed as $row) {
            $this->assertTrue(
                $row->clinicalEncounters()->where('status', ClinicalEncounter::STATUS_CLOSED)->exists(),
                "Appointment {$row->id} is completed without a closed encounter.",
            );
        }
    }

    public function test_the_closure_still_accepts_a_consultation_without_materials(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, ['patient_id' => $patient->id]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                'mode' => 'consultation',
                'treatment_plan' => [
                    'title' => 'Propuesta',
                    'as_proposed' => true,
                    'items' => [['procedure_name' => 'Endodoncia', 'unit_cost' => 400, 'quantity' => 1]],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('meta.quotation_generated', false);

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        $this->assertTrue($encounter->isClosed());
        $this->assertSame('consultation', $encounter->mode);
        // A proposed plan is not executed work, so the act has no amount.
        $this->assertSame('0.00', $encounter->final_amount);
        $this->assertSame(0, Quotation::count());
    }
}
