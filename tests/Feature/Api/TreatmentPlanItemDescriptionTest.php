<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #418 — the label says the line description is optional, and the API accepts
 * it that way: the procedure name is the required identity of the line, and the
 * description the user writes survives as `procedure_description`.
 */
class TreatmentPlanItemDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function createPlan(User $author, Patient $patient, array $items)
    {
        return $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan con descripción opcional',
                'items' => $items,
            ]);
    }

    public function test_a_line_without_description_is_accepted(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $response = $this->createPlan($author, $patient, [
            [
                'procedure_name' => 'Limpieza dental',
                'quantity' => 1,
                'unit_cost' => 80,
            ],
        ])->assertCreated();

        $item = TreatmentPlanItem::where(
            'treatment_plan_id',
            $response->json('data.id')
        )->sole();

        $this->assertSame('Limpieza dental', $item->procedure_name);
        $this->assertNull($item->procedure_description);
    }

    public function test_the_written_description_is_stored_as_the_line_description(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $response = $this->createPlan($author, $patient, [
            [
                'procedure_name' => 'Limpieza dental',
                'description' => 'Detartraje supragingival',
                'quantity' => 1,
                'unit_cost' => 80,
            ],
        ])->assertCreated();

        $planId = $response->json('data.id');

        $item = TreatmentPlanItem::where('treatment_plan_id', $planId)->sole();
        $this->assertSame('Detartraje supragingival', $item->procedure_description);

        $this->actingAs($author, 'sanctum')
            ->getJson("/api/treatment-plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.procedure_description', 'Detartraje supragingival');
    }

    public function test_updating_a_line_keeps_the_description_it_receives(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $planId = $this->createPlan($author, $patient, [
            [
                'procedure_name' => 'Limpieza dental',
                'description' => 'Detartraje inicial',
                'quantity' => 1,
                'unit_cost' => 80,
            ],
        ])->assertCreated()->json('data.id');

        $item = TreatmentPlanItem::where('treatment_plan_id', $planId)->sole();

        $this->actingAs($author, 'sanctum')
            ->putJson("/api/treatment-plans/{$planId}", [
                'items' => [
                    [
                        'id' => $item->id,
                        'procedure_name' => 'Limpieza dental',
                        'description' => 'Detartraje y pulido',
                        'quantity' => 1,
                        'unit_cost' => 80,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            'Detartraje y pulido',
            $item->fresh()->procedure_description
        );
    }

    public function test_adding_a_line_persists_its_description(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $planId = $this->createPlan($author, $patient, [
            [
                'procedure_name' => 'Limpieza dental',
                'quantity' => 1,
                'unit_cost' => 80,
            ],
        ])->assertCreated()->json('data.id');

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/{$planId}/add-item", [
                'procedure_name' => 'Blanqueamiento',
                'description' => 'Aclaramiento en consultorio',
                'quantity' => 1,
                'unit_cost' => 200,
            ])
            ->assertCreated();

        $added = TreatmentPlanItem::where('procedure_name', 'Blanqueamiento')->sole();
        $this->assertSame('Aclaramiento en consultorio', $added->procedure_description);
    }

    public function test_the_procedure_name_is_still_required(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $this->createPlan($author, $patient, [
            [
                'description' => 'Solo una descripción',
                'quantity' => 1,
                'unit_cost' => 80,
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.procedure_name']);

        $this->assertSame(0, TreatmentPlanItem::count());
    }
}
