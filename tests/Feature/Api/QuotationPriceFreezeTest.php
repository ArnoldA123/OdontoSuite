<?php

namespace Tests\Feature\Api;

use App\Http\Resources\QuotationResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\ProcedureCatalog;
use App\Models\ProcedurePrice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\BillingService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #293 S4/S6 — the estimate freezes the tariff in force when it is issued.
 *
 * The emission is the commercial act: the price is resolved on
 * `quotation_date`, not on the day the plan was written and not on the day the
 * quotation row happened to be saved. The line keeps the resolved price, the
 * source that produced it and the applied window, and from that moment nobody
 * recalculates: moving the tariff, the convenio or the list does not move an
 * issued estimate nor a closed act, because the closure copies the executed
 * line instead of resolving again.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class QuotationPriceFreezeTest extends TestCase
{
    use RefreshDatabase;

    private function professional(?Branch $branch, string $role = 'odontologo'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function payer(string $code = 'rimac'): Payer
    {
        return Payer::create([
            'code' => $code,
            'name' => 'Convenio '.strtoupper($code),
            'is_active' => true,
        ]);
    }

    private function procedure(float $cost = 100.0): ProcedureCatalog
    {
        return ProcedureCatalog::factory()->create([
            'code' => 'PROC-'.uniqid(),
            'name' => 'Limpieza '.uniqid(),
            'default_cost' => $cost,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $dimensions
     */
    private function priceRow(
        ProcedureCatalog $procedure,
        float $price,
        array $dimensions = [],
        ?string $from = null,
        ?string $to = null,
    ): ProcedurePrice {
        return ProcedurePrice::create(array_merge([
            'procedure_catalog_id' => $procedure->id,
            'price' => $price,
            'valid_from' => $from,
            'valid_to' => $to,
        ], $dimensions));
    }

    /**
     * A manual line of the estimate. `unit_price` is deliberately a value the
     * server must not trust for a catalogued procedure.
     *
     * @return array<string, mixed>
     */
    private function line(ProcedureCatalog $procedure, float $quantity = 1.0, float $clientPrice = 1.0): array
    {
        return [
            'description' => $procedure->name,
            'procedure_catalog_id' => $procedure->id,
            'quantity' => $quantity,
            'unit_price' => $clientPrice,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function manual(User $actor, array $data = []): Quotation
    {
        $this->actingAs($actor, 'sanctum');

        return app(QuotationService::class)->createQuotation(array_merge([
            'patient_id' => Patient::factory()->create()->id,
        ], $data));
    }

    public function test_the_estimate_uses_the_tariff_window_in_force_on_its_issue_date(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->professional($branch);
        $procedure = $this->procedure(100.0);

        $january = $this->priceRow($procedure, 80.0, ['branch_id' => $branch->id], '2026-01-01', '2026-01-31');
        $february = $this->priceRow($procedure, 90.0, ['branch_id' => $branch->id], '2026-02-01', null);

        $issuedInJanuary = $this->manual($actor, [
            'subtotal' => 0,
            'quotation_date' => '2026-01-15',
            'items' => [$this->line($procedure)],
        ]);

        $line = $issuedInJanuary->items()->firstOrFail();

        $this->assertSame(80.0, (float) $line->unit_price);
        $this->assertSame(80.0, (float) $line->total_price);
        $this->assertSame('branch', $line->price_source);
        $this->assertSame($january->id, $line->price_row_id);
        $this->assertSame('2026-01-01', $line->price_valid_from?->toDateString());
        $this->assertSame('2026-01-31', $line->price_valid_to?->toDateString());
        $this->assertSame('80.00', $issuedInJanuary->fresh()->total_amount);

        $issuedInFebruary = $this->manual($actor, [
            'subtotal' => 0,
            'quotation_date' => '2026-02-15',
            'items' => [$this->line($procedure)],
        ]);

        $next = $issuedInFebruary->items()->firstOrFail();

        $this->assertSame(90.0, (float) $next->unit_price);
        $this->assertSame($february->id, $next->price_row_id);
        $this->assertSame('2026-02-01', $next->price_valid_from?->toDateString());
        $this->assertNull($next->price_valid_to);
        $this->assertSame('90.00', $issuedInFebruary->fresh()->total_amount);
    }

    public function test_the_convenio_tariff_in_force_wins_over_the_sede_tariff(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();
        $actor = $this->professional($branch);
        $procedure = $this->procedure(100.0);

        $this->priceRow($procedure, 80.0, ['branch_id' => $branch->id]);
        $agreed = $this->priceRow(
            $procedure,
            60.0,
            ['branch_id' => $branch->id, 'payer_id' => $payer->id],
            '2026-01-01',
            null,
        );

        $quotation = $this->manual($actor, [
            'subtotal' => 0,
            'payer_id' => $payer->id,
            'items' => [$this->line($procedure)],
        ]);

        $line = $quotation->items()->firstOrFail();

        $this->assertSame($payer->id, $quotation->fresh()->payer_id);
        $this->assertSame(60.0, (float) $line->unit_price);
        $this->assertSame('payer_branch', $line->price_source);
        $this->assertSame($agreed->id, $line->price_row_id);
        $this->assertSame('2026-01-01', $line->price_valid_from?->toDateString());

        // The resource publishes the convenio and the evidence of each line.
        $payload = QuotationResource::make($quotation->fresh()->load('items'))->resolve();

        $this->assertSame($payer->id, $payload['payer_id']);
        $this->assertSame($agreed->id, $payload['items'][0]['price_row_id']);
        $this->assertSame('payer_branch', $payload['items'][0]['price_source']);
        $this->assertSame('2026-01-01', $payload['items'][0]['price_valid_from']);
    }

    public function test_moving_the_tariff_after_emission_does_not_move_the_estimate(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->professional($branch);
        $procedure = $this->procedure(100.0);
        $applied = $this->priceRow($procedure, 80.0, ['branch_id' => $branch->id]);

        $quotation = $this->manual($actor, [
            'subtotal' => 0,
            'items' => [$this->line($procedure, 2)],
        ]);

        $issued = $quotation->items()->firstOrFail();
        $this->assertSame(80.0, (float) $issued->unit_price);
        $this->assertSame(160.0, (float) $issued->total_price);
        $this->assertSame('160.00', $quotation->fresh()->total_amount);

        // The tariff is rewritten and a newer window is opened afterwards.
        $applied->update(['price' => 95.0]);
        $this->priceRow($procedure, 200.0, ['branch_id' => $branch->id], now()->toDateString());

        $still = $quotation->fresh()->items()->firstOrFail();

        $this->assertSame(80.0, (float) $still->unit_price);
        $this->assertSame(160.0, (float) $still->total_price);
        $this->assertSame($applied->id, $still->price_row_id);
        $this->assertSame('160.00', $quotation->fresh()->total_amount);
    }

    public function test_a_closed_act_keeps_its_amount_after_the_tariff_moves(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->professional($branch);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $procedure = $this->procedure(100.0);
        $tariff = $this->priceRow($procedure, 100.0, ['branch_id' => $branch->id]);

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'created_by' => $actor->id,
        ]);

        // The act was executed at the amount the professional documented.
        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_catalog_id' => $procedure->id,
            'procedure_name' => $procedure->name,
            'quantity' => 1,
            'unit_cost' => 150.0,
            'total_cost' => 150.0,
            'status' => 'completed',
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $actor->id,
            'treatment_plan_id' => $plan->id,
            'status' => 'completed',
            'completed_at' => now()->subDay(),
            'final_amount' => 150.0,
        ]);

        // The tariff moves while the act is already documented.
        $tariff->update(['price' => 300.0]);

        $this->actingAs($actor, 'sanctum');

        $quotation = app(BillingService::class)
            ->generateQuotationFromAppointment($appointment->fresh());

        $line = $quotation->items()->firstOrFail();

        $this->assertSame(150.0, (float) $line->unit_price);
        $this->assertSame(150.0, (float) $line->total_price);
        $this->assertSame('150.00', $quotation->fresh()->total_amount);

        // The closure copies the executed line; it never resolves a tariff, so
        // it records no evidence it did not produce.
        $this->assertNull($line->price_source);
        $this->assertNull($line->price_row_id);
    }

    public function test_a_manual_estimate_ignores_the_price_the_client_sends(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->professional($branch);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $procedure = $this->procedure(100.0);
        $this->priceRow($procedure, 80.0, ['branch_id' => $branch->id]);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/quotations', [
                'patient_id' => $patient->id,
                // The client declares S/ 1.00 for a catalogued procedure.
                'subtotal' => 1,
                'items' => [$this->line($procedure, 1, 1)],
            ])
            ->assertCreated();

        $line = QuotationItem::firstOrFail();

        $this->assertSame(80.0, (float) $line->unit_price);
        $this->assertSame(80.0, (float) $line->total_price);
        $this->assertSame('branch', $line->price_source);
        $this->assertSame('80.00', Quotation::firstOrFail()->total_amount);
    }

    public function test_a_free_text_line_keeps_its_price_and_records_no_tariff_evidence(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->professional($branch);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/quotations', [
                'patient_id' => $patient->id,
                'subtotal' => 90,
                'items' => [[
                    'description' => 'Ajuste oclusal',
                    'quantity' => 2,
                    'unit_price' => 45,
                ]],
            ])
            ->assertCreated();

        $line = QuotationItem::firstOrFail();

        $this->assertNull($line->procedure_catalog_id);
        $this->assertSame(45.0, (float) $line->unit_price);
        $this->assertSame(90.0, (float) $line->total_price);
        $this->assertNull($line->price_source);
        $this->assertNull($line->price_row_id);
        $this->assertNull($line->price_valid_from);
        $this->assertNull($line->price_valid_to);
        $this->assertSame('90.00', Quotation::firstOrFail()->total_amount);
    }

    public function test_an_edit_freezes_at_the_estimate_own_issue_date(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->professional($branch);
        $procedure = $this->procedure(100.0);

        $january = $this->priceRow($procedure, 90.0, ['branch_id' => $branch->id], '2026-01-01', '2026-01-31');
        $this->priceRow($procedure, 70.0, ['branch_id' => $branch->id], '2026-02-01', null);

        $quotation = $this->manual($actor, [
            'subtotal' => 0,
            'quotation_date' => '2026-01-10',
            'discount_amount' => 10,
            'items' => [$this->line($procedure)],
        ]);

        $this->assertSame(90.0, (float) $quotation->items()->firstOrFail()->unit_price);
        // 90 gross, 10 discount: the patient owes 80.
        $this->assertSame('80.00', $quotation->fresh()->total_amount);

        // The payload sends no `subtotal` and a price the server must ignore.
        $updated = app(QuotationService::class)->updateQuotation($quotation->id, [
            'items' => [$this->line($procedure, 2, 1)],
        ]);

        $line = $updated->items()->firstOrFail();

        // The stored issue date is January, so January's window still applies
        // even though today's window is the February one.
        $this->assertSame(90.0, (float) $line->unit_price);
        $this->assertSame($january->id, $line->price_row_id);
        // 2 × 90 gross, the stored 10 discount applies once: 170.
        $this->assertSame('170.00', $updated->fresh()->total_amount);
        $this->assertSame('10.00', $updated->fresh()->discount_amount);
    }
}
