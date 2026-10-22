<?php

namespace Tests\Feature\Api;

use App\Models\AccountingLock;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\AccountingLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3 (#283, S5) — the 403 of the accounting lock travels through the real
 * endpoints for the two modules whose controllers used to answer 500.
 *
 * `QuotationController@update` and the treatment-plan money doors wrap their
 * body in `catch (\Exception)`, so the `AuthorizationException` raised by
 * `AccountingLockService::guard()` is rethrown there. What is asserted here is
 * the HTTP envelope, the untouched row and the audit trail — never only the
 * exception type.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AccountingLockHttpGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
            'branch_id' => null,
        ]);

        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');
    }

    private function lock(string $module, \DateTimeInterface $date): AccountingLock
    {
        return AccountingLock::create([
            'module' => $module,
            'branch_id' => $this->branch->id,
            'locked_through' => $date->format('Y-m-d'),
            'set_by' => $this->admin->id,
        ]);
    }

    private function quotationAt(\DateTimeInterface $date): Quotation
    {
        $patient = Patient::factory()->create(['branch_id' => $this->branch->id]);

        return Quotation::create([
            'patient_id' => $patient->id,
            'created_by' => $this->admin->id,
            'quotation_number' => 'PR-'.strtoupper(substr(uniqid(), -8)),
            'quotation_date' => $date->format('Y-m-d'),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 100.0,
            'total_amount' => 100.0,
            'status' => 'draft',
            'notes' => 'Nota original',
        ])->refresh();
    }

    private function planAt(\DateTimeInterface $date): TreatmentPlan
    {
        return TreatmentPlan::factory()->create([
            'patient_id' => Patient::factory()->create(['branch_id' => $this->branch->id])->id,
            'created_by' => $this->admin->id,
            'title' => 'Plan original',
            'branch_id' => $this->branch->id,
            'start_date' => $date->format('Y-m-d'),
        ])->refresh();
    }

    public function test_updating_a_locked_quotation_answers_403_and_audits_the_attempt(): void
    {
        $quotation = $this->quotationAt(now()->subDays(5));
        $this->lock(AccountingLockService::QUOTATIONS, now()->subDays(2));

        $this->putJson("/api/quotations/{$quotation->id}", ['notes' => 'Nota reescrita'])
            ->assertForbidden();

        $this->assertSame('Nota original', $quotation->fresh()->notes);

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame(Quotation::class, $audit->auditable_type);
        $this->assertSame($quotation->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::QUOTATIONS, $audit->metadata['module']);
        $this->assertSame($this->branch->id, $audit->metadata['branch_id']);
    }

    public function test_updating_a_locked_quotation_with_a_registered_reason_succeeds(): void
    {
        $quotation = $this->quotationAt(now()->subDays(5));
        $this->lock(AccountingLockService::QUOTATIONS, now()->subDays(2));

        $this->putJson("/api/quotations/{$quotation->id}", [
            'notes' => 'Nota reescrita',
            'lock_override_reason' => 'Gerencia autorizo la correccion',
        ])->assertOk();

        $this->assertSame('Nota reescrita', $quotation->fresh()->notes);
        $this->assertSame(
            1,
            AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->count(),
        );
    }

    public function test_adding_an_item_to_a_locked_plan_answers_403_and_writes_nothing(): void
    {
        $plan = $this->planAt(now()->subDays(5));
        $this->lock(AccountingLockService::TREATMENT_PLANS, now()->subDays(2));

        $this->postJson("/api/treatment-plans/{$plan->id}/add-item", [
            'procedure_name' => 'Limpieza',
            'quantity' => 1,
            'unit_cost' => 80,
        ])->assertForbidden();

        $this->assertSame(0, TreatmentPlanItem::where('treatment_plan_id', $plan->id)->count());
        $this->assertSame(
            1,
            AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)
                ->where('auditable_id', $plan->id)
                ->count(),
        );
    }

    public function test_removing_an_item_from_a_locked_plan_answers_403_and_keeps_the_item(): void
    {
        $plan = $this->planAt(now()->subDays(5));
        $this->lock(AccountingLockService::TREATMENT_PLANS, now()->subDays(2));

        $item = TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_name' => 'Limpieza',
            'quantity' => 1,
            'unit_cost' => 80,
            'total_cost' => 80,
        ]);

        $this->deleteJson("/api/treatment-plans/items/{$item->id}")->assertForbidden();

        $this->assertNotNull($item->fresh());
        $this->assertSame(
            1,
            AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)
                ->where('auditable_id', $plan->id)
                ->where('auditable_type', TreatmentPlan::class)
                ->count(),
        );
    }

    public function test_updating_a_locked_plan_answers_403(): void
    {
        $plan = $this->planAt(now()->subDays(5));
        $this->lock(AccountingLockService::TREATMENT_PLANS, now()->subDays(2));

        $this->putJson("/api/treatment-plans/{$plan->id}", ['title' => 'Plan reescrito'])
            ->assertForbidden();

        $this->assertSame('Plan original', $plan->fresh()->title);
    }
}
