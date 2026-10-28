<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ClinicalCompliancePayload;
use Tests\TestCase;

/**
 * #393 — the closed attention is collected with the balance the list publishes.
 *
 * The billable list computed the debt from `final_amount` while the paying door
 * read `total_cost`/`paid_amount`, so a closed attention whose closure had set
 * only `final_amount` showed a debt that could not be collected. One definition
 * of the balance (`App\Support\AppointmentBalance`) now feeds both ends, and the
 * flow closure → collection → zero balance is walked end to end.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AppointmentPaymentBalanceTest extends TestCase
{
    use ClinicalCompliancePayload;
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function openDrawer(User $actor): void
    {
        $this->actingAs($actor, 'sanctum');

        app(CashRegisterService::class)->openSession([
            'branch_id' => Branch::factory()->create()->id,
            'opening_amount' => 100.0,
        ]);
    }

    private function cashMethod(): PaymentMethod
    {
        return PaymentMethod::factory()->create(['name' => 'Efectivo', 'type' => 'cash']);
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

    private function closedAppointment(User $professional, float $amount): Appointment
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $professional->id,
            'created_by' => $professional->id,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readyToBillRows(User $actor): array
    {
        return $this->actingAs($actor, 'sanctum')
            ->getJson('/api/appointments/ready-to-bill')
            ->assertOk()
            ->json('data');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readyToBillRow(User $actor, Appointment $appointment): ?array
    {
        return collect($this->readyToBillRows($actor))->firstWhere('id', $appointment->id);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function pay(User $actor, Appointment $appointment, PaymentMethod $method, float $amount, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor, 'sanctum')
            ->postJson("/api/pending-payments/{$appointment->id}/pay", array_merge([
                'payment_method_id' => $method->id,
                'amount' => $amount,
            ], $extra));
    }

    public function test_the_billable_list_publishes_the_balance_the_closure_left(): void
    {
        $professional = $this->user('odontologo');
        $admin = $this->user('administrador');
        $appointment = $this->closedAppointment($professional, 180);

        $row = $this->readyToBillRow($admin, $appointment);

        $this->assertNotNull($row, 'The closed attention must be listed as billable.');
        $this->assertEqualsWithDelta(0.0, (float) $row['paid_amount'], 0.001);
        $this->assertEqualsWithDelta((float) $row['final_amount'], (float) $row['balance'], 0.001);
        $this->assertGreaterThan(0, (float) $row['balance']);
    }

    public function test_paying_the_published_balance_settles_the_attention(): void
    {
        $professional = $this->user('odontologo');
        $admin = $this->user('administrador');
        $appointment = $this->closedAppointment($professional, 180);
        $method = $this->cashMethod();
        $this->openDrawer($admin);

        $row = $this->readyToBillRow($admin, $appointment);
        $this->assertNotNull($row);
        $balance = (float) $row['balance'];

        $response = $this->pay($admin, $appointment, $method, $balance);
        $response->assertOk()
            ->assertJsonPath('meta.message', 'Pago registrado correctamente.');
        $this->assertEqualsWithDelta(0.0, (float) $response->json('meta.appointment_balance'), 0.001);

        $settled = $appointment->fresh();
        $this->assertEqualsWithDelta((float) $settled->final_amount, (float) $settled->paid_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $settled->balance, 0.001);
        $this->assertEqualsWithDelta((float) $settled->final_amount, (float) $settled->total_cost, 0.001);

        $this->assertNotContains($appointment->id, array_column($this->readyToBillRows($admin), 'id'));

        $preview = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}/payment-preview")
            ->assertOk();
        $this->assertEqualsWithDelta(0.0, (float) $preview->json('data.balance'), 0.001);
        $this->assertEqualsWithDelta(
            (float) $preview->json('data.final_amount'),
            (float) $preview->json('data.paid_amount'),
            0.001
        );
    }

    public function test_a_settled_attention_answers_its_own_reason_instead_of_the_missing_balance(): void
    {
        $professional = $this->user('odontologo');
        $admin = $this->user('administrador');
        $appointment = $this->closedAppointment($professional, 180);
        $method = $this->cashMethod();
        $this->openDrawer($admin);

        $row = $this->readyToBillRow($admin, $appointment);
        $this->assertNotNull($row);
        $this->pay($admin, $appointment, $method, (float) $row['balance'])->assertOk();

        $response = $this->pay($admin, $appointment, $method, 10);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'La cita ya tiene un pago registrado y no le queda saldo pendiente.');
        $this->assertSame(1, Transaction::where('appointment_id', $appointment->id)->count());
    }

    public function test_a_partial_payment_is_accepted_and_the_rest_stays_payable(): void
    {
        $professional = $this->user('odontologo');
        $admin = $this->user('administrador');
        $appointment = $this->closedAppointment($professional, 300);
        $method = $this->cashMethod();
        $this->openDrawer($admin);

        $this->pay($admin, $appointment, $method, 100.0)->assertOk();

        $partial = $appointment->fresh();
        $this->assertEqualsWithDelta(100.0, (float) $partial->paid_amount, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $partial->balance, 0.001);
        $this->assertEqualsWithDelta((float) $partial->final_amount, (float) $partial->total_cost, 0.001);

        $row = $this->readyToBillRow($admin, $appointment);
        $this->assertNotNull($row, 'A partially paid attention is still billable.');
        $this->assertEqualsWithDelta(200.0, (float) $row['balance'], 0.001);

        $this->pay($admin, $appointment, $method, (float) $row['balance'])->assertOk();

        $settled = $appointment->fresh();
        $this->assertEqualsWithDelta((float) $settled->final_amount, (float) $settled->paid_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $settled->balance, 0.001);
        $this->assertSame(2, Transaction::where('appointment_id', $appointment->id)->count());
    }

    public function test_an_amount_above_the_remaining_balance_is_rejected_without_a_transaction(): void
    {
        $professional = $this->user('odontologo');
        $admin = $this->user('administrador');
        $appointment = $this->closedAppointment($professional, 300);
        $method = $this->cashMethod();
        $this->openDrawer($admin);

        $row = $this->readyToBillRow($admin, $appointment);
        $this->assertNotNull($row);
        $balance = (float) $row['balance'];

        $response = $this->pay($admin, $appointment, $method, $balance + 0.01);

        $response->assertStatus(422);
        $this->assertStringContainsString('excede el saldo pendiente', $response->json('message'));
        $this->assertSame(0, Transaction::where('appointment_id', $appointment->id)->count());
        $this->assertEqualsWithDelta(0.0, (float) $appointment->fresh()->paid_amount, 0.001);
    }
}
