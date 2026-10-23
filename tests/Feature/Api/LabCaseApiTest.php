<?php

namespace Tests\Feature\Api;

use App\Events\LabCaseReady;
use App\Models\DentalPiece;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F5 (#285, S1/S3) — the prosthetic case surface.
 *
 * The laboratory desk creates and edits a work order without reading the
 * clinical record, and the phase moves only through its own door. Reception
 * reads the cases (it answers the phone about them) but does not write them;
 * finance neither reads nor writes them.
 */
class LabCaseApiTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private ?DentalPiece $piece = null;

    private function piece(): DentalPiece
    {
        return $this->piece ??= DentalPiece::firstOrCreate(['fdi_number' => '36'], [
            'name' => 'Primer molar inferior izquierdo',
            'type' => 'molar',
            'quadrant' => 'inferior_izquierdo',
            'position' => 6,
            'is_permanent' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'professional_id' => $this->staff('odontologo')->id,
            'technician_id' => $this->staff('tecnico_dental')->id,
            'dental_piece_id' => $this->piece()->id,
            'work' => 'Corona metal-cerámica',
            'material' => 'Zirconia',
            'sent_at' => '2026-10-01',
            'promised_at' => '2026-10-10',
            'notes' => 'Margen subgingival en mesial.',
        ], $overrides);
    }

    #[Test]
    public function the_store_persists_the_nine_fields_of_the_case(): void
    {
        $professional = $this->staff('odontologo');
        $technician = $this->staff('tecnico_dental');
        $patient = Patient::factory()->create();
        $piece = $this->piece();

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/lab-cases', [
                'patient_id' => $patient->id,
                'professional_id' => $professional->id,
                'technician_id' => $technician->id,
                'dental_piece_id' => $piece->id,
                'work' => 'Corona metal-cerámica',
                'material' => 'Zirconia',
                'sent_at' => '2026-10-01',
                'promised_at' => '2026-10-10',
                'notes' => 'Margen subgingival en mesial.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.work', 'Corona metal-cerámica')
            ->assertJsonPath('data.status', LabCase::PHASE_IN_PREPARATION)
            ->assertJsonPath('meta.message', 'Caso de laboratorio registrado exitosamente');

        $this->assertDatabaseHas('lab_cases', [
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'technician_id' => $technician->id,
            'dental_piece_id' => $piece->id,
            'work' => 'Corona metal-cerámica',
            'material' => 'Zirconia',
            'sent_at' => '2026-10-01',
            'promised_at' => '2026-10-10',
            'notes' => 'Margen subgingival en mesial.',
            'status' => LabCase::PHASE_IN_PREPARATION,
            'created_by' => $professional->id,
        ]);
    }

    #[Test]
    public function the_store_ignores_a_phase_sent_by_the_client(): void
    {
        $professional = $this->staff('odontologo');

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/lab-cases', $this->payload(['status' => LabCase::PHASE_PLACED]))
            ->assertCreated()
            ->assertJsonPath('data.status', LabCase::PHASE_IN_PREPARATION);

        $this->assertSame(LabCase::PHASE_IN_PREPARATION, LabCase::query()->sole()->status);
    }

    #[Test]
    public function a_case_without_work_is_rejected_and_nothing_is_written(): void
    {
        $professional = $this->staff('odontologo');
        $payload = $this->payload();
        unset($payload['work']);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/lab-cases', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work');

        $this->assertDatabaseCount('lab_cases', 0);
    }

    #[Test]
    public function a_promised_date_before_the_sent_date_is_rejected_and_nothing_is_written(): void
    {
        $professional = $this->staff('odontologo');

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/lab-cases', $this->payload([
                'sent_at' => '2026-10-10',
                'promised_at' => '2026-10-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('promised_at');

        $this->assertDatabaseCount('lab_cases', 0);
    }

    #[Test]
    public function the_professional_defaults_to_the_actor(): void
    {
        $professional = $this->staff('implantologo');
        $payload = $this->payload();
        unset($payload['professional_id']);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/lab-cases', $payload)
            ->assertCreated()
            ->assertJsonPath('data.professional_id', $professional->id);

        $this->assertSame($professional->id, LabCase::query()->sole()->professional_id);
    }

    #[Test]
    public function the_assigned_technician_must_be_a_laboratory_technician(): void
    {
        $professional = $this->staff('odontologo');

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/lab-cases', $this->payload([
                'technician_id' => $this->staff('asistente')->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('technician_id');

        $this->assertDatabaseCount('lab_cases', 0);
    }

    #[Test]
    public function reception_reads_the_cases_but_does_not_write_them(): void
    {
        $receptionist = $this->staff('recepcionista');
        LabCase::factory()->create();

        $this->actingAs($receptionist, 'sanctum')
            ->getJson('/api/lab-cases')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/lab-cases', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('lab_cases', 1);
    }

    #[Test]
    public function finance_cannot_read_the_laboratory_cases(): void
    {
        $finance = $this->staff('finanzas');
        LabCase::factory()->create();

        $this->actingAs($finance, 'sanctum')
            ->getJson('/api/lab-cases')
            ->assertForbidden();
    }

    #[Test]
    public function the_technician_moves_the_case_and_an_invalid_phase_changes_nothing(): void
    {
        Event::fake([LabCaseReady::class]);

        $technician = $this->staff('tecnico_dental');
        $case = LabCase::factory()->create(['technician_id' => $technician->id]);

        $this->actingAs($technician, 'sanctum')
            ->postJson("/api/lab-cases/{$case->id}/phase", ['phase' => LabCase::PHASE_DELIVERED])
            ->assertOk()
            ->assertJsonPath('data.status', LabCase::PHASE_DELIVERED)
            ->assertJsonPath('meta.message', 'Fase del caso de laboratorio actualizada exitosamente');

        $this->assertNotNull($case->fresh()->delivered_at);
        Event::assertDispatched(LabCaseReady::class);

        $this->actingAs($technician, 'sanctum')
            ->postJson("/api/lab-cases/{$case->id}/phase", ['phase' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phase');

        $this->assertSame(LabCase::PHASE_DELIVERED, $case->fresh()->status);
    }

    #[Test]
    public function a_backwards_phase_is_rejected_over_http(): void
    {
        $technician = $this->staff('tecnico_dental');
        $case = LabCase::factory()->create([
            'technician_id' => $technician->id,
            'status' => LabCase::PHASE_TRY_IN,
        ]);

        $this->actingAs($technician, 'sanctum')
            ->postJson("/api/lab-cases/{$case->id}/phase", ['phase' => LabCase::PHASE_IN_LABORATORY])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phase');

        $this->assertSame(LabCase::PHASE_TRY_IN, $case->fresh()->status);
    }

    #[Test]
    public function the_update_edits_the_work_and_never_moves_the_phase(): void
    {
        $professional = $this->staff('odontologo');
        $case = LabCase::factory()->create(['professional_id' => $professional->id]);

        $this->actingAs($professional, 'sanctum')
            ->putJson("/api/lab-cases/{$case->id}", [
                'work' => 'Prótesis total superior',
                'material' => 'Acrílico',
                'technician_id' => null,
                'status' => LabCase::PHASE_PLACED,
            ])
            ->assertOk()
            ->assertJsonPath('data.work', 'Prótesis total superior')
            ->assertJsonPath('data.material', 'Acrílico')
            ->assertJsonPath('data.status', LabCase::PHASE_IN_PREPARATION)
            ->assertJsonPath('meta.message', 'Caso de laboratorio actualizado exitosamente');

        $stored = $case->fresh();

        $this->assertSame('Prótesis total superior', $stored->work);
        $this->assertNull($stored->technician_id);
        $this->assertSame(LabCase::PHASE_IN_PREPARATION, $stored->status);
    }

    #[Test]
    public function the_index_filters_by_status_overdue_and_my_assigned_cases(): void
    {
        $admin = $this->staff('administrador');
        $technician = $this->staff('tecnico_dental');
        $otherTechnician = $this->staff('tecnico_dental');

        $overdue = LabCase::factory()->create([
            'technician_id' => $technician->id,
            'status' => LabCase::PHASE_IN_PREPARATION,
            'sent_at' => now()->subDays(20)->toDateString(),
            'promised_at' => now()->subDays(5)->toDateString(),
        ]);
        $delivered = LabCase::factory()->create([
            'technician_id' => $technician->id,
            'status' => LabCase::PHASE_DELIVERED,
            'delivered_at' => now(),
            'sent_at' => now()->subDays(20)->toDateString(),
            'promised_at' => now()->subDays(5)->toDateString(),
        ]);
        $foreign = LabCase::factory()->create([
            'technician_id' => $otherTechnician->id,
            'status' => LabCase::PHASE_IN_LABORATORY,
            'sent_at' => now()->subDay()->toDateString(),
            'promised_at' => now()->addDays(5)->toDateString(),
        ]);

        $byStatus = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/lab-cases?status='.LabCase::PHASE_IN_LABORATORY)
            ->assertOk();

        $byStatus->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $foreign->id);

        $overdueResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/lab-cases?overdue=1')
            ->assertOk();

        $overdueResponse->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $overdue->id);

        $mine = $this->actingAs($technician, 'sanctum')
            ->getJson('/api/lab-cases?technician_id=me')
            ->assertOk();

        $mine->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $overdue->id)->assertJsonPath('data.1.id', $delivered->id);
    }

    #[Test]
    public function another_professional_does_not_see_cases_that_are_not_theirs(): void
    {
        $professional = $this->staff('odontologo');
        $other = $this->staff('implantologo');
        $own = LabCase::factory()->create(['professional_id' => $professional->id]);
        LabCase::factory()->create(['professional_id' => $other->id]);

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson("/api/lab-cases?professional_id={$professional->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame([$own->id], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function the_show_publishes_the_case_with_its_relations(): void
    {
        $professional = $this->staff('odontologo');
        $technician = $this->staff('tecnico_dental');
        $case = LabCase::factory()->create([
            'professional_id' => $professional->id,
            'technician_id' => $technician->id,
            'created_by' => $professional->id,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson("/api/lab-cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.patient.id', $case->patient_id)
            ->assertJsonPath('data.professional.id', $professional->id)
            ->assertJsonPath('data.technician.id', $technician->id)
            ->assertJsonPath('data.created_by.id', $professional->id);
    }

    #[Test]
    public function a_restricted_user_does_not_reach_another_branch_case(): void
    {
        $branchA = \App\Models\Branch::factory()->create();
        $branchB = \App\Models\Branch::factory()->create();

        $professional = $this->staff('odontologo', ['branch_id' => $branchA->id]);
        $case = LabCase::factory()->create([
            'branch_id' => $branchB->id,
            'professional_id' => $professional->id,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson("/api/lab-cases/{$case->id}")
            ->assertNotFound();
    }
}
