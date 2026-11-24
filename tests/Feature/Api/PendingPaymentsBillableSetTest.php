<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * #393 — Cobros (Caja) and Listos para facturar (Calendario) are the same
 * population and the same amount.
 *
 * The two doors used to answer different questions: `ready-to-bill` published
 * the balance of the closed attention, while `pending-payments` listed every
 * completed appointment without a transaction and priced it with the
 * appointment type's catalog price. A seeded clinic showed 37 rows at S/ 400.00
 * in Caja and 1 row at S/ 50.00 in the agenda, and the 36 extra rows had a
 * balance of 0.00. Both now read `BillingService::readyToBill`, which resolves
 * the pair through `App\Support\AppointmentBalance`.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PendingPaymentsBillableSetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function completed(float $amount, ?Carbon $when = null, ?float $totalCost = null): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'status' => 'completed',
            'scheduled_at' => $when ?? now()->subDay()->setTime(9, 0),
            'completed_at' => $when ?? now()->subDay()->setTime(9, 30),
            'duration_minutes' => 30,
            'final_amount' => $amount,
            'total_cost' => $totalCost ?? $amount,
        ]);
    }

    private function pay(Appointment $appointment, float $amount): Transaction
    {
        return Transaction::factory()->create([
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'created_by' => $this->admin->id,
            'amount' => $amount,
            'type' => 'payment',
            'status' => 'completed',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function cobros(): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/cash-register/pending-payments?per_page=100')
            ->assertOk()
            ->json('data');
    }

    /** @return array<int, array<string, mixed>> */
    private function billable(): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/appointments/ready-to-bill?per_page=100')
            ->assertOk()
            ->json('data');
    }

    public function test_both_surfaces_publish_the_same_rows_and_the_same_balance(): void
    {
        $unpaid = $this->completed(200);
        $partial = $this->completed(300);
        $this->pay($partial, 100);
        $paid = $this->completed(150);
        $this->pay($paid, 150);
        // The seeded historical rows: completed by the seeder with no closure
        // amount. They owe nothing and were the 36 phantom rows of Caja.
        $withoutAmount = $this->completed(0);
        // The older column: a row never closed by an act keeps its total_cost.
        $historical = $this->completed(0, null, 80);

        $cobros = collect($this->cobros())->keyBy('id');
        $billable = collect($this->billable())->keyBy('id');

        $this->assertEqualsCanonicalizing(
            [$unpaid->id, $partial->id, $historical->id],
            $cobros->keys()->all()
        );
        $this->assertEqualsCanonicalizing($cobros->keys()->all(), $billable->keys()->all());

        $this->assertSame(200.0, (float) $cobros[$unpaid->id]['amount']);
        $this->assertSame(200.0, (float) $billable[$unpaid->id]['balance']);

        // A partial payment is the case the old endpoint could not express: it
        // dropped the row because it had a transaction at all.
        $this->assertSame(200.0, (float) $cobros[$partial->id]['amount']);
        $this->assertSame(300.0, (float) $cobros[$partial->id]['total_amount']);
        $this->assertSame(100.0, (float) $cobros[$partial->id]['paid_amount']);
        $this->assertSame(200.0, (float) $billable[$partial->id]['balance']);

        $this->assertSame(80.0, (float) $cobros[$historical->id]['amount']);

        $this->assertArrayNotHasKey($paid->id, $cobros->all());
        $this->assertArrayNotHasKey($withoutAmount->id, $cobros->all());
    }

    public function test_a_completed_appointment_without_an_amount_is_not_billable(): void
    {
        $this->completed(0);

        $this->assertSame([], $this->cobros());
        $this->assertSame([], $this->billable());
    }

    public function test_the_amount_is_the_balance_and_not_the_appointment_type_price(): void
    {
        $type = AppointmentType::factory()->create(['price' => 400]);
        $appointment = $this->completed(50);
        $appointment->forceFill(['appointment_type_id' => $type->id])->save();

        $row = collect($this->cobros())->firstWhere('id', $appointment->id);

        $this->assertNotNull($row);
        $this->assertSame(50.0, (float) $row['amount']);
        $this->assertSame(50.0, (float) $row['total_amount']);
    }
}
