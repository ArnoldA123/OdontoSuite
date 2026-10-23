<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemConsumption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4 (#284) — a plan is executed by acts that consume its lines.
 *
 * `status` is derived from the consumptions, the plan API refuses to write an
 * executed status by hand, and editing a plan reconciles lines instead of
 * deleting the history that points at them.
 */
class PlanSessionConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private function professional(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function inConsultation(User $professional, Patient $patient): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'status' => 'in_consultation',
            'checked_in_at' => now()->subMinutes(30),
            'scheduled_at' => now()->startOfDay()->addHours(9),
        ]);
    }

    private function plan(Patient $patient, User $author, array $items = []): TreatmentPlan
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $author->id,
            'status' => 'approved',
            'total_cost' => 0,
            'discount_amount' => 0,
            'final_cost' => 0,
        ]);

        foreach ($items as $item) {
            TreatmentPlanItem::create(array_merge([
                'treatment_plan_id' => $plan->id,
                'procedure_name' => 'Procedimiento',
                'quantity' => 1,
                'unit_cost' => 100,
                'total_cost' => 100,
                'phase_number' => 1,
                'status' => 'pending',
                'is_optional' => false,
            ], $item));
        }

        return $plan->refresh()->load('items');
    }

    /**
     * @param  array<int, array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    private function sessionPayload(TreatmentPlan $plan, array $lines, string $mode = 'plan_session'): array
    {
        return [
            'mode' => $mode,
            'skip_materials' => true,
            'evolution' => [
                'subjective' => 'Sesión de tratamiento.',
                'objective' => 'Procedimiento ejecutado según el plan.',
                'assessment' => 'Sin complicaciones.',
                'plan' => 'Continuar con la siguiente fase.',
            ],
            'treatment_plan' => [
                'id' => $plan->id,
                'items' => $lines,
            ],
        ];
    }

    public function test_a_session_consumes_the_line_and_completes_it(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);
        $plan = $this->plan($patient, $professional, [
            ['procedure_name' => 'Endodoncia', 'quantity' => 2, 'unit_cost' => 100, 'total_cost' => 200],
        ]);
        $item = $plan->items->first();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->sessionPayload($plan, [
                [
                    'id' => $item->id,
                    'procedure_name' => $item->procedure_name,
                    'quantity' => 2,
                    'consumed_quantity' => 2,
                    'status' => 'completed',
                ],
            ]))
            ->assertOk();

        $consumption = TreatmentPlanItemConsumption::sole();
        $this->assertSame($item->id, $consumption->treatment_plan_item_id);
        $this->assertSame('2.00', $consumption->quantity);
        $this->assertSame('completed', $item->fresh()->status);

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson("/api/treatment-plans/{$plan->id}")
            ->assertOk();

        $this->assertSame(2, $response->json('data.items.0.executed_quantity'));
        $this->assertSame(0, $response->json('data.items.0.pending_quantity'));
        $this->assertSame(1, $response->json('data.items.0.session_count'));
        $this->assertSame($consumption->clinical_encounter_id, $response->json('data.items.0.consumptions.0.clinical_encounter_id'));
    }

    public function test_a_partial_consumption_leaves_the_line_in_progress(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);
        $plan = $this->plan($patient, $professional, [
            ['procedure_name' => 'Obturación', 'quantity' => 2, 'unit_cost' => 100, 'total_cost' => 200],
        ]);
        $item = $plan->items->first();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->sessionPayload($plan, [
                [
                    'id' => $item->id,
                    'procedure_name' => $item->procedure_name,
                    'quantity' => 1,
                    'consumed_quantity' => 1,
                ],
            ]))
            ->assertOk();

        $item->refresh();
        $this->assertSame('in_progress', $item->status);
        $this->assertSame(1.0, $item->executedQuantity());
        $this->assertSame(1.0, $item->pendingQuantity());
    }

    public function test_the_plan_api_rejects_an_executed_status_written_by_hand(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $plan = $this->plan($patient, $professional, [
            ['procedure_name' => 'Sellante', 'quantity' => 1, 'unit_cost' => 80, 'total_cost' => 80],
        ]);
        $item = $plan->items->first();

        $this->actingAs($professional, 'sanctum')
            ->putJson("/api/treatment-plans/{$plan->id}", [
                'items' => [[
                    'id' => $item->id,
                    'procedure_name' => $item->procedure_name,
                    'quantity' => 1,
                    'unit_cost' => 80,
                    'status' => 'completed',
                ]],
            ])
            ->assertStatus(422);

        $this->assertSame('pending', $item->fresh()->status);
        $this->assertSame(0, TreatmentPlanItemConsumption::count());
    }

    public function test_editing_a_plan_reconciles_lines_and_preserves_the_consumption(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);
        $plan = $this->plan($patient, $professional, [
            ['procedure_name' => 'Endodoncia', 'quantity' => 1, 'unit_cost' => 100, 'total_cost' => 100],
        ]);
        $item = $plan->items->first();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->sessionPayload($plan, [
                [
                    'id' => $item->id,
                    'procedure_name' => $item->procedure_name,
                    'quantity' => 1,
                    'consumed_quantity' => 1,
                    'status' => 'completed',
                ],
            ]))
            ->assertOk();

        $this->actingAs($professional, 'sanctum')
            ->putJson("/api/treatment-plans/{$plan->id}", [
                'items' => [
                    [
                        'id' => $item->id,
                        'procedure_name' => $item->procedure_name,
                        'quantity' => 1,
                        'unit_cost' => 150,
                    ],
                    [
                        'procedure_name' => 'Corona',
                        'quantity' => 1,
                        'unit_cost' => 400,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame(1, TreatmentPlanItemConsumption::count());
        $this->assertSame($item->id, TreatmentPlanItemConsumption::sole()->treatment_plan_item_id);
        $this->assertSame(2, TreatmentPlanItem::where('treatment_plan_id', $plan->id)->count());
        $this->assertSame('completed', $item->fresh()->status);
    }

    public function test_a_consumed_line_cannot_be_removed_by_editing_the_plan(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);
        $plan = $this->plan($patient, $professional, [
            ['procedure_name' => 'Endodoncia', 'quantity' => 1, 'unit_cost' => 100, 'total_cost' => 100],
        ]);
        $item = $plan->items->first();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->sessionPayload($plan, [
                [
                    'id' => $item->id,
                    'procedure_name' => $item->procedure_name,
                    'quantity' => 1,
                    'consumed_quantity' => 1,
                    'status' => 'completed',
                ],
            ]))
            ->assertOk();

        $this->actingAs($professional, 'sanctum')
            ->putJson("/api/treatment-plans/{$plan->id}", ['items' => []])
            ->assertStatus(422);

        $this->assertSame(1, TreatmentPlanItem::where('treatment_plan_id', $plan->id)->count());
        $this->assertSame(1, TreatmentPlanItemConsumption::count());
    }

    public function test_add_item_cannot_create_an_executed_line(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $plan = $this->plan($patient, $professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/treatment-plans/{$plan->id}/add-item", [
                'procedure_name' => 'Implante',
                'quantity' => 1,
                'unit_cost' => 1200,
                'status' => 'completed',
            ])
            ->assertStatus(422);

        $this->assertSame(0, TreatmentPlanItem::where('treatment_plan_id', $plan->id)->count());
    }

    public function test_an_execution_act_records_its_own_consumptions(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", [
                'mode' => 'execution',
                'skip_materials' => true,
                'evolution' => [
                    'subjective' => 'Dolor.',
                    'objective' => 'Caries.',
                    'assessment' => 'Caries dentinaria.',
                    'plan' => 'Obturación.',
                ],
                'treatment_plan' => [
                    'title' => 'Restauración',
                    'items' => [[
                        'procedure_name' => 'Obturación composite',
                        'quantity' => 1,
                        'unit_cost' => 180,
                    ]],
                ],
            ])
            ->assertOk();

        $plan = TreatmentPlan::where('origin_appointment_id', $appointment->id)->sole();
        $item = $plan->items()->sole();

        $this->assertSame('completed', $item->status);
        $this->assertSame(1, TreatmentPlanItemConsumption::where('treatment_plan_item_id', $item->id)->count());
        $this->assertSame('180.00', $appointment->fresh()->final_amount);
    }

    public function test_a_session_cannot_consume_a_line_of_another_plan(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);
        $plan = $this->plan($patient, $professional);
        $other = $this->plan($patient, $professional, [
            ['procedure_name' => 'Limpieza', 'quantity' => 1, 'unit_cost' => 60, 'total_cost' => 60],
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->sessionPayload($plan, [
                [
                    'id' => $other->items->first()->id,
                    'procedure_name' => 'Limpieza',
                    'quantity' => 1,
                    'consumed_quantity' => 1,
                ],
            ]))
            ->assertStatus(422);

        $this->assertSame(0, TreatmentPlanItemConsumption::count());
    }
}
