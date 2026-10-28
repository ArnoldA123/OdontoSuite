<?php

namespace Tests\Feature\Api;

use App\Models\AccountingLock;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountingLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\ClinicalCompliancePayload;
use Tests\TestCase;

/**
 * #466 — the blocked charge of the pending-payment door leaves its evidence.
 *
 * `POST /api/pending-payments/{id}/pay` wraps the create in its own transaction
 * and the accounting-lock guard used to run inside it, so the rolled-back
 * attempt discarded its `accounting_lock_blocked` audit entry. The door now
 * guards before opening the transaction — the drawer is resolved here, so it
 * cannot hoist the guard on its own — and tells the service the lock is already
 * answered, which keeps the authorized override from being audited twice.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PendingPaymentsBlockedAuditTest extends TestCase
{
    use ClinicalCompliancePayload;
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    private Patient $patient;

    private PaymentMethod $cashMethod;

    private CashRegisterSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
            'branch_id' => null,
        ]);

        $this->patient = Patient::factory()->create(['branch_id' => $this->branch->id]);

        $this->cashMethod = PaymentMethod::factory()->create([
            'name' => 'Efectivo',
            'type' => PaymentMethod::TYPE_CASH,
            'requires_reference' => false,
        ]);

        $this->session = CashRegisterSession::factory()->create([
            'user_id' => $this->admin->id,
            'branch_id' => $this->branch->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function closurePayload(float $amount): array
    {
        return [
            ...$this->compliancePayload(),
            'mode' => 'execution',
            'evolution' => [
                'subjective' => 'Dolor en pieza 16 desde hace tres días.',
                'objective' => 'Caries oclusal profunda, sin exposición pulpar.',
                'assessment' => 'Caries dentinaria.',
                'plan' => 'Obturación con resina compuesta.',
            ],
            'treatment_plan' => [
                'title' => 'Restauración 16',
                'items' => [[
                    'procedure_name' => 'Obturación composite',
                    'dental_piece_id' => null,
                    'unit_cost' => $amount,
                    'quantity' => 1,
                ]],
            ],
        ];
    }

    private function closedAppointment(float $amount): Appointment
    {
        $professional = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
            'branch_id' => null,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $this->patient->id,
            'user_id' => $professional->id,
            'created_by' => $professional->id,
            'branch_id' => $this->branch->id,
            'status' => 'arrived',
            'checked_in_at' => now(),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertOk();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->closurePayload($amount))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        return $appointment->fresh();
    }

    private function lockThroughToday(): AccountingLock
    {
        return AccountingLock::create([
            'module' => AccountingLockService::CASH_TRANSACTIONS,
            'branch_id' => $this->branch->id,
            'locked_through' => now()->toDateString(),
            'set_by' => $this->admin->id,
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function pay(Appointment $appointment, float $amount, array $extra = []): TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/pending-payments/{$appointment->id}/pay", array_merge([
                'payment_method_id' => $this->cashMethod->id,
                'amount' => $amount,
            ], $extra));
    }

    public function test_a_blocked_charge_answers_403_and_keeps_its_audit_entry(): void
    {
        $appointment = $this->closedAppointment(180);
        $this->lockThroughToday();

        $response = $this->pay($appointment, (float) $appointment->final_amount);

        $response->assertForbidden();
        $response->assertJsonPath('code', 'accounting_lock_blocked');
        $response->assertJsonPath(
            'message',
            sprintf(
                'El módulo [%s] está bloqueado contablemente hasta el %s: la operación [%s] sobre un registro de esa fecha requiere autorización registrada.',
                AccountingLockService::CASH_TRANSACTIONS,
                now()->toDateString(),
                'create',
            ),
        );

        $this->assertSame(0, Transaction::count(), 'The blocked charge must not write the transaction.');
        $this->assertSame(0, CashMovement::count(), 'The blocked charge must not move the drawer.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(AccountingLockService::CASH_TRANSACTIONS, $audit->metadata['module']);
        $this->assertSame(now()->toDateString(), $audit->metadata['accounting_date']);
        $this->assertSame('create', $audit->metadata['action']);
        $this->assertSame($this->branch->id, $audit->metadata['branch_id']);
        $this->assertSame(Appointment::class, $audit->auditable_type);
        $this->assertSame($appointment->id, $audit->auditable_id);
    }

    public function test_an_authorized_retry_charges_and_audits_the_override_once(): void
    {
        $appointment = $this->closedAppointment(180);
        $this->lockThroughToday();

        $this->pay($appointment, (float) $appointment->final_amount, [
            'lock_override_reason' => 'Gerencia autorizo el cobro de hoy',
        ])->assertOk()->assertJsonPath('meta.message', 'Pago registrado correctamente.');

        $transaction = Transaction::sole();
        $this->assertSame('payment', $transaction->type);
        $this->assertSame($this->admin->id, $transaction->created_by);
        $this->assertSame(1, CashMovement::where('transaction_id', $transaction->id)->count());

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame('Gerencia autorizo el cobro de hoy', $audit->metadata['reason']);
        $this->assertSame(AccountingLockService::CASH_TRANSACTIONS, $audit->metadata['module']);
        $this->assertSame('create', $audit->metadata['action']);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_a_charge_without_a_lock_writes_no_accounting_audit(): void
    {
        $appointment = $this->closedAppointment(180);

        $this->pay($appointment, (float) $appointment->final_amount)->assertOk();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, CashMovement::count());
        $this->assertSame(
            0,
            AuditLog::whereIn('action', [
                AccountingLockService::ACTION_BLOCKED,
                AccountingLockService::ACTION_OVERRIDDEN,
            ])->count(),
        );
    }
}
