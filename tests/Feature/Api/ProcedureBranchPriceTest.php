<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\ProcedureCatalog;
use App\Models\ProcedurePrice;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\ProcedurePriceResolver;
use App\Services\QuotationService;
use App\Services\TreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * #290 S8 (decision L6) — the branch tariff.
 *
 * The group list price is `procedure_catalog.default_cost`; a row for the sede,
 * inside its validity window, wins. The picker answers the price of the caller's
 * sede and keeps `list_cost` visible, and every document written from it keeps
 * the applied price as a snapshot.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedureBranchPriceTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'odontologo'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
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
     * @return array<string, mixed>
     */
    private function priceRow(ProcedureCatalog $procedure, Branch $branch, float $price, ?string $from = null, ?string $to = null): ProcedurePrice
    {
        return ProcedurePrice::create([
            'procedure_catalog_id' => $procedure->id,
            'branch_id' => $branch->id,
            'payer_id' => null,
            'price' => $price,
            'valid_from' => $from,
            'valid_to' => $to,
        ]);
    }

    public function test_a_branch_price_wins_over_the_list_price(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);
        $this->priceRow($procedure, $branch, 80.0, now()->subWeek()->toDateString(), now()->addWeek()->toDateString());

        $response = $this->actingAs($this->user($branch), 'sanctum')
            ->getJson('/api/procedure-catalog/search?q='.urlencode($procedure->name));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $procedure->id);

        $this->assertNotNull($row);
        $this->assertSame(80.0, (float) $row['default_cost']);
        $this->assertSame(100.0, (float) $row['list_cost']);
        $this->assertSame('branch', $row['price_source']);
    }

    public function test_a_price_outside_its_window_is_ignored(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);
        $this->priceRow(
            $procedure,
            $branch,
            60.0,
            now()->subMonths(3)->toDateString(),
            now()->subMonths(2)->toDateString(),
        );

        $response = $this->actingAs($this->user($branch), 'sanctum')
            ->getJson('/api/procedure-catalog/search?q='.urlencode($procedure->name));

        $row = collect($response->json('data'))->firstWhere('id', $procedure->id);

        $this->assertSame(100.0, (float) $row['default_cost']);
        $this->assertSame('catalog', $row['price_source']);
    }

    public function test_the_list_price_applies_without_a_branch_row_or_a_sede(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);

        $withoutRow = $this->actingAs($this->user($branch), 'sanctum')
            ->getJson('/api/procedure-catalog/search?q='.urlencode($procedure->name));
        $withoutSede = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->getJson('/api/procedure-catalog/search?q='.urlencode($procedure->name));

        foreach ([$withoutRow, $withoutSede] as $response) {
            $response->assertOk();
            $row = collect($response->json('data'))->firstWhere('id', $procedure->id);
            $this->assertSame(100.0, (float) $row['default_cost']);
            $this->assertSame('catalog', $row['price_source']);
        }
    }

    public function test_the_most_recent_window_in_force_wins(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);
        $this->priceRow($procedure, $branch, 90.0, now()->subMonth()->toDateString());
        $this->priceRow($procedure, $branch, 70.0, now()->subDay()->toDateString());

        $resolved = app(ProcedurePriceResolver::class)->resolve($procedure, $branch->id);

        $this->assertSame(70.0, $resolved['price']);
        $this->assertSame('branch', $resolved['source']);
    }

    public function test_an_administrator_stores_and_lists_the_branch_tariff(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);
        $actor = $this->user(null, 'administrador');

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/branches/{$branch->id}/procedure-prices", [
                'items' => [[
                    'procedure_catalog_id' => $procedure->id,
                    'price' => 75.5,
                    'valid_from' => Carbon::now()->toDateString(),
                    'valid_to' => Carbon::now()->addMonth()->toDateString(),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('procedure_prices', [
            'procedure_catalog_id' => $procedure->id,
            'branch_id' => $branch->id,
            'payer_id' => null,
            'price' => 75.5,
        ]);

        $list = $this->actingAs($actor, 'sanctum')
            ->getJson("/api/branches/{$branch->id}/procedure-prices")
            ->assertOk()
            ->json('data.0');

        $this->assertSame($procedure->id, $list['procedure_id']);
        $this->assertEqualsWithDelta(75.5, (float) $list['price'], 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $list['list_price'], 0.001);
    }

    public function test_a_sede_administrator_only_reaches_their_own_tariff(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $procedure = $this->procedure(100.0);
        $actor = $this->user($own, 'administrador');

        $payload = ['items' => [['procedure_catalog_id' => $procedure->id, 'price' => 50.0]]];

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/branches/{$other->id}/procedure-prices", $payload)
            ->assertForbidden();

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/branches/{$own->id}/procedure-prices", $payload)
            ->assertOk();
    }

    public function test_a_non_administrator_cannot_manage_the_tariff(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);

        $this->actingAs($this->user($branch, 'odontologo'), 'sanctum')
            ->putJson("/api/branches/{$branch->id}/procedure-prices", [
                'items' => [['procedure_catalog_id' => $procedure->id, 'price' => 10.0]],
            ])
            ->assertForbidden();
    }

    public function test_the_branch_tariff_can_be_filtered_by_procedure(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);
        $other = $this->procedure(200.0);

        $this->priceRow($procedure, $branch, 80.0, now()->subWeek()->toDateString());
        $this->priceRow($other, $branch, 150.0, now()->subWeek()->toDateString());

        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->getJson("/api/branches/{$branch->id}/procedure-prices?procedure_id={$procedure->id}")
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.procedure_id', $procedure->id);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_a_quotation_keeps_the_applied_price_as_a_snapshot(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);
        $priceRow = $this->priceRow($procedure, $branch, 80.0, now()->subDay()->toDateString());
        $actor = $this->user($branch, 'odontologo');

        $applied = app(ProcedurePriceResolver::class)->resolve($procedure, $branch->id);
        $this->assertSame(80.0, $applied['price']);

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => \App\Models\Patient::factory()->create()->id,
            'branch_id' => $branch->id,
            'created_by' => $actor->id,
        ]);

        app(TreatmentPlanService::class)->addItem($plan->id, [
            'procedure_name' => $procedure->name,
            'procedure_catalog_id' => $procedure->id,
            'quantity' => 1,
            'unit_cost' => $applied['price'],
        ]);

        // #293 S4 superseded the earlier expectation: the estimate freezes the
        // tariff in force on its issue date, not the price the plan copied.
        $priceRow->update(['price' => 95.0]);

        $this->actingAs($actor, 'sanctum');
        $quotation = app(QuotationService::class)->generateQuotation($plan->id);
        $item = $quotation->items()->firstOrFail();

        $this->assertSame(95.0, (float) $item->unit_price);
        $this->assertSame($priceRow->id, $item->price_row_id);
        $this->assertSame('95.00', $quotation->fresh()->total_amount);

        // The issued estimate does not follow a later tariff change.
        $priceRow->update(['price' => 120.0]);

        $still = $quotation->fresh()->items()->firstOrFail();

        $this->assertSame(95.0, (float) $still->unit_price);
        $this->assertSame($priceRow->id, $still->price_row_id);
        $this->assertSame('95.00', $quotation->fresh()->total_amount);
    }
}
