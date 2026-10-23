<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #288 — explicit priority and sequence per plan line.
 */
class TreatmentPlanPriorityTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    public function test_priority_and_sequence_are_persisted_and_published(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan por fases',
                'items' => [
                    [
                        'description' => 'Urgencia',
                        'procedure_name' => 'Endodoncia',
                        'quantity' => 1,
                        'unit_cost' => 300,
                        'priority' => 'urgent',
                        'sequence' => 2,
                        'phase_number' => 1,
                    ],
                    [
                        'description' => 'Electivo',
                        'procedure_name' => 'Limpieza',
                        'quantity' => 1,
                        'unit_cost' => 80,
                        'priority' => 'elective',
                        'sequence' => 1,
                        'phase_number' => 1,
                    ],
                    [
                        'description' => 'Opcional',
                        'procedure_name' => 'Blanqueamiento',
                        'quantity' => 1,
                        'unit_cost' => 400,
                        'is_optional' => true,
                        'sequence' => 1,
                        'phase_number' => 2,
                    ],
                ],
            ])
            ->assertCreated();

        $plan = TreatmentPlan::findOrFail($response->json('data.id'));

        $show = $this->actingAs($author, 'sanctum')
            ->getJson("/api/treatment-plans/{$plan->id}")
            ->assertOk();

        $items = $show->json('data.items');

        $this->assertSame(['Limpieza', 'Endodoncia', 'Blanqueamiento'], array_column($items, 'procedure_name'));
        $this->assertSame('elective', $items[0]['priority']);
        $this->assertSame(1, $items[0]['sequence']);
        $this->assertSame('urgent', $items[1]['priority']);
        $this->assertSame(2, $items[1]['sequence']);
        $this->assertSame('optional', $items[2]['priority']);
        $this->assertTrue($items[2]['is_optional']);

        $this->assertSame(1, $show->json('data.phase_summary.0.phase_number'));
        $this->assertSame(2, $show->json('data.phase_summary.0.total'));
        $this->assertSame(1, $show->json('data.phase_summary.1.total'));
    }

    public function test_sequence_is_automatic_inside_a_phase_when_it_is_not_declared(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan secuencial',
                'items' => [
                    ['description' => 'Uno', 'procedure_name' => 'Primero', 'quantity' => 1, 'unit_cost' => 10],
                    ['description' => 'Dos', 'procedure_name' => 'Segundo', 'quantity' => 1, 'unit_cost' => 10],
                    ['description' => 'Tres', 'procedure_name' => 'Tercero', 'quantity' => 1, 'unit_cost' => 10, 'phase_number' => 2],
                ],
            ])
            ->assertCreated();

        $plan = TreatmentPlan::findOrFail($response->json('data.id'));

        $this->assertSame([1, 2], $plan->items()->where('phase_number', 1)->pluck('sequence')->all());
        $this->assertSame([1], $plan->items()->where('phase_number', 2)->pluck('sequence')->all());
    }

    public function test_is_optional_and_priority_stay_in_sync(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan opcional',
                'items' => [[
                    'description' => 'Opcional',
                    'procedure_name' => 'Blanqueamiento',
                    'quantity' => 1,
                    'unit_cost' => 200,
                    'is_optional' => true,
                ]],
            ])
            ->assertCreated();

        $item = TreatmentPlan::findOrFail($response->json('data.id'))->items()->sole();

        $this->assertSame('optional', $item->priority);
        $this->assertTrue($item->is_optional);
    }

    public function test_an_unknown_priority_is_rejected(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan inválido',
                'items' => [[
                    'description' => 'Prioridad inventada',
                    'procedure_name' => 'Limpieza',
                    'quantity' => 1,
                    'unit_cost' => 10,
                    'priority' => 'cuando_se_pueda',
                ]],
            ])
            ->assertStatus(422);
    }
}
