<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #289, S1 (finding D2) — the document the patient takes away has to add up.
 *
 * `quotations.subtotal` is the base imponible since the price includes the IGV,
 * so the printed block shows the list price as the starting line, the discount
 * as a subtraction, and the base plus IGV as the breakdown of the total. Before
 * this, the PDF printed `base - discount + IGV`, which never matched the total of
 * a discounted quotation.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class QuotationDocumentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function quotation(array $overrides = []): Quotation
    {
        $actor = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        return Quotation::create(array_merge([
            'treatment_plan_id' => null,
            'patient_id' => Patient::factory()->create()->id,
            'created_by' => $actor->id,
            'quotation_number' => 'PR-DOC-1',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 152.54,
            'discount_percentage' => 10,
            'discount_amount' => 20,
            'tax_percentage' => 18,
            'tax_amount' => 27.46,
            'total_amount' => 180,
            'status' => 'sent',
        ], $overrides));
    }

    private function render(Quotation $quotation): string
    {
        $quotation->load(['patient', 'items', 'createdBy']);

        return view('reports.quotation', ['quotation' => $quotation])->render();
    }

    public function test_the_pdf_starts_from_the_list_price_and_adds_up_to_the_total(): void
    {
        $html = $this->render($this->quotation());

        // 200 list, -20 discount, 152.54 base + 27.46 IGV = 180 total.
        $this->assertStringContainsString('S/ 200.00', $html);
        $this->assertStringContainsString('- S/ 20.00', $html);
        $this->assertStringContainsString('Base imponible:', $html);
        $this->assertStringContainsString('S/ 152.54', $html);
        $this->assertStringContainsString('IGV (18.00%):', $html);
        $this->assertStringContainsString('S/ 27.46', $html);
        $this->assertStringContainsString('S/ 180.00', $html);
    }

    public function test_the_discount_and_the_tax_breakdown_do_not_double_count(): void
    {
        preg_match_all('/S\/ ([\d,]+\.\d{2})/', $this->render($this->quotation()), $matches);

        $amounts = array_map(
            fn ($value) => (float) str_replace(',', '', $value),
            $matches[1]
        );

        $list = 200.0;
        $discount = 20.0;
        $base = 152.54;
        $tax = 27.46;
        $total = 180.0;

        $this->assertContains($list, $amounts);
        $this->assertContains($discount, $amounts);
        $this->assertContains($base, $amounts);
        $this->assertContains($tax, $amounts);
        $this->assertContains($total, $amounts);
        // list - discount == base + tax == total
        $this->assertEqualsWithDelta($total, $list - $discount, 0.001);
        $this->assertEqualsWithDelta($total, $base + $tax, 0.001);
    }

    public function test_a_tax_free_quotation_prints_no_base_row(): void
    {
        $html = $this->render($this->quotation([
            'subtotal' => 180,
            'discount_percentage' => 10,
            'discount_amount' => 20,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => 180,
        ]));

        $this->assertStringContainsString('S/ 200.00', $html);
        $this->assertStringContainsString('- S/ 20.00', $html);
        $this->assertStringNotContainsString('Base imponible:', $html);
        $this->assertStringNotContainsString('IGV (', $html);
        $this->assertStringContainsString('S/ 180.00', $html);
    }

    public function test_a_quotation_without_discount_prints_the_same_total_twice(): void
    {
        $html = $this->render($this->quotation([
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'subtotal' => 100,
            'tax_amount' => 18,
            'total_amount' => 118,
        ]));

        $this->assertStringNotContainsString('Descuento', $html);
        // List price == base + IGV == total: 118 appears as subtotal and total.
        $this->assertSame(2, substr_count($html, 'S/ 118.00'));
        $this->assertStringContainsString('Base imponible:', $html);
        $this->assertStringContainsString('S/ 100.00', $html);
    }
}
