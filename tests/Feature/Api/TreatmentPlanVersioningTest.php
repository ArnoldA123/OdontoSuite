<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #288 — the accepted plan is a version, not a moving row.
 *
 * A signed quotation points at the accepted snapshot, so editing the plan never
 * rewrites what the patient approved.
 */
class TreatmentPlanVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'is_active' => true]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function createPlan(User $author, Patient $patient, array $items = []): TreatmentPlan
    {
        $items = $items ?: [[
            'description' => 'Limpieza dental',
            'procedure_name' => 'Limpieza dental',
            'quantity' => 1,
            'unit_cost' => 100,
        ]];

        $response = $this->actingAs($author, 'sanctum')
            ->postJson('/api/treatment-plans', [
                'patient_id' => $patient->id,
                'title' => 'Plan de tratamiento',
                'items' => $items,
            ])
            ->assertCreated();

        return TreatmentPlan::findOrFail($response->json('data.id'));
    }

    private function approvePlan(User $author, TreatmentPlan $plan): void
    {
        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/{$plan->id}/change-status", ['status' => 'proposed'])
            ->assertOk();

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/treatment-plans/{$plan->id}/change-status", ['status' => 'approved'])
            ->assertOk();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function editItems(User $author, TreatmentPlan $plan, array $items): void
    {
        $this->actingAs($author, 'sanctum')
            ->putJson("/api/treatment-plans/{$plan->id}", ['items' => $items])
            ->assertOk();
    }

    public function test_creating_a_plan_opens_its_first_draft_version(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();

        $plan = $this->createPlan($admin, $patient);
        $plan->refresh()->load('currentVersion');

        $this->assertNotNull($plan->current_version_id);
        $this->assertNull($plan->active_version_id);
        $this->assertSame(1, $plan->currentVersion->version_number);
        $this->assertSame(TreatmentPlanVersion::STATUS_DRAFT, $plan->currentVersion->status);
        $this->assertSame('Plan de tratamiento', $plan->currentVersion->snapshot['plan']['title']);
        $this->assertCount(1, $plan->currentVersion->snapshot['items']);
    }

    public function test_approving_the_plan_accepts_the_current_version(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $plan = $this->createPlan($admin, $patient);

        $this->approvePlan($admin, $plan);

        $plan->refresh()->load(['currentVersion', 'activeVersion']);

        $this->assertSame($plan->current_version_id, $plan->active_version_id);
        $this->assertSame(TreatmentPlanVersion::STATUS_ACCEPTED, $plan->activeVersion->status);
        $this->assertNotNull($plan->activeVersion->accepted_at);
        $this->assertSame($admin->id, $plan->activeVersion->accepted_by);
    }

    public function test_editing_an_accepted_plan_creates_a_new_version_and_keeps_the_accepted_one(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $plan = $this->createPlan($admin, $patient);
        $this->approvePlan($admin, $plan);

        $acceptedId = $plan->fresh()->active_version_id;
        $acceptedSnapshot = TreatmentPlanVersion::findOrFail($acceptedId)->snapshot;

        $item = $plan->items()->sole();

        $this->editItems($admin, $plan, [[
            'id' => $item->id,
            'description' => $item->procedure_description,
            'procedure_name' => $item->procedure_name,
            'quantity' => 2,
            'unit_cost' => 100,
        ]]);

        $plan->refresh();

        $this->assertSame($acceptedId, (int) $plan->active_version_id);
        $this->assertNotSame((int) $plan->active_version_id, (int) $plan->current_version_id);
        $this->assertSame(2, TreatmentPlanVersion::where('treatment_plan_id', $plan->id)->count());

        $newVersion = $plan->currentVersion()->first();
        $this->assertSame(TreatmentPlanVersion::STATUS_DRAFT, $newVersion->status);
        $this->assertSame(200.0, (float) $newVersion->snapshot['plan']['final_cost']);

        $this->assertSame(
            $acceptedSnapshot,
            TreatmentPlanVersion::findOrFail($acceptedId)->snapshot,
            'The accepted version must not be rewritten by a later edit.'
        );
    }

    public function test_an_approved_quotation_keeps_pointing_at_the_accepted_version(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $plan = $this->createPlan($admin, $patient);
        $this->approvePlan($admin, $plan);

        $acceptedId = (int) $plan->fresh()->active_version_id;

        $quotation = Quotation::create([
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'created_by' => $admin->id,
            'quotation_number' => 'Q-'.Str::upper(Str::random(8)),
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'subtotal' => 100,
            'total_amount' => 100,
            'status' => 'sent',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/quotations/{$quotation->id}/approve")
            ->assertOk();

        $this->assertSame($acceptedId, (int) $quotation->fresh()->treatment_plan_version_id);

        $item = $plan->items()->sole();

        $this->editItems($admin, $plan, [[
            'id' => $item->id,
            'description' => $item->procedure_description,
            'procedure_name' => $item->procedure_name,
            'quantity' => 1,
            'unit_cost' => 250,
        ]]);

        $this->assertSame($acceptedId, (int) $quotation->fresh()->treatment_plan_version_id);
        $this->assertSame('100.00', $quotation->fresh()->total_amount);
    }

    public function test_a_partial_line_update_does_not_zero_its_total(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();

        $plan = $this->createPlan($admin, $patient, [[
            'description' => 'Dos obturaciones',
            'procedure_name' => 'Obturación composite',
            'quantity' => 2,
            'unit_cost' => 100,
        ]]);

        $item = $plan->items()->sole();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/treatment-plans/{$plan->id}", [
                'items' => [[
                    'id' => $item->id,
                    'procedure_name' => 'Obturación composite',
                ]],
            ])
            ->assertOk();

        $item->refresh();
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame('100.00', $item->unit_cost);
        $this->assertSame('200.00', $item->total_cost);
        $this->assertSame('200.00', $plan->fresh()->total_cost);
    }

    public function test_the_version_history_and_a_single_snapshot_are_readable(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $plan = $this->createPlan($admin, $patient);
        $this->approvePlan($admin, $plan);

        $item = $plan->items()->sole();
        $this->editItems($admin, $plan, [[
            'id' => $item->id,
            'description' => $item->procedure_description,
            'procedure_name' => $item->procedure_name,
            'quantity' => 1,
            'unit_cost' => 150,
        ]]);

        $history = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/treatment-plans/{$plan->id}/versions")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $history);
        $this->assertSame(2, $history[0]['version_number']);

        $versionId = $history[1]['id'];

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/treatment-plans/{$plan->id}/versions/{$versionId}")
            ->assertOk()
            ->assertJsonPath('data.snapshot.plan.title', 'Plan de tratamiento');
    }

    public function test_the_state_machine_refuses_an_invalid_transition_with_422(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $plan = $this->createPlan($admin, $patient);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/treatment-plans/{$plan->id}/change-status", ['status' => 'in_progress'])
            ->assertStatus(422);
    }

    public function test_a_plan_cannot_be_completed_while_a_live_line_is_pending(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $plan = $this->createPlan($admin, $patient);
        $this->approvePlan($admin, $plan);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/treatment-plans/{$plan->id}/change-status", ['status' => 'in_progress'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/treatment-plans/{$plan->id}/change-status", ['status' => 'completed'])
            ->assertStatus(422);

        $this->assertSame('in_progress', $plan->fresh()->status);
    }

    public function test_duplicating_a_plan_opens_a_fresh_first_version(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $plan = $this->createPlan($admin, $patient);
        $this->approvePlan($admin, $plan);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/treatment-plans/{$plan->id}/duplicate")
            ->assertCreated();

        $copy = TreatmentPlan::findOrFail($response->json('data.id'));
        $copy->refresh()->load('currentVersion');

        $this->assertSame(1, $copy->currentVersion->version_number);
        $this->assertSame(TreatmentPlanVersion::STATUS_DRAFT, $copy->currentVersion->status);
        $this->assertNull($copy->active_version_id);
    }
}
