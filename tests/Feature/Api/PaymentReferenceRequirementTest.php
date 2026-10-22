<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #291, S4 — a payment method that leaves a voucher or an operation number
 * cannot book the charge without it, on both cash doors and without leaving a
 * trace behind.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PaymentReferenceRequirementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private CashRegisterSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $this->session = app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => 100.0,
        ]);
    }

    private function method(string $type, bool $requiresReference, string $name): PaymentMethod
    {
        return PaymentMethod::factory()->create([
            'name' => $name,
            'type' => $type,
            'requires_reference' => $requiresReference,
        ]);
    }

    private function charge(PaymentMethod $method, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/transactions', array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => $method->id,
            'amount' => 100.0,
            'type' => 'payment',
            'description' => 'Cobro de prueba',
        ], $extra));
    }

    public function test_a_method_that_requires_a_reference_refuses_the_charge_without_one(): void
    {
        $method = $this->method('transfer', true, 'Transferencia bancaria');

        $response = $this->charge($method)->assertStatus(422);

        $response->assertJsonValidationErrors('reference_number');
        $this->assertStringContainsString(
            'El método de pago Transferencia bancaria exige un número de referencia.',
            $response->json('errors.reference_number.0')
        );
    }

    public function test_the_refused_charge_leaves_no_money_behind(): void
    {
        $method = $this->method('card', true, 'Tarjeta de crédito');

        $this->charge($method)->assertStatus(422);

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, CashMovement::where('type', 'income')->count());
        $this->assertEqualsWithDelta(
            100.0,
            app(CashRegisterService::class)->getSessionSummary($this->session->fresh())['expected_amount'],
            0.001
        );
    }

    public function test_a_reference_made_of_spaces_is_not_a_reference(): void
    {
        $method = $this->method('wallet', true, 'Yape');

        $this->charge($method, ['reference_number' => '   '])->assertStatus(422);
        $this->assertSame(0, Transaction::count());
    }

    public function test_the_charge_with_its_reference_is_registered_and_stored(): void
    {
        $method = $this->method('transfer', true, 'Transferencia bancaria');

        $this->charge($method, ['reference_number' => 'OP-778899'])->assertCreated();

        $transaction = Transaction::firstOrFail();

        $this->assertSame('OP-778899', $transaction->reference_number);
        $this->assertSame($method->id, $transaction->payment_method_id);
    }

    public function test_a_cash_charge_needs_no_reference(): void
    {
        $method = $this->method('cash', false, 'Efectivo');

        $this->charge($method)->assertCreated();

        $this->assertNull(Transaction::firstOrFail()->reference_number);
    }

    public function test_the_pending_payment_door_answers_the_same_rule(): void
    {
        $method = $this->method('transfer', true, 'Transferencia bancaria');
        $appointment = $this->completedAppointment(150.0);

        $response = $this->postJson("/api/pending-payments/{$appointment->id}/pay", [
            'payment_method_id' => $method->id,
            'amount' => 150.0,
        ])->assertStatus(422);

        $response->assertJsonValidationErrors('reference_number');
        $this->assertSame(0, Transaction::count());
        $this->assertSame('0.00', $appointment->fresh()->paid_amount);
    }

    public function test_the_pending_payment_door_accepts_the_charge_with_its_reference(): void
    {
        $method = $this->method('card', true, 'Tarjeta de crédito');
        $appointment = $this->completedAppointment(150.0);

        $this->postJson("/api/pending-payments/{$appointment->id}/pay", [
            'payment_method_id' => $method->id,
            'amount' => 150.0,
            'reference_number' => 'VOUCHER-42',
        ])->assertOk();

        $this->assertSame('VOUCHER-42', Transaction::firstOrFail()->reference_number);
        $this->assertSame('0.00', $appointment->fresh()->balance);
    }

    public function test_the_rule_moves_with_the_method(): void
    {
        $method = $this->method('transfer', true, 'Transferencia bancaria');
        $this->charge($method)->assertStatus(422);

        // The admin turns the requirement off: the same payload now books.
        $method->update(['requires_reference' => false]);

        $this->charge($method)->assertCreated();
    }

    private function completedAppointment(float $total): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'branch_id' => $this->branch->id,
            'status' => 'completed',
            'scheduled_at' => now()->subDay(),
            'total_cost' => $total,
            'paid_amount' => 0,
            'balance' => $total,
        ]);
    }
}
