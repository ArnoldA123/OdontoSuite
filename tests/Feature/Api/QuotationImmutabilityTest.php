<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\TreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #289, S2 — an accepted quotation is immutable.
 *
 * The catalog and the plan can move after an estimate was accepted: the
 * quotation keeps the price it was signed with, and no edit door can rewrite it
 * once it left the decision-pending states.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class QuotationImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum');
    }

    private function quotation(string $status, array $overrides = []): Quotation
    {
        return Quotation::create(array_merge([
            'treatment_plan_id' => null,
            'patient_id' => Patient::factory()->create()->id,
            'created_by' => $this->admin->id,
            'quotation_number' => 'PR-'.strtoupper(Str::random(8)),
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 100,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'status' => $status,
        ], $overrides));
    }

    public function test_a_catalog_change_after_approval_does_not_change_the_quotation_amounts(): void
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'created_by' => $this->admin->id,
        ]);

        app(TreatmentPlanService::class)->addItem($plan->id, [
            'procedure_name' => 'Limpieza dental',
            'quantity' => 1,
            'unit_cost' => 200.0,
        ]);

        $quotation = app(QuotationService::class)->generateQuotation($plan->fresh()->id);
        $quotation->update(['status' => 'sent']);
        $this->postJson('/api/quotations/'.$quotation->id.'/approve')->assertOk();

        $this->assertSame('approved', $quotation->fresh()->status);

        // The tariff and the plan move after the patient accepted the estimate.
        $plan->refresh();
        $plan->items()->update(['unit_cost' => 500.0, 'total_cost' => 500.0]);
        $plan->update(['total_cost' => 500.0, 'final_cost' => 500.0]);

        $fresh = $quotation->fresh();

        $this->assertSame('200.00', $fresh->total_amount);
        $this->assertSame('169.49', $fresh->subtotal);
        $this->assertSame('30.51', $fresh->tax_amount);
        $this->assertSame('200.00', $fresh->items()->firstOrFail()->unit_price);
    }

    public function test_an_approved_quotation_cannot_be_edited(): void
    {
        $quotation = $this->quotation('approved');

        $response = $this->putJson('/api/quotations/'.$quotation->id, ['subtotal' => 999])
            ->assertStatus(422);

        $this->assertStringContainsString(
            'Solo se puede modificar un presupuesto en borrador, enviado o visto. Estado actual: approved.',
            $response->json('errors.status.0')
        );
        $this->assertSame('100.00', $quotation->fresh()->total_amount);
    }

    public function test_a_rejected_quotation_cannot_be_edited(): void
    {
        $quotation = $this->quotation('rejected');

        $this->putJson('/api/quotations/'.$quotation->id, ['notes' => 'cambio tardío'])
            ->assertStatus(422);

        $this->assertNull($quotation->fresh()->notes);
    }

    public function test_an_expired_quotation_cannot_be_edited(): void
    {
        $quotation = $this->quotation('expired');

        $this->putJson('/api/quotations/'.$quotation->id, ['subtotal' => 999])
            ->assertStatus(422);

        $this->assertSame('100.00', $quotation->fresh()->total_amount);
    }

    public function test_a_rejected_edit_does_not_rewrite_the_items(): void
    {
        $quotation = $this->quotation('approved');
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'item_name' => 'Limpieza dental',
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        $this->putJson('/api/quotations/'.$quotation->id, [
            'items' => [['description' => 'Otro', 'quantity' => 1, 'unit_price' => 9999]],
        ])->assertStatus(422);

        $this->assertSame(1, $quotation->fresh()->items()->count());
        $this->assertSame('Limpieza dental', $quotation->fresh()->items()->firstOrFail()->item_name);
    }

    public function test_a_sent_quotation_can_still_be_edited(): void
    {
        $quotation = $this->quotation('sent');

        $this->putJson('/api/quotations/'.$quotation->id, ['notes' => 'Ajuste antes de la firma'])
            ->assertOk();

        $this->assertSame('Ajuste antes de la firma', $quotation->fresh()->notes);
    }

    public function test_a_draft_quotation_can_still_be_edited(): void
    {
        $quotation = $this->quotation('draft');

        $this->putJson('/api/quotations/'.$quotation->id, ['discount_percentage' => 10])
            ->assertOk();

        $fresh = $quotation->fresh();

        $this->assertSame('10.00', $fresh->discount_amount);
        $this->assertSame('90.00', $fresh->total_amount);
    }

    public function test_an_approved_quotation_keeps_its_amount_when_the_rate_is_edited_elsewhere(): void
    {
        $quotation = $this->quotation('approved', [
            'subtotal' => 100,
            'tax_percentage' => 18,
            'tax_amount' => 15.25,
            'total_amount' => 100,
        ]);

        $this->putJson('/api/quotations/'.$quotation->id, ['tax_percentage' => 0])
            ->assertStatus(422);

        $this->assertSame('15.25', $quotation->fresh()->tax_amount);
    }
}
