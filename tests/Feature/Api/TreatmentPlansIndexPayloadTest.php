<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #417 — the list publishes the lines and the total the detail already has.
 *
 * The card counted "0 procedimientos" and the edit form opened empty because
 * the index returned the bare page without `items`, never the plan resource.
 */
class TreatmentPlansIndexPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function createPlan(User $author, Patient $patient, string $title, array $items): int
    {
        return (int) $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => $title,
                'items' => $items,
            ])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_the_index_publishes_the_lines_and_progress_of_each_plan(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Quispe',
        ]);

        $withTwoLines = $this->createPlan($author, $patient, 'Rehabilitación oral', [
            [
                'description' => 'Limpieza',
                'procedure_name' => 'Limpieza dental',
                'quantity' => 1,
                'unit_cost' => 80,
            ],
            [
                'description' => 'Corona',
                'procedure_name' => 'Corona de porcelana',
                'quantity' => 1,
                'unit_cost' => 100,
                'phase_number' => 2,
            ],
        ]);

        $withOneLine = $this->createPlan($author, $patient, 'Control', [
            [
                'description' => 'Control',
                'procedure_name' => 'Control postoperatorio',
                'quantity' => 1,
                'unit_cost' => 50,
            ],
        ]);

        $response = $this->actingAs($author, 'sanctum')
            ->getJson('/api/treatment-plans')
            ->assertOk();

        $rows = collect($response->json('data'));

        $first = $rows->firstWhere('id', $withTwoLines);
        $this->assertNotNull($first, 'the plan with two lines must be listed');
        $this->assertCount(2, $first['items']);
        $this->assertSame(2, $first['progress']['total_items']);
        $this->assertSame(180.0, (float) $first['final_cost']);
        $this->assertSame('Corona de porcelana', $first['items'][1]['procedure_name']);
        $this->assertSame('Ana', $first['patient']['first_name']);
        $this->assertArrayHasKey('is_overdue', $first);
        $this->assertArrayHasKey('created_at', $first);

        $second = $rows->firstWhere('id', $withOneLine);
        $this->assertNotNull($second, 'the plan with one line must be listed');
        $this->assertCount(1, $second['items']);
        $this->assertSame(1, $second['progress']['total_items']);
    }

    public function test_the_single_plan_door_keeps_publishing_the_same_lines(): void
    {
        $author = $this->clinician();
        $patient = Patient::factory()->create();

        $planId = $this->createPlan($author, $patient, 'Plan de una línea', [
            [
                'description' => 'Endodoncia',
                'procedure_name' => 'Endodoncia unirradicular',
                'quantity' => 1,
                'unit_cost' => 300,
            ],
        ]);

        $show = $this->actingAs($author, 'sanctum')
            ->getJson("/api/treatment-plans/{$planId}")
            ->assertOk();

        $this->assertCount(1, $show->json('data.items'));
        $this->assertSame(1, $show->json('data.progress.total_items'));
        $this->assertSame(
            $show->json('data.items.0.procedure_name'),
            $this->actingAs($author, 'sanctum')
                ->getJson('/api/treatment-plans')
                ->json('data.0.items.0.procedure_name')
        );
    }
}
