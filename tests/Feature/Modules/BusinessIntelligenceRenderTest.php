<?php

namespace Tests\Feature\Modules;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR6 / Phase 4b — Business Intelligence Render.
 *
 * Validation-first Feature test for the BI report contract v2 documented in
 * `odd/tasks/business-intelligence-ui-redesign.md`. Exercises the
 * `GET /api/reports/revenue` endpoint (auth:sanctum): collected payments drive
 * the rows, the retired `Resumen` row is gone, and `meta.summary` carries the
 * period totals.
 * Must run against MySQL harness (AGENTS.md §6 — SQLite transactions.type tech debt).
 */
class BusinessIntelligenceRenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // User::factory() bypass: same MySQL NOT NULL `username` reason as CatalogFilterTest.
        return User::create([
            'name' => 'BI Admin',
            'email' => 'bi.admin.test@example.com',
            'username' => 'bi_admin',
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function branch(): Branch
    {
        return Branch::create([
            'name' => 'BI Branch',
            'code' => 'BI-001',
            'address' => 'Av. Test 123',
            'city' => 'Lima',
            'is_active' => true,
        ]);
    }

    private function appointmentType(int $price = 25000): AppointmentType
    {
        return AppointmentType::create([
            'name' => 'BI Control',
            'default_duration_minutes' => 30,
            'price' => $price,
            'requires_confirmation' => true,
            'is_active' => true,
        ]);
    }

    private function chair(): DentalChair
    {
        return DentalChair::create([
            'name' => 'BI Chair',
            'code' => 'BIC-1',
            'is_active' => true,
        ]);
    }

    private function completedAppointment(
        User $doctor,
        Branch $branch,
        AppointmentType $type,
        DentalChair $chair,
        Carbon $scheduledAt,
    ): Appointment {
        $patient = Patient::create([
            'first_name' => 'BI',
            'last_name' => 'Patient',
            'email' => 'bi.patient.'.uniqid('', true).'@example.com',
            'phone' => '+51 9'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);

        return Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'dental_chair_id' => $chair->id,
            'branch_id' => $branch->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 'completed',
            'created_by' => $doctor->id,
        ]);
    }

    private function payment(Appointment $appointment, float $amount, Carbon $createdAt): Transaction
    {
        $method = PaymentMethod::create([
            'name' => 'BI Cash',
            'code' => 'BIPM'.strtoupper(substr(uniqid(), -8)),
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'payment_method_id' => $method->id,
            'created_by' => $appointment->user_id,
            'transaction_number' => 'TXN-'.strtoupper(substr(uniqid(), -10)),
            'type' => 'payment',
            'amount' => $amount,
            'status' => 'completed',
            'processed_at' => $createdAt,
        ]);

        $transaction->timestamps = false;
        $transaction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $transaction;
    }

    public function test_revenue_report_renders_contract_v2_groups_and_summary(): void
    {
        // The endpoint reports the whole clinic: a payment another suite test
        // left behind would land in this period. Pin an empty ledger first.
        Transaction::query()->forceDelete();
        Appointment::query()->forceDelete();

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType(25000);
        $chair = $this->chair();

        $appointment = $this->completedAppointment($admin, $branch, $type, $chair, Carbon::now()->subDays(2));
        $this->payment($appointment, 300.00, Carbon::now()->subDays(2)->addHour());

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/reports/revenue');

        $response->assertOk();

        $rows = $response->json('data');
        $summary = $response->json('meta.summary');

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows, 'Revenue report MUST contain at least one row when completed payments exist.');

        $types = array_values(array_unique(array_column($rows, 'type')));
        $expectedTypes = ['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día'];

        sort($types);
        sort($expectedTypes);

        $this->assertSame(
            $expectedTypes,
            $types,
            'Contract v2: revenue rows carry only the exclusive cuts; the retired Resumen row must be gone.'
        );
        $this->assertNotContains('Total General', array_column($rows, 'category'));

        foreach ($rows as $row) {
            $this->assertArrayHasKey('category', $row);
            $this->assertArrayHasKey('total_revenue', $row);
            $this->assertNotSame('Resumen', $row['type']);
        }

        $this->assertIsArray($summary);
        $this->assertEqualsCanonicalizing(
            ['appointments_count', 'total_revenue', 'average_per_appointment', 'period'],
            array_keys($summary)
        );
        $this->assertSame(1, $summary['appointments_count']);
        $this->assertEqualsWithDelta(300.00, (float) $summary['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(300.00, (float) $summary['average_per_appointment'], 0.001);
        $this->assertMatchesRegularExpression(
            '#^\d{2}/\d{2}/\d{4} - \d{2}/\d{2}/\d{4}$#',
            $summary['period']
        );

        $dayRows = array_values(array_filter($rows, fn (array $row) => $row['type'] === 'Por Día'));

        $this->assertNotEmpty($dayRows);
        $this->assertEqualsWithDelta(300.00, (float) array_sum(array_column($dayRows, 'total_revenue')), 0.001);
    }

    public function test_empty_dataset_renders_200_with_zero_summaries(): void
    {
        Transaction::query()->forceDelete();
        Appointment::query()->forceDelete();

        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/reports/revenue');

        $response->assertOk();

        // Contract v2: no synthetic Resumen row. An empty period is an empty
        // `data` array plus zeroed totals in `meta.summary`.
        $this->assertSame([], $response->json('data'));

        $summary = $response->json('meta.summary');

        $this->assertIsArray($summary);
        $this->assertSame(0, $summary['appointments_count']);
        $this->assertEqualsWithDelta(0.0, (float) $summary['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $summary['average_per_appointment'], 0.001);
        $this->assertNotSame('', $summary['period']);
        $this->assertArrayNotHasKey('error', $response->json());
    }

    public function test_unauthenticated_request_is_rejected_with_401_envelope(): void
    {
        $previousDebug = (bool) config('app.debug');
        config(['app.debug' => false]);

        try {
            $response = $this->getJson('/api/reports/revenue');

            $response->assertStatus(401)
                ->assertJsonStructure(['message'])
                ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');

            $this->assertSame('No autenticado.', $response->json('message'));
            $this->assertNull(
                $response->json('data'),
                'No report data may leak in the unauthenticated response envelope.'
            );
        } finally {
            config(['app.debug' => $previousDebug]);
        }
    }
}
