<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3 (#292) — the only correction is an addendum.
 *
 * The PUT/PATCH surface is gone, so a professional that documented something
 * wrong adds a note instead of rewriting evidence. The note carries its own
 * author (from the session, never from the body), date and reason, the original
 * row keeps every field, and the write is audited against the history.
 */
class ClinicalAddendumApiTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function receptionist(): User
    {
        return User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
    }

    public function test_the_author_comes_from_the_session_and_the_body_cannot_choose_it(): void
    {
        $author = $this->clinician();
        $intruder = $this->clinician();
        $record = MedicalRecord::factory()->create();

        $response = $this->actingAs($author, 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [
                'reason' => 'Error de transcripción',
                'content' => 'La pieza tratada fue la 16, no la 26.',
                'user_id' => $intruder->id,
                'medical_record_id' => 999999,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $author->id)
            ->assertJsonPath('data.author.id', $author->id)
            ->assertJsonPath('data.medical_record_id', $record->id)
            ->assertJsonPath('data.clinical_evolution_id', null)
            ->assertJsonPath('meta.message', 'Addendum registrado exitosamente');

        $this->assertDatabaseHas('clinical_addenda', [
            'id' => $response->json('data.id'),
            'medical_record_id' => $record->id,
            'clinical_evolution_id' => null,
            'user_id' => $author->id,
            'reason' => 'Error de transcripción',
        ]);
    }

    public function test_reason_and_content_are_required(): void
    {
        $record = MedicalRecord::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason', 'content']);

        $this->assertDatabaseCount('clinical_addenda', 0);
    }

    public function test_the_correction_text_is_sanitized(): void
    {
        $record = MedicalRecord::factory()->create();

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [
                'reason' => '<b>Corrección</b>',
                'content' => '<script>alert(1)</script>Se corrige el diagnóstico.',
            ]);

        $response->assertCreated();
        $this->assertSame('Corrección', $response->json('data.reason'));
        $this->assertSame('alert(1)Se corrige el diagnóstico.', $response->json('data.content'));
    }

    public function test_an_addendum_leaves_the_record_and_the_evolution_untouched(): void
    {
        $record = MedicalRecord::factory()->create([
            'diagnosis' => 'Caries oclusal',
            'notes' => 'Sin alergias conocidas',
        ]);
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'subjective' => 'Dolor al masticar',
            'assessment' => 'Caries',
            'plan' => 'Obturación',
        ]);

        $recordSnapshot = $record->fresh()->toArray();
        $evolutionSnapshot = $evolution->fresh()->toArray();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/evolutions/{$evolution->id}/addenda", [
                'reason' => 'Hallazgo adicional',
                'content' => 'La lesión comprometía la cúspide vestibular.',
            ])
            ->assertCreated();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [
                'reason' => 'Antecedente omitido',
                'content' => 'El paciente refiere alergia a la penicilina.',
            ])
            ->assertCreated();

        $this->assertSame($recordSnapshot, $record->fresh()->toArray());
        $this->assertSame($evolutionSnapshot, $evolution->fresh()->toArray());
    }

    public function test_the_addendum_is_listed_with_the_record_and_with_its_evolution(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();

        $evolutionAddendum = $this->actingAs($user, 'sanctum')
            ->postJson("/api/medical-records/evolutions/{$evolution->id}/addenda", [
                'reason' => 'Hallazgo adicional',
                'content' => 'La lesión comprometía la cúspide vestibular.',
            ])
            ->assertCreated()
            ->json('data.id');

        $recordAddendum = $this->actingAs($user, 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [
                'reason' => 'Antecedente omitido',
                'content' => 'El paciente refiere alergia a la penicilina.',
            ])
            ->assertCreated()
            ->json('data.id');

        $show = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}")
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$recordAddendum, $evolutionAddendum],
            array_column($show->json('data.addenda'), 'id')
        );
        $this->assertSame($user->id, $show->json('data.addenda.0.author.id'));

        $evolutions = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}/evolutions")
            ->assertOk();

        $this->assertSame(
            [$evolutionAddendum],
            array_column($evolutions->json('data.0.addenda'), 'id')
        );
        $this->assertSame($user->id, $evolutions->json('data.0.addenda.0.author.id'));
    }

    public function test_reception_cannot_add_an_addendum(): void
    {
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();
        $reception = $this->receptionist();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [
                'reason' => 'Motivo',
                'content' => 'Contenido',
            ])
            ->assertForbidden();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/medical-records/evolutions/{$evolution->id}/addenda", [
                'reason' => 'Motivo',
                'content' => 'Contenido',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('clinical_addenda', 0);
    }

    public function test_an_unknown_record_or_evolution_answers_404(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/medical-records/999999/addenda', [
                'reason' => 'Motivo',
                'content' => 'Contenido',
            ])
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/medical-records/evolutions/999999/addenda', [
                'reason' => 'Motivo',
                'content' => 'Contenido',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('clinical_addenda', 0);
        $this->assertDatabaseHas('medical_records', ['id' => $record->id]);
    }

    public function test_the_addendum_is_audited_against_the_history(): void
    {
        $author = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();

        $addendumId = $this->actingAs($author, 'sanctum')
            ->postJson("/api/medical-records/evolutions/{$evolution->id}/addenda", [
                'reason' => 'Hallazgo adicional',
                'content' => 'La lesión comprometía la cúspide vestibular.',
            ])
            ->assertCreated()
            ->json('data.id');

        $log = AuditLog::where('action', 'clinical_addendum_created')->sole();

        $this->assertSame($author->id, $log->user_id);
        $this->assertSame(MedicalRecord::class, $log->auditable_type);
        $this->assertSame($record->id, $log->auditable_id);
        $this->assertSame(['reason' => 'Hallazgo adicional'], $log->new_values);
        $this->assertSame([
            'clinical_addendum_id' => $addendumId,
            'clinical_evolution_id' => $evolution->id,
        ], $log->metadata);
    }
}
