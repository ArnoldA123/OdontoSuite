<?php

namespace Tests\Feature\Domain;

use App\Models\AiAnalysisCitation;
use App\Models\AiImageAnalysis;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * #296 — the decision-support shape of an AI analysis.
 *
 * An analysis is not a clinical diagnosis of its own: it hangs from the act,
 * carries the patient notice declaration, is annulled instead of deleted and
 * has a cost. Citations are the only door through which it enters the record
 * and they are polymorphic because an evolution and a plan both cite.
 */
class AiAnalysisDecisionSupportSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_analysis_table_carries_the_act_the_void_trace_the_notice_and_the_cost(): void
    {
        foreach ([
            'clinical_encounter_id',
            'voided_at',
            'voided_by',
            'void_reason',
            'notice_acknowledged_at',
            'notice_acknowledged_by',
            'estimated_cost',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('ai_image_analyses', $column),
                "ai_image_analyses must declare [{$column}].",
            );
        }
    }

    public function test_a_fresh_analysis_is_neither_voided_nor_costed(): void
    {
        $analysis = AiImageAnalysis::factory()->create();

        $this->assertFalse($analysis->isVoided());
        $this->assertNull($analysis->voided_at);
        $this->assertNull($analysis->voided_by);
        $this->assertNull($analysis->estimated_cost);
        $this->assertSame(1200, $analysis->tokens_used);
    }

    public function test_the_voided_scope_excludes_an_annulled_analysis(): void
    {
        $active = AiImageAnalysis::factory()->create();
        $voided = AiImageAnalysis::factory()->create([
            'voided_at' => now(),
            'voided_by' => User::factory()->create(['role' => 'odontologo'])->id,
            'void_reason' => 'Duplicado del estudio previo',
        ]);

        $ids = AiImageAnalysis::query()->active()->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($voided->id, $ids);
        $this->assertTrue($voided->fresh()->isVoided());
    }

    public function test_the_analysis_belongs_to_the_clinical_encounter_of_its_attachment(): void
    {
        $user = User::factory()->create(['role' => 'odontologo']);
        $patient = Patient::factory()->create();
        $encounter = ClinicalEncounter::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $analysis = AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'clinical_encounter_id' => $encounter->id,
        ]);

        $this->assertTrue($analysis->clinicalEncounter->is($encounter));
        $this->assertSame($encounter->id, $analysis->fresh()->clinical_encounter_id);
    }

    public function test_the_citation_table_is_polymorphic_and_unique_per_pair(): void
    {
        foreach ([
            'citable_type',
            'citable_id',
            'ai_image_analysis_id',
            'cited_by',
            'created_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('ai_analysis_citations', $column),
                "ai_analysis_citations must declare [{$column}].",
            );
        }

        $this->assertFalse(
            Schema::hasColumn('ai_analysis_citations', 'updated_at'),
            'A citation is append-only: it must not carry updated_at.',
        );
    }

    public function test_an_evolution_cites_analyses_through_the_shared_citation_table(): void
    {
        $analysis = AiImageAnalysis::factory()->create();
        $evolution = ClinicalEvolution::factory()->create([
            'patient_id' => $analysis->patient_id,
            'medical_record_id' => \App\Models\MedicalRecord::factory()->create([
                'patient_id' => $analysis->patient_id,
            ])->id,
        ]);

        $citation = AiAnalysisCitation::create([
            'citable_type' => $evolution->getMorphClass(),
            'citable_id' => $evolution->id,
            'ai_image_analysis_id' => $analysis->id,
            'cited_by' => $analysis->requested_by,
        ]);

        $this->assertTrue($citation->citable->is($evolution));
        $this->assertTrue($citation->analysis->is($analysis));
        $this->assertSame(1, $evolution->citedAnalyses()->count());
        $this->assertTrue($evolution->citedAnalyses->first()->is($analysis));
    }

    public function test_a_treatment_plan_cites_analyses_through_the_shared_citation_table(): void
    {
        $analysis = AiImageAnalysis::factory()->create();
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $analysis->patient_id,
            'created_by' => $analysis->requested_by,
        ]);

        AiAnalysisCitation::create([
            'citable_type' => $plan->getMorphClass(),
            'citable_id' => $plan->id,
            'ai_image_analysis_id' => $analysis->id,
            'cited_by' => $analysis->requested_by,
        ]);

        $this->assertSame(1, $plan->citedAnalyses()->count());
        $this->assertSame($analysis->id, $plan->citedAnalyses->first()->id);
    }
}
