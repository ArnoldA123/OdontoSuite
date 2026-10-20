<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\QuotationApproval;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #201 — `approve`/`reject` respect the DB status machine, keep a single
 * approval row, and the `expired` filter reads the state instead of deriving
 * it from `valid_until`.
 */
class QuotationStatusMachineTest extends TestCase
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
            'status' => 'draft',
        ], $overrides));
    }

    public function test_approving_a_draft_is_rejected_with_an_explicit_message(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'draft']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/approve')
            ->assertStatus(422);

        $this->assertStringContainsString(
            'Solo se puede aprobar un presupuesto enviado o visto',
            $response->json('errors.status.0')
        );
        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertSame(0, QuotationApproval::count());
    }

    public function test_approving_a_sent_quotation_records_one_approval(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'sent']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/approve', ['notes' => 'Conforme'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $quotation->fresh()->status);
        $this->assertSame(1, QuotationApproval::where('quotation_id', $quotation->id)->count());
    }

    public function test_approving_twice_is_rejected_and_keeps_a_single_approval_row(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'sent']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/approve')
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/approve')
            ->assertStatus(422);

        $this->assertSame(1, QuotationApproval::where('quotation_id', $quotation->id)->count());
    }

    public function test_rejecting_a_draft_is_rejected_and_leaves_the_notes_untouched(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'draft', 'notes' => 'Nota original']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/reject', ['reason' => 'Precio alto'])
            ->assertStatus(422);

        $this->assertStringContainsString(
            'Solo se puede rechazar un presupuesto enviado o visto',
            $response->json('errors.status.0')
        );
        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertSame('Nota original', $quotation->fresh()->notes);
    }

    public function test_rejecting_a_viewed_quotation_is_allowed(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'viewed']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/reject', ['reason' => 'Precio alto'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertStringContainsString('Rechazado: Precio alto', $quotation->fresh()->notes);
    }

    public function test_rejecting_an_already_rejected_quotation_is_rejected(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'rejected']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/reject', ['reason' => 'Otra vez'])
            ->assertStatus(422);

        $this->assertSame('rejected', $quotation->fresh()->status);
    }

    public function test_approving_an_expired_quotation_is_rejected(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'expired']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/approve')
            ->assertStatus(422);

        $this->assertSame('expired', $quotation->fresh()->status);
    }

    public function test_the_expired_filter_reads_the_status_not_the_date(): void
    {
        $user = $this->admin();
        $sentPastDue = $this->quotation([
            'status' => 'sent',
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $expired = $this->quotation([
            'status' => 'expired',
            'valid_until' => now()->addDays(5)->toDateString(),
        ]);

        $onlyExpired = $this->actingAs($user, 'sanctum')
            ->getJson('/api/quotations?expired=1')
            ->assertOk()
            ->json('data');

        $this->assertSame([$expired->id], array_column($onlyExpired, 'id'));

        $notExpired = $this->actingAs($user, 'sanctum')
            ->getJson('/api/quotations?expired=0')
            ->assertOk()
            ->json('data');

        $this->assertSame([$sentPastDue->id], array_column($notExpired, 'id'));
    }
}
