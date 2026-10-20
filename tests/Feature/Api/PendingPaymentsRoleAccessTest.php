<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Issue #191 — GET /api/pending-payments, POST /api/pending-payments/{id}/pay
 * and the /api/cash-register/pending-payments alias lived in the
 * role:administrador,finanzas group, so the receptionist who actually collects
 * at the desk got a 403. Option A of the issue moves the three routes into the
 * cash group recepcion already owns since issue #55.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PendingPaymentsRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function pendingAppointment(float $totalCost = 100): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'status' => 'completed',
            'scheduled_at' => Carbon::yesterday()->setTime(9, 0),
            'duration_minutes' => 30,
            'total_cost' => $totalCost,
            'paid_amount' => 0,
            'balance' => $totalCost,
        ]);
    }

    public function test_a_recepcionista_lists_the_pending_payments(): void
    {
        $recepcionista = $this->user('recepcionista');
        $this->pendingAppointment();

        $payload = $this->actingAs($recepcionista, 'sanctum')
            ->getJson('/api/pending-payments')
            ->assertOk()
            ->json();

        $this->assertIsList($payload['data']);
        $this->assertArrayHasKey('total', $payload['meta']);
        $this->assertSame(1, $payload['meta']['total']);

        $alias = $this->actingAs($recepcionista, 'sanctum')
            ->getJson('/api/cash-register/pending-payments')
            ->assertOk()
            ->json();

        $this->assertSame($payload['data'], $alias['data']);
    }

    public function test_a_recepcionista_collects_a_pending_appointment(): void
    {
        $recepcionista = $this->user('recepcionista');
        $appointment = $this->pendingAppointment();
        $method = PaymentMethod::factory()->create();

        CashRegisterSession::factory()->create([
            'user_id' => $recepcionista->id,
            'status' => 'open',
            'opening_amount' => 0,
        ]);

        $payload = $this->actingAs($recepcionista, 'sanctum')
            ->postJson("/api/pending-payments/{$appointment->id}/pay", [
                'payment_method_id' => $method->id,
                'amount' => 100,
            ])
            ->assertOk()
            ->json();

        $this->assertEqualsWithDelta(0.0, (float) $payload['meta']['appointment_balance'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $appointment->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $appointment->fresh()->paid_amount, 0.001);
    }

    public function test_a_clinical_role_cannot_read_or_collect(): void
    {
        $odontologo = $this->user('odontologo');
        $appointment = $this->pendingAppointment();
        $method = PaymentMethod::factory()->create();

        $this->actingAs($odontologo, 'sanctum')
            ->getJson('/api/pending-payments')
            ->assertForbidden();

        $this->actingAs($odontologo, 'sanctum')
            ->postJson("/api/pending-payments/{$appointment->id}/pay", [
                'payment_method_id' => $method->id,
                'amount' => 100,
            ])
            ->assertForbidden();
    }

    public function test_a_finanzas_user_keeps_access(): void
    {
        $finanzas = $this->user('finanzas');
        $this->pendingAppointment();

        $this->actingAs($finanzas, 'sanctum')
            ->getJson('/api/pending-payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }
}
