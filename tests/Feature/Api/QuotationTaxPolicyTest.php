<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\BillingService;
use App\Services\QuotationService;
use App\Services\TreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #289, S1 — the IGV policy.
 *
 * The catalog price is the price the patient pays, so the tax is broken out of
 * the amount instead of being added on top of it. Declaring a rate therefore
 * never changes the total; it only splits it into base imponible plus IGV.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class QuotationTaxPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        $this->actingAs($this->actor, 'sanctum');
    }

    private function planWithPrice(float $price): TreatmentPlan
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'created_by' => $this->actor->id,
        ]);

        app(TreatmentPlanService::class)->addItem($plan->id, [
            'procedure_name' => 'Limpieza dental',
            'quantity' => 1,
            'unit_cost' => $price,
        ]);

        return $plan->fresh();
    }

    public function test_the_default_igv_is_broken_out_of_the_catalog_price(): void
    {
        $plan = $this->planWithPrice(118.0);

        $quotation = app(QuotationService::class)->generateQuotation($plan->id);

        $this->assertSame('18.00', $quotation->tax_percentage);
        $this->assertSame('18.00', $quotation->tax_amount);
        $this->assertSame('100.00', $quotation->subtotal);
        $this->assertSame('118.00', $quotation->total_amount);
    }

    public function test_declaring_the_tax_never_changes_the_price_the_patient_pays(): void
    {
        $withDefaultRate = app(QuotationService::class)->generateQuotation($this->planWithPrice(118.0)->id);
        $withoutTax = app(QuotationService::class)->generateQuotation(
            $this->planWithPrice(118.0)->id,
            ['tax_percentage' => 0]
        );

        $this->assertSame(118.0, (float) $withDefaultRate->total_amount);
        $this->assertSame(118.0, (float) $withoutTax->total_amount);

        $this->assertSame('0.00', $withoutTax->tax_amount);
        $this->assertSame('118.00', $withoutTax->subtotal);
    }

    public function test_the_breakdown_applies_after_the_discount(): void
    {
        $quotation = app(QuotationService::class)->createQuotation([
            'patient_id' => Patient::factory()->create()->id,
            'subtotal' => 118.0,
            'discount_percentage' => 10,
        ]);

        $this->assertSame('11.80', $quotation->discount_amount);
        $this->assertSame('16.20', $quotation->tax_amount);
        $this->assertSame('90.00', $quotation->subtotal);
        $this->assertSame('106.20', $quotation->total_amount);
    }

    public function test_changing_the_discount_recomputes_from_the_stored_price_and_rate(): void
    {
        $quotation = app(QuotationService::class)->generateQuotation($this->planWithPrice(118.0)->id);

        $this->putJson('/api/quotations/'.$quotation->id, ['discount_amount' => 11.80])
            ->assertOk();

        $fresh = $quotation->fresh();

        $this->assertSame('18.00', $fresh->tax_percentage);
        $this->assertSame('16.20', $fresh->tax_amount);
        $this->assertSame('90.00', $fresh->subtotal);
        $this->assertSame('106.20', $fresh->total_amount);
    }

    public function test_a_partial_update_of_a_discounted_quotation_does_not_discount_twice(): void
    {
        $quotation = app(QuotationService::class)->createQuotation([
            'patient_id' => Patient::factory()->create()->id,
            'subtotal' => 200.0,
            'discount_amount' => 20.0,
        ]);

        $this->assertSame('180.00', $quotation->total_amount);

        // Only the rate travels: the gross has to be rebuilt from the stored net
        // plus the discount it already absorbed, not from the net alone.
        $this->putJson('/api/quotations/'.$quotation->id, ['tax_percentage' => 0])
            ->assertOk();

        $fresh = $quotation->fresh();

        $this->assertSame('20.00', $fresh->discount_amount);
        $this->assertSame('180.00', $fresh->subtotal);
        $this->assertSame('180.00', $fresh->total_amount);
    }

    public function test_a_partial_update_of_the_discount_uses_the_list_price(): void
    {
        $quotation = app(QuotationService::class)->createQuotation([
            'patient_id' => Patient::factory()->create()->id,
            'subtotal' => 200.0,
            'discount_amount' => 20.0,
        ]);

        $this->putJson('/api/quotations/'.$quotation->id, ['discount_amount' => 30.0])
            ->assertOk();

        $fresh = $quotation->fresh();

        $this->assertSame('25.93', $fresh->tax_amount);
        $this->assertSame('144.07', $fresh->subtotal);
        $this->assertSame('170.00', $fresh->total_amount);
    }

    public function test_declaring_no_tax_on_an_existing_quotation_keeps_the_price(): void
    {
        $quotation = app(QuotationService::class)->generateQuotation($this->planWithPrice(118.0)->id);

        $this->putJson('/api/quotations/'.$quotation->id, ['tax_percentage' => 0])
            ->assertOk();

        $fresh = $quotation->fresh();

        $this->assertSame('0.00', $fresh->tax_amount);
        $this->assertSame('118.00', $fresh->subtotal);
        $this->assertSame('118.00', $fresh->total_amount);
    }

    public function test_the_automatic_quotation_of_a_closed_appointment_follows_the_same_policy(): void
    {
        $appointment = Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $this->actor->id,
            'created_by' => $this->actor->id,
            'status' => 'completed',
            'final_amount' => 180.0,
        ]);

        $quotation = app(BillingService::class)->generateQuotationFromAppointment($appointment);

        $this->assertSame('18.00', $quotation->tax_percentage);
        $this->assertSame('27.46', $quotation->tax_amount);
        $this->assertSame('152.54', $quotation->subtotal);
        $this->assertSame('180.00', $quotation->total_amount);
    }

    public function test_the_stored_total_still_equals_the_sum_of_the_items(): void
    {
        $quotation = app(QuotationService::class)->generateQuotation($this->planWithPrice(118.0)->id);

        $itemsTotal = (float) $quotation->items()->sum('total_price');

        $this->assertSame($itemsTotal, (float) $quotation->total_amount);
        $this->assertSame(118.0, $itemsTotal);
        // Base + IGV is exactly the patient price: no money is invented.
        $this->assertSame(
            (float) $quotation->total_amount,
            (float) $quotation->subtotal + (float) $quotation->tax_amount
        );
    }

    public function test_the_total_is_the_same_with_or_without_tax_when_a_discount_applies(): void
    {
        $withTax = app(QuotationService::class)->createQuotation([
            'patient_id' => Patient::factory()->create()->id,
            'subtotal' => 200.0,
            'discount_amount' => 20.0,
        ]);
        $withoutTax = app(QuotationService::class)->createQuotation([
            'patient_id' => Patient::factory()->create()->id,
            'subtotal' => 200.0,
            'discount_amount' => 20.0,
            'tax_percentage' => 0,
        ]);

        $this->assertSame(180.0, (float) $withTax->total_amount);
        $this->assertSame(180.0, (float) $withoutTax->total_amount);
        $this->assertSame(27.46, (float) $withTax->tax_amount);
        $this->assertSame(0.0, (float) $withoutTax->tax_amount);
        $this->assertSame(
            (float) $withTax->total_amount,
            (float) $withTax->subtotal + (float) $withTax->tax_amount
        );
    }

    public function test_the_declared_rate_is_stored_as_sent(): void
    {
        $quotation = app(QuotationService::class)->createQuotation([
            'patient_id' => Patient::factory()->create()->id,
            'subtotal' => 112.10,
            'tax_percentage' => 12.1,
        ]);

        $this->assertSame('12.10', $quotation->tax_percentage);
        $this->assertSame('12.10', $quotation->tax_amount);
        $this->assertSame('100.00', $quotation->subtotal);
        $this->assertSame('112.10', $quotation->total_amount);
    }

    public function test_a_quotation_without_amounts_stays_at_zero(): void
    {
        $quotation = Quotation::create([
            'treatment_plan_id' => null,
            'patient_id' => Patient::factory()->create()->id,
            'created_by' => $this->actor->id,
            'quotation_number' => 'PR-ZERO-1',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'status' => 'draft',
        ]);

        $this->assertSame('0.00', $quotation->total_amount);
    }
}
