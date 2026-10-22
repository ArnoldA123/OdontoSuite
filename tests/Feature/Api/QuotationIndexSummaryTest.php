<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contract for the quotations redesign backend surface:
 *  - `q` text search over number, patient name and document (the list filter);
 *  - `GET /api/quotations/summary` aggregates that feed the KPI strip;
 *  - manual quotations (no treatment plan) are persisted;
 *  - approving persists the approval record on the real columns.
 */
class QuotationIndexSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function quotation(array $overrides = []): Quotation
    {
        $subtotal = $overrides['subtotal'] ?? 100;
        $user = $this->admin();
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
        ]);

        return Quotation::create(array_merge([
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'quotation_number' => 'PR-'.strtoupper(Str::random(8)),
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => $subtotal,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => $subtotal,
            'status' => 'draft',
        ], $overrides));
    }

    public function test_index_search_matches_the_quotation_number(): void
    {
        $user = $this->admin();
        $wanted = $this->quotation(['quotation_number' => 'PR-ALPHA-001']);
        $this->quotation(['quotation_number' => 'PR-BETA-002']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/quotations?q=ALPHA');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $wanted->id);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_index_search_matches_patient_name_and_document_number(): void
    {
        $user = $this->admin();
        $patient = Patient::factory()->create([
            'first_name' => 'Rosa',
            'last_name' => 'Quispe',
            'document_number' => '44556677',
        ]);
        $wanted = $this->quotation(['patient_id' => $patient->id]);
        $this->quotation();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/quotations?q=quispe')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/quotations?q=44556677')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_index_without_search_still_returns_every_quotation(): void
    {
        $user = $this->admin();
        $this->quotation();
        $this->quotation();
        $this->quotation();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/quotations')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_summary_reports_status_counts_and_approved_amount(): void
    {
        $user = $this->admin();
        $this->quotation(['status' => 'draft', 'subtotal' => 100, 'total_amount' => 100]);
        $this->quotation(['status' => 'sent', 'subtotal' => 200, 'total_amount' => 200]);
        $this->quotation(['status' => 'viewed', 'subtotal' => 20, 'total_amount' => 20]);
        $this->quotation(['status' => 'approved', 'subtotal' => 150.50, 'total_amount' => 150.50]);
        $this->quotation(['status' => 'rejected', 'subtotal' => 50, 'total_amount' => 50]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/quotations/summary');

        $response->assertOk();
        $response->assertJsonPath('data.total', 5);
        $response->assertJsonPath('data.by_status.draft.count', 1);
        $response->assertJsonPath('data.by_status.sent.count', 1);
        $response->assertJsonPath('data.by_status.viewed.count', 1);
        $response->assertJsonPath('data.by_status.approved.count', 1);
        $response->assertJsonPath('data.by_status.rejected.count', 1);
        $response->assertJsonPath('data.by_status.expired.count', 0);
        $response->assertJsonPath('data.pending', 2);
        $response->assertJsonPath('data.approved_total', 150.5);
    }

    public function test_summary_reports_expiring_and_overdue_pending_quotations(): void
    {
        $user = $this->admin();
        $this->quotation(['status' => 'sent', 'valid_until' => now()->addDays(3)->toDateString()]);
        $this->quotation(['status' => 'viewed', 'valid_until' => now()->subDay()->toDateString()]);
        $this->quotation(['status' => 'approved', 'valid_until' => now()->subDays(2)->toDateString()]);
        $this->quotation(['status' => 'draft', 'valid_until' => now()->addDay()->toDateString()]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/quotations/summary');

        $response->assertOk();
        $response->assertJsonPath('data.expiring_soon', 1);
        $response->assertJsonPath('data.overdue', 1);
    }

    public function test_manual_quotation_without_treatment_plan_is_created(): void
    {
        $user = $this->admin();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/quotations', [
            'patient_id' => $patient->id,
            'subtotal' => 250,
            'items' => [
                ['description' => 'Limpieza dental', 'quantity' => 2, 'unit_price' => 100],
            ],
        ]);

        $response->assertCreated();

        $quotation = Quotation::firstOrFail();
        $this->assertNull($quotation->treatment_plan_id);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame($patient->id, $quotation->patient_id);
        // #293 S4: with lines, the document's gross is the sum of the frozen
        // line totals (2 × 100), not the subtotal the client declared.
        $this->assertSame('200.00', $quotation->total_amount);

        $item = QuotationItem::firstOrFail();
        $this->assertSame('Limpieza dental', $item->item_name);
        $this->assertSame('200.00', $item->total_price);
        // A free-text line keeps its price and carries no tariff evidence.
        $this->assertNull($item->price_source);
    }

    public function test_approving_a_quotation_persists_the_approval_record(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'sent', 'subtotal' => 80, 'total_amount' => 80]);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/quotations/{$quotation->id}/approve",
            ['notes' => 'Aprobado por el paciente']
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('quotation_approvals', [
            'quotation_id' => $quotation->id,
            'patient_id' => $quotation->patient_id,
            'approval_status' => 'approved',
            'patient_notes' => 'Aprobado por el paciente',
        ]);
        $this->assertNotNull(
            $quotation->fresh()->approvals()->first()?->approval_date,
            'The approval date must be persisted.'
        );
    }
}
