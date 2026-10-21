<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #201 — the first PDF export is what moves `sent` to `viewed` (the decision
 * recorded in the feature spec). It is idempotent and never rewrites a
 * terminal status.
 */
class QuotationViewedOnPdfTest extends TestCase
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
            'subtotal' => 100,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'status' => 'sent',
        ], $overrides));
    }

    public function test_exporting_the_pdf_of_a_sent_quotation_marks_it_viewed(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'sent']);

        $this->actingAs($user, 'sanctum')
            ->get('/api/quotations/'.$quotation->id.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame('viewed', $quotation->fresh()->status);
    }

    public function test_a_draft_is_not_marked_viewed_by_the_export(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'draft']);

        $this->actingAs($user, 'sanctum')
            ->get('/api/quotations/'.$quotation->id.'/pdf')
            ->assertOk();

        $this->assertSame('draft', $quotation->fresh()->status);
    }

    public function test_an_approved_quotation_keeps_its_terminal_status(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'approved']);

        $this->actingAs($user, 'sanctum')
            ->get('/api/quotations/'.$quotation->id.'/pdf')
            ->assertOk();

        $this->assertSame('approved', $quotation->fresh()->status);
    }

    public function test_repeated_exports_keep_the_viewed_status(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'sent']);

        $this->actingAs($user, 'sanctum')->get('/api/quotations/'.$quotation->id.'/pdf')->assertOk();
        $this->actingAs($user, 'sanctum')->get('/api/quotations/'.$quotation->id.'/pdf')->assertOk();

        $this->assertSame('viewed', $quotation->fresh()->status);
    }
}
