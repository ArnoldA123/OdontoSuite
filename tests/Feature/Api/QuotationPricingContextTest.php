<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Payer;
use App\Models\ProcedureCatalog;
use App\Models\ProcedurePrice;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #414, S1 — the manual form reads the same arithmetic the server applies.
 *
 * The preview cannot invent the tax rate nor guess the line price: a catalog
 * line is priced by `ProcedurePriceResolver` on the estimate's issue date, and
 * an untouched rate falls back to the billing policy. This read-only context
 * publishes both, and the writer resolves them with the same private rules, so
 * the total the form paints is the total the document persists.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class QuotationPricingContextTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'recepcionista'): User
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

    private function procedure(float $cost = 120.0): ProcedureCatalog
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
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function pricing(array $params): array
    {
        return $this->getJson('/api/quotations/pricing?'.http_build_query($params))
            ->assertOk()
            ->json('data');
    }

    public function test_the_convenio_and_sede_tariff_on_the_issue_date_outranks_the_catalog_price(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();
        $procedure = $this->procedure(120.0);

        // The catalog says 120; the convenio agreed with the sede says 140.
        $agreed = $this->priceRow(
            $procedure,
            140.0,
            ['branch_id' => $branch->id, 'payer_id' => $payer->id],
            '2026-01-01',
            null,
        );

        $this->actingAs($this->user($branch), 'sanctum');

        $context = $this->pricing([
            'procedure_ids' => [$procedure->id],
            'payer_id' => $payer->id,
            'quotation_date' => '2026-06-15',
        ]);

        $this->assertSame('2026-06-15', $context['quotation_date']);

        $resolved = $context['prices'][(string) $procedure->id];

        $this->assertSame(140.0, (float) $resolved['unit_price']);
        $this->assertSame('payer_branch', $resolved['source']);
        $this->assertSame($agreed->id, $resolved['price_id']);
        $this->assertSame('2026-01-01', $resolved['valid_from']);
        $this->assertNull($resolved['valid_to']);
    }

    public function test_the_context_publishes_the_configured_default_tax_rate(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure();

        $this->actingAs($this->user($branch), 'sanctum');

        config(['billing.tax.default_percentage' => 18.0]);

        $this->assertSame(18.0, (float) $this->pricing(['procedure_ids' => [$procedure->id]])['tax_percentage']);

        config(['billing.tax.default_percentage' => 10.0]);

        $this->assertSame(10.0, (float) $this->pricing(['procedure_ids' => [$procedure->id]])['tax_percentage']);
    }

    public function test_a_procedure_that_does_not_exist_is_not_priced(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(90.0);

        $this->actingAs($this->user($branch), 'sanctum');

        $context = $this->pricing([
            'procedure_ids' => [$procedure->id, 999999],
            'quotation_date' => '2026-06-15',
        ]);

        $this->assertSame([(string) $procedure->id], array_map('strval', array_keys($context['prices'])));
        $this->assertSame(90.0, (float) $context['prices'][(string) $procedure->id]['unit_price']);
        $this->assertSame('catalog', $context['prices'][(string) $procedure->id]['source']);
    }

    public function test_the_context_is_guarded_by_the_quotation_reader_permission(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure();

        $this->actingAs($this->user($branch, 'tecnico_dental'), 'sanctum');

        $this->getJson('/api/quotations/pricing?procedure_ids[]='.$procedure->id)
            ->assertForbidden();

        $this->actingAs($this->user($branch, 'recepcionista'), 'sanctum');

        $this->getJson('/api/quotations/pricing?procedure_ids[]='.$procedure->id)
            ->assertOk();

        // Reading the context prices nothing and issues no document.
        $this->assertSame(0, Quotation::count());
    }

    public function test_an_unknown_payer_is_rejected_with_the_house_envelope(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure();

        $this->actingAs($this->user($branch), 'sanctum');

        $this->getJson('/api/quotations/pricing?procedure_ids[]='.$procedure->id.'&payer_id=999999')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Error de validación')
            ->assertJsonStructure(['message', 'errors' => ['payer_id']]);
    }

    public function test_a_free_text_estimate_still_reads_the_billing_policy_rate(): void
    {
        config(['billing.tax.default_percentage' => 10.0]);

        $branch = Branch::factory()->create();
        $this->actingAs($this->user($branch), 'sanctum');

        // #414: no catalog line means no tariff, never no policy. The form
        // paints the rate the writer will apply instead of inventing 0 %.
        $this->getJson('/api/quotations/pricing')
            ->assertOk()
            ->assertJsonPath('data.tax_percentage', 10)
            ->assertJsonPath('data.prices', []);
    }
}
