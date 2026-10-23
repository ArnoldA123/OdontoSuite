<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemAlternative;
use App\Models\TreatmentPlanItemConsumption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #288 — option A/B per line and the record of the declined option.
 */
class TreatmentPlanAlternativesTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function planWithOptionalItem(User $author, Patient $patient): array
    {
        $response = $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan con alternativa',
                'items' => [[
                    'description' => 'Rehabilitación 16',
                    'procedure_name' => 'Corona de porcelana',
                    'quantity' => 1,
                    'unit_cost' => 800,
                    'priority' => 'optional',
                    'is_optional' => true,
                ]],
            ])
            ->assertCreated();

        $plan = TreatmentPlan::findOrFail($response->json('data.id'));

        return [$plan, $plan->items()->sole()];
    }

    private function addAlternative(User $author, TreatmentPlanItem $item, string $name, float $cost): TreatmentPlanItemAlternative
    {
        $response = $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/items/{$item->id}/alternatives", [
                'procedure_name' => $name,
                'quantity' => 1,
                'unit_cost' => $cost,
            ])
            ->assertCreated();

        return TreatmentPlanItemAlternative::findOrFail($response->json('data.id'));
    }

    public function test_accepting_one_option_replaces_the_line_and_records_the_other_as_rejected(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();
        [$plan, $item] = $this->planWithOptionalItem($author, $patient);

        $optionA = $this->addAlternative($author, $item, 'Implante unitario', 1500);
        $optionB = $this->addAlternative($author, $item, 'Puente de tres piezas', 1200);

        $response = $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/alternatives/{$optionA->id}/accept")
            ->assertOk();

        $this->assertSame('accepted', $response->json('data.alternative.status'));
        $this->assertSame('Implante unitario', $response->json('data.item.procedure_name'));
        $this->assertSame(1500.0, (float) $response->json('data.item.unit_cost'));

        $optionB->refresh();
        $this->assertSame('rejected', $optionB->status);
        $this->assertNotNull($optionB->rejected_at);
        $this->assertStringContainsString('Opción no elegida', $optionB->rejection_reason);

        $show = $this->actingAs($author, 'sanctum')
            ->getJson("/api/treatment-plans/{$plan->id}")
            ->assertOk();

        $this->assertCount(2, $show->json('data.items.0.alternatives'));
        $this->assertSame('Implante unitario', $show->json('data.items.0.procedure_name'));
    }

    public function test_rejecting_an_option_requires_and_records_the_reason(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();
        [, $item] = $this->planWithOptionalItem($author, $patient);

        $option = $this->addAlternative($author, $item, 'Implante unitario', 1500);

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/alternatives/{$option->id}/reject", [])
            ->assertStatus(422);

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/alternatives/{$option->id}/reject", [
                'reason' => 'El paciente prefiere posponer el implante.',
            ])
            ->assertOk();

        $option->refresh();
        $this->assertSame('rejected', $option->status);
        $this->assertSame('El paciente prefiere posponer el implante.', $option->rejection_reason);
        $this->assertNotNull($option->rejected_at);
    }

    public function test_an_optional_line_can_be_rejected_with_date_and_reason(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();
        [, $item] = $this->planWithOptionalItem($author, $patient);

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/items/{$item->id}/reject", [
                'reason' => 'El paciente decidió no realizar el tratamiento.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $item->refresh();
        $this->assertSame('cancelled', $item->status);
        $this->assertSame('El paciente decidió no realizar el tratamiento.', $item->rejection_reason);
        $this->assertNotNull($item->rejected_at);
    }

    public function test_a_non_optional_line_cannot_be_rejected_as_an_option(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan electivo',
                'items' => [[
                    'description' => 'Limpieza',
                    'procedure_name' => 'Limpieza dental',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'priority' => 'elective',
                ]],
            ])
            ->assertCreated();

        $item = TreatmentPlanItem::where('treatment_plan_id', $response->json('data.id'))->sole();

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/items/{$item->id}/reject", ['reason' => 'No aplica.'])
            ->assertStatus(422);
    }

    public function test_an_executed_line_cannot_change_its_option(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();
        [$plan, $item] = $this->planWithOptionalItem($author, $patient);
        $option = $this->addAlternative($author, $item, 'Implante unitario', 1500);

        $encounter = ClinicalEncounter::create([
            'patient_id' => $patient->id,
            'user_id' => $author->id,
            'mode' => ClinicalEncounter::MODE_CONSULTATION,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        TreatmentPlanItemConsumption::create([
            'clinical_encounter_id' => $encounter->id,
            'treatment_plan_item_id' => $item->id,
            'quantity' => 1,
            'consumed_by' => $author->id,
            'consumed_at' => now(),
        ]);

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/alternatives/{$option->id}/accept")
            ->assertStatus(422);

        $this->assertSame('Corona de porcelana', $item->fresh()->procedure_name);
        $this->assertSame('proposed', $option->fresh()->status);
    }

    public function test_an_alternative_changes_the_draft_version_of_the_plan(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();
        [$plan, $item] = $this->planWithOptionalItem($author, $patient);

        $versionId = $plan->fresh()->current_version_id;

        $this->addAlternative($author, $item, 'Implante unitario', 1500);

        $version = $plan->fresh()->currentVersion()->first();

        $this->assertSame($versionId, $version->id);
        $this->assertCount(1, $version->snapshot['items'][0]['alternatives']);
    }
}
