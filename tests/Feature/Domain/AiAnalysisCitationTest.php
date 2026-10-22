<?php

namespace Tests\Feature\Domain;

use App\Models\AiImageAnalysis;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #296 — an analysis enters the record only when a professional reviewed it.
 *
 * The citation is the only door: an evolution and a plan both cite, an
 * unreviewed, foreign, unfinished or annulled analysis is refused, and a cited
 * analysis cannot be annulled while the document that cites it still exists.
 */
class AiAnalysisCitationTest extends TestCase
{
    use RefreshDatabase;

    protected function clinician(string $role = 'odontologo'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    protected function reviewedAnalysis(Patient $patient, User $user): AiImageAnalysis
    {
        return AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'status' => 'completed',
            'reviewed' => true,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_decision' => 'accepted',
        ]);
    }

    protected function record(Patient $patient, User $user): MedicalRecord
    {
        return MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_an_evolution_cites_a_reviewed_analysis_and_publishes_the_citation(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $analysis = $this->reviewedAnalysis($patient, $doctor);

        $response = $this->actingAs($doctor)->postJson('/api/medical-records/'.$record->id.'/evolutions', [
            'subjective' => 'Control postoperatorio',
            'assessment' => 'Sin signos de infección',
            'cited_analysis_ids' => [$analysis->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.cited_analyses.0.id', $analysis->id)
            ->assertJsonPath('data.cited_analyses.0.review_decision', 'accepted');

        $this->assertDatabaseHas('ai_analysis_citations', [
            'citable_type' => (new ClinicalEvolution)->getMorphClass(),
            'ai_image_analysis_id' => $analysis->id,
            'cited_by' => $doctor->id,
        ]);
    }

    public function test_an_unreviewed_analysis_cannot_be_cited(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $analysis = AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'status' => 'completed',
            'reviewed' => false,
        ]);

        $this->actingAs($doctor)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control',
                'cited_analysis_ids' => [$analysis->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.cited_analysis_ids.0',
                'El análisis no puede citarse porque no fue revisado por un profesional.',
            );

        $this->assertDatabaseCount('clinical_evolutions', 0);
        $this->assertDatabaseCount('ai_analysis_citations', 0);
    }

    public function test_an_analysis_of_another_patient_cannot_be_cited(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $foreign = $this->reviewedAnalysis(Patient::factory()->create(), $doctor);

        $this->actingAs($doctor)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control',
                'cited_analysis_ids' => [$foreign->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.cited_analysis_ids.0',
                'El análisis citado no pertenece al paciente de la historia clínica.',
            );

        $this->assertDatabaseCount('ai_analysis_citations', 0);
    }

    public function test_an_annulled_analysis_cannot_be_cited(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $analysis = $this->reviewedAnalysis($patient, $doctor);
        $analysis->update([
            'voided_at' => now(),
            'voided_by' => $doctor->id,
            'void_reason' => 'Estudio duplicado',
        ]);

        $this->actingAs($doctor)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control',
                'cited_analysis_ids' => [$analysis->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.cited_analysis_ids.0',
                'El análisis citado fue anulado y no puede volver a citarse.',
            );

        $this->assertDatabaseCount('ai_analysis_citations', 0);
    }

    public function test_an_unknown_analysis_cannot_be_cited(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);

        $this->actingAs($doctor)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control',
                'cited_analysis_ids' => [999999],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.cited_analysis_ids.0', 'El análisis citado no existe.');
    }

    public function test_the_same_analysis_cited_twice_in_one_evolution_is_stored_once(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $analysis = $this->reviewedAnalysis($patient, $doctor);

        $this->actingAs($doctor)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control',
                'cited_analysis_ids' => [$analysis->id, $analysis->id],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.cited_analyses');

        $this->assertDatabaseCount('ai_analysis_citations', 1);
    }

    public function test_a_treatment_plan_cites_a_reviewed_analysis(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $analysis = $this->reviewedAnalysis($patient, $doctor);

        $response = $this->actingAs($doctor)->postJson('/api/treatment-plans', [
            'patient_id' => $patient->id,
            'title' => 'Rehabilitación oral',
            'cited_analysis_ids' => [$analysis->id],
        ]);

        $response->assertCreated();

        $plan = TreatmentPlan::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('ai_analysis_citations', [
            'citable_type' => $plan->getMorphClass(),
            'citable_id' => $plan->id,
            'ai_image_analysis_id' => $analysis->id,
        ]);
        $this->assertSame(1, $plan->citedAnalyses()->count());
    }

    public function test_a_plan_refuses_an_unreviewed_analysis(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $analysis = AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'status' => 'completed',
            'reviewed' => false,
        ]);

        $this->actingAs($doctor)
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Rehabilitación oral',
                'cited_analysis_ids' => [$analysis->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.cited_analysis_ids.0',
                'El análisis no puede citarse porque no fue revisado por un profesional.',
            );

        $this->assertDatabaseCount('treatment_plans', 0);
        $this->assertDatabaseCount('ai_analysis_citations', 0);
    }

    public function test_updating_a_plan_replaces_its_citations(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $first = $this->reviewedAnalysis($patient, $doctor);
        $second = $this->reviewedAnalysis($patient, $doctor);

        $this->actingAs($doctor)->postJson('/api/treatment-plans', [
            'patient_id' => $patient->id,
            'title' => 'Rehabilitación oral',
            'cited_analysis_ids' => [$first->id],
        ])->assertCreated();

        $plan = TreatmentPlan::query()->latest('id')->firstOrFail();

        $this->actingAs($doctor)
            ->putJson('/api/treatment-plans/'.$plan->id, [
                'title' => 'Rehabilitación oral revisada',
                'cited_analysis_ids' => [$second->id],
            ])
            ->assertOk();

        $cited = $plan->citedAnalyses()->pluck('ai_image_analyses.id')->all();

        $this->assertSame([$second->id], $cited);
        $this->assertDatabaseMissing('ai_analysis_citations', [
            'citable_id' => $plan->id,
            'ai_image_analysis_id' => $first->id,
        ]);
    }

    public function test_a_cited_analysis_cannot_be_annulled(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $analysis = $this->reviewedAnalysis($patient, $doctor);

        $this->actingAs($doctor)->postJson('/api/medical-records/'.$record->id.'/evolutions', [
            'subjective' => 'Control',
            'cited_analysis_ids' => [$analysis->id],
        ])->assertCreated();

        $this->actingAs($doctor)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/void', ['reason' => 'Estudio duplicado'])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.analysis.0',
                'No se puede anular un análisis citado en una evolución o en un plan.',
            );

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'voided_at' => null,
        ]);
        $this->assertDatabaseCount('ai_analysis_citations', 1);
    }

    public function test_the_laboratory_desktop_cannot_cite_an_analysis(): void
    {
        $technician = $this->clinician('tecnico_dental');
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $technician);
        $analysis = $this->reviewedAnalysis($patient, $this->clinician());

        $this->actingAs($technician)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control',
                'cited_analysis_ids' => [$analysis->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.cited_analysis_ids.0',
                'No tiene permiso para citar análisis de IA en el registro clínico.',
            );

        $this->assertDatabaseCount('clinical_evolutions', 0);
        $this->assertDatabaseCount('ai_analysis_citations', 0);
    }

    public function test_the_laboratory_desktop_keeps_writing_evolutions_without_citations(): void
    {
        $technician = $this->clinician('tecnico_dental');
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $technician);

        $this->actingAs($technician)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control de laboratorio',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('clinical_evolutions', 1);
    }

    public function test_an_evolution_without_citations_keeps_the_existing_contract(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);

        $this->actingAs($doctor)
            ->postJson('/api/medical-records/'.$record->id.'/evolutions', [
                'subjective' => 'Control sin imágenes',
            ])
            ->assertCreated()
            ->assertJsonPath('data.cited_analyses', []);

        $this->assertDatabaseCount('ai_analysis_citations', 0);
    }

    public function test_the_evolution_timeline_publishes_its_citations(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $analysis = $this->reviewedAnalysis($patient, $doctor);

        $this->actingAs($doctor)->postJson('/api/medical-records/'.$record->id.'/evolutions', [
            'subjective' => 'Control',
            'cited_analysis_ids' => [$analysis->id],
        ])->assertCreated();

        $this->actingAs($doctor)
            ->getJson('/api/medical-records/'.$record->id.'/evolutions')
            ->assertOk()
            ->assertJsonPath('data.0.cited_analyses.0.id', $analysis->id);
    }

    public function test_the_cited_analysis_travels_as_a_decision_not_as_the_whole_row(): void
    {
        $doctor = $this->clinician();
        $patient = Patient::factory()->create();
        $record = $this->record($patient, $doctor);
        $analysis = $this->reviewedAnalysis($patient, $doctor);
        $analysis->update([
            'findings' => [['diagnosis' => 'Caries oclusal', 'confidence' => 90]],
            'review_notes' => 'Nota interna del revisor',
            'tokens_used' => 1234,
            'estimated_cost' => 0.0123,
        ]);

        $this->actingAs($doctor)->postJson('/api/medical-records/'.$record->id.'/evolutions', [
            'subjective' => 'Control',
            'cited_analysis_ids' => [$analysis->id],
        ])->assertCreated();

        $response = $this->actingAs($doctor)
            ->getJson('/api/medical-records/'.$record->id.'/evolutions')
            ->assertOk();

        $cited = $response->json('data.0.cited_analyses.0');

        $this->assertSame($analysis->id, $cited['id']);
        $this->assertSame('accepted', $cited['review_decision']);
        $this->assertArrayHasKey('reviewed_at', $cited);
        $this->assertArrayHasKey('confidence_score', $cited);

        // A reader of the record may not hold view.ai_analysis (#283 still lets
        // the laboratory read the record), so provider detail stays out.
        foreach (['findings', 'review_notes', 'tokens_used', 'estimated_cost', 'prompt_sent', 'raw_response'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $cited, "The citation must not publish [{$hidden}].");
        }
    }
}
