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
use App\Services\QuotationService;
use App\Services\TreatmentPlanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3 (#283, S5) — quotation and treatment-plan money, frozen inside the lock.
 *
 * Both services guard before opening their own transaction, so the refused
 * mutation leaves the row untouched and the attempt in `audit_logs`. A
 * registered override (payload key `lock_override_reason`) succeeds and the key
 * never reaches the model as a column.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AccountingLockMoneyGuardTest extends TestCase
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
        ]);

        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');
    }

    private function lock(string $module, \DateTimeInterface $date, ?Branch $branch = null): AccountingLock
    {
        return AccountingLock::create([
            'module' => $module,
            'branch_id' => $branch?->id,
            'locked_through' => $date->format('Y-m-d'),
            'set_by' => $this->admin->id,
        ]);
    }

    private function quotationAt(\DateTimeInterface $date): Quotation
    {
        $patient = Patient::factory()->create(['branch_id' => $this->branch->id]);

        $quotation = Quotation::create([
            'patient_id' => $patient->id,
            'created_by' => $this->admin->id,
            'quotation_number' => 'PR-'.strtoupper(substr(uniqid(), -8)),
            'quotation_date' => $date->format('Y-m-d'),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 100.0,
            'total_amount' => 100.0,
            'status' => 'draft',
            'notes' => 'Nota original',
        ]);

        return $quotation->refresh();
    }

    private function planAt(\DateTimeInterface $date): TreatmentPlan
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => Patient::factory()->create(['branch_id' => $this->branch->id])->id,
            'created_by' => $this->admin->id,
            'title' => 'Plan original',
            'start_date' => $date->format('Y-m-d'),
        ]);

        return $plan->refresh();
    }

    public function test_updating_a_quotation_inside_the_lock_is_refused_and_audited(): void
    {
        $quotation = $this->quotationAt(now()->subDays(5));
        $this->lock(AccountingLockService::QUOTATIONS, now()->subDays(2), $this->branch);

        try {
            app(QuotationService::class)->updateQuotation($quotation->id, ['notes' => 'Nota reescrita']);
            $this->fail('A quotation inside the lock must be refused.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString(AccountingLockService::QUOTATIONS, $e->getMessage());
        }

        $this->assertSame('Nota original', $quotation->fresh()->notes, 'The blocked update must not change the row.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame(Quotation::class, $audit->auditable_type);
        $this->assertSame($quotation->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::QUOTATIONS, $audit->metadata['module']);
        $this->assertSame($quotation->quotation_date->toDateString(), $audit->metadata['accounting_date']);
        $this->assertSame('update', $audit->metadata['action']);
        $this->assertSame($this->branch->id, $audit->metadata['branch_id']);
    }

    public function test_updating_a_quotation_inside_the_lock_with_a_registered_reason_succeeds(): void
    {
        $quotation = $this->quotationAt(now()->subDays(5));
        $this->lock(AccountingLockService::QUOTATIONS, now()->subDays(2), $this->branch);

        $updated = app(QuotationService::class)->updateQuotation($quotation->id, [
            'notes' => 'Nota reescrita',
            'lock_override_reason' => 'Gerencia autorizo la correccion',
        ]);

        $this->assertSame('Nota reescrita', $updated->notes);
        $this->assertSame('Nota reescrita', $quotation->fresh()->notes);

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame('Gerencia autorizo la correccion', $audit->metadata['reason']);
        $this->assertSame(AccountingLockService::QUOTATIONS, $audit->metadata['module']);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_updating_a_treatment_plan_inside_the_lock_is_refused_and_audited(): void
    {
        $plan = $this->planAt(now()->subDays(5));
        $this->lock(AccountingLockService::TREATMENT_PLANS, now()->subDays(2));

        try {
            app(TreatmentPlanService::class)->updatePlan($plan->id, ['title' => 'Plan reescrito']);
            $this->fail('A treatment plan inside the lock must be refused.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString(AccountingLockService::TREATMENT_PLANS, $e->getMessage());
        }

        $this->assertSame('Plan original', $plan->fresh()->title, 'The blocked update must not change the row.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame(TreatmentPlan::class, $audit->auditable_type);
        $this->assertSame($plan->id, $audit->auditable_id);
        $this->assertSame('update', $audit->metadata['action']);
        $this->assertSame($plan->start_date->toDateString(), $audit->metadata['accounting_date']);
    }

    public function test_adding_an_item_inside_the_lock_is_refused_and_audited(): void
    {
        $plan = $this->planAt(now()->subDays(5));
        $this->lock(AccountingLockService::TREATMENT_PLANS, now()->subDays(2));

        try {
            app(TreatmentPlanService::class)->addItem($plan->id, [
                'procedure_name' => 'Limpieza dental',
                'quantity' => 1,
                'unit_cost' => 80.0,
            ]);
            $this->fail('Adding money to a plan inside the lock must be refused.');
        } catch (AuthorizationException) {
            // Expected: the plan is frozen.
        }

        $this->assertSame(0, TreatmentPlanItem::where('treatment_plan_id', $plan->id)->count());
        $this->assertSame(0.0, (float) $plan->fresh()->total_cost);

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame('add_item', $audit->metadata['action']);
        $this->assertSame($plan->id, $audit->auditable_id);
    }

    public function test_removing_an_item_inside_the_lock_is_refused_and_audited(): void
    {
        $plan = $this->planAt(now()->subDays(5));
        $item = TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_name' => 'Limpieza dental',
            'quantity' => 1,
            'unit_cost' => 80.0,
            'total_cost' => 80.0,
        ]);

        $this->lock(AccountingLockService::TREATMENT_PLANS, now()->subDays(2));

        try {
            app(TreatmentPlanService::class)->removeItem($item->id);
            $this->fail('Removing money from a plan inside the lock must be refused.');
        } catch (AuthorizationException) {
            // Expected: the plan is frozen.
        }

        $this->assertNotNull($item->fresh(), 'The blocked delete must not remove the item.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame('remove_item', $audit->metadata['action']);
        $this->assertSame($plan->id, $audit->auditable_id);
    }

    public function test_a_quotation_dated_after_the_lock_is_unaffected(): void
    {
        $quotation = $this->quotationAt(now());
        $this->lock(AccountingLockService::QUOTATIONS, now()->subDay(), $this->branch);

        $updated = app(QuotationService::class)->updateQuotation($quotation->id, ['notes' => 'Nota reescrita']);

        $this->assertSame('Nota reescrita', $updated->notes);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->count());
    }
}
