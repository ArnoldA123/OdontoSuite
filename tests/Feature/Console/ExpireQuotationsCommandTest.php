<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #201 — `quotations:expire` is the writer of the `expired` state: it
 * formalizes every pending quotation whose validity ended, and never touches a
 * draft, a terminal status, or a quotation that is still valid today.
 */
class ExpireQuotationsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function quotation(string $status, string $validUntil): Quotation
    {
        $user = $this->admin();
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
        ]);

        return Quotation::create([
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'quotation_number' => 'PR-'.strtoupper(Str::random(8)),
            'quotation_date' => now()->subDays(40)->toDateString(),
            'valid_until' => $validUntil,
            'subtotal' => 100,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'status' => $status,
        ]);
    }

    public function test_it_expires_sent_and_viewed_quotations_past_their_validity(): void
    {
        $sent = $this->quotation('sent', now()->subDay()->toDateString());
        $viewed = $this->quotation('viewed', now()->subDays(10)->toDateString());

        $this->artisan('quotations:expire')
            ->expectsOutput('Presupuestos expirados: 2.')
            ->assertExitCode(0);

        $this->assertSame('expired', $sent->fresh()->status);
        $this->assertSame('expired', $viewed->fresh()->status);
    }

    public function test_a_quotation_valid_until_today_is_not_expired(): void
    {
        $today = $this->quotation('sent', now()->toDateString());
        $future = $this->quotation('viewed', now()->addDay()->toDateString());

        $this->artisan('quotations:expire')->assertExitCode(0);

        $this->assertSame('sent', $today->fresh()->status);
        $this->assertSame('viewed', $future->fresh()->status);
    }

    public function test_others_statuses_are_never_rewritten(): void
    {
        $draft = $this->quotation('draft', now()->subDay()->toDateString());
        $approved = $this->quotation('approved', now()->subDay()->toDateString());
        $rejected = $this->quotation('rejected', now()->subDay()->toDateString());

        $this->artisan('quotations:expire')->assertExitCode(0);

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    public function test_a_second_run_is_idempotent(): void
    {
        $this->quotation('sent', now()->subDay()->toDateString());

        $this->artisan('quotations:expire')
            ->expectsOutput('Presupuestos expirados: 1.')
            ->assertExitCode(0);

        $this->artisan('quotations:expire')
            ->expectsOutput('Presupuestos expirados: 0.')
            ->assertExitCode(0);
    }
}
