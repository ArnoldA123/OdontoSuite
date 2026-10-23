<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * #303 — the sede filter of Business Intelligence and the "Por Sede" cut of the
 * revenue report.
 *
 * The attribution decision is L2 of odd/tasks/bi-filtro-sede-303.md: a period
 * report is attributed by the sede where the work happened, never by the sede
 * of origin of the shared patient file. Appointments answer by
 * `appointments.branch_id`, chairs by `dental_chairs.branch_id`, money by the
 * sede of the drawer that collected it, and the professional population is the
 * one with activity in the sede during the period.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class BranchScopeReportTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2024-03-04';

    private const END = '2024-03-08';

    /**
     * Every report that has to honour the sede filter.
     */
    private const REPORTS = ['dashboard', 'appointments', 'patients', 'professionals', 'revenue', 'utilization'];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2024-03-06 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(?Branch $branch, string $role = 'finanzas'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function chair(Branch $branch): DentalChair
    {
        // `branch_id` is not mass assignable on the chair model: the sede is
        // stamped by the authorization rule, so the fixture writes it directly.
        $chair = DentalChair::factory()->create();
        $chair->forceFill(['branch_id' => $branch->id])->save();

        return $chair;
    }

    private function cashSession(Branch $branch, User $collector): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $collector->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    private function appointment(
        Branch $branch,
        User $professional,
        Patient $patient,
        DentalChair $chair,
        string $status = 'completed',
    ): Appointment {
        return Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'branch_id' => $branch->id,
            'scheduled_at' => Carbon::parse('2024-03-05 10:00:00'),
            'status' => $status,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function money(array $attributes): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'status' => 'completed',
        ], $attributes));
    }

    /**
     * Two sedes with parallel activity, plus money that resolves to no sede.
     *
     * Sede Lima: 100.50 collected through its appointment, 20.25 refunded and
     * 10.00 collected at its drawer without an appointment. Sede Trujillo:
     * 500.75 collected. Money with no drawer at all (300.25) belongs to no sede.
     *
     * @return array<string, mixed>
     */
    private function scenario(): array
    {
        $a = Branch::factory()->create(['name' => 'Sede Lima']);
        $b = Branch::factory()->create(['name' => 'Sede Trujillo']);

        $professionalA = $this->user($a, 'odontologo');
        $professionalB = $this->user($b, 'odontologo');
        $collectorA = $this->user($a, 'recepcionista');
        $collectorB = $this->user($b, 'recepcionista');

        $patientA = Patient::factory()->create(['branch_id' => $a->id]);
        $patientB = Patient::factory()->create(['branch_id' => $b->id]);

        $chairA = $this->chair($a);
        $chairB = $this->chair($b);

        $sessionA = $this->cashSession($a, $collectorA);
        $sessionB = $this->cashSession($b, $collectorB);

        $appointmentA = $this->appointment($a, $professionalA, $patientA, $chairA);
        $appointmentB = $this->appointment($b, $professionalB, $patientB, $chairB);

        $this->money(['patient_id' => $patientA->id, 'appointment_id' => $appointmentA->id, 'cash_register_session_id' => $sessionA->id, 'created_by' => $collectorA->id, 'type' => 'payment', 'amount' => 100.50]);
        $this->money(['patient_id' => $patientA->id, 'appointment_id' => $appointmentA->id, 'cash_register_session_id' => $sessionA->id, 'created_by' => $collectorA->id, 'type' => 'refund', 'amount' => 20.25]);
        $this->money(['patient_id' => $patientA->id, 'appointment_id' => null, 'cash_register_session_id' => $sessionA->id, 'created_by' => $collectorA->id, 'type' => 'payment', 'amount' => 10.00]);

        $this->money(['patient_id' => $patientB->id, 'appointment_id' => $appointmentB->id, 'cash_register_session_id' => $sessionB->id, 'created_by' => $collectorB->id, 'type' => 'payment', 'amount' => 500.75]);

        $this->money(['patient_id' => $patientB->id, 'appointment_id' => null, 'cash_register_session_id' => null, 'created_by' => $collectorB->id, 'type' => 'payment', 'amount' => 300.25]);

        return [
            'branches' => ['a' => $a, 'b' => $b],
            'professionals' => ['a' => $professionalA, 'b' => $professionalB],
            'patients' => ['a' => $patientA, 'b' => $patientB],
            'chairs' => ['a' => $chairA, 'b' => $chairB],
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function report(User $user, string $type, array $query = []): TestResponse
    {
        return $this->actingAs($user, 'sanctum')
            ->getJson('/api/reports/'.$type.'?'.http_build_query($query + [
                'start_date' => self::START,
                'end_date' => self::END,
            ]));
    }

    /**
     * The Sede Lima answer every report must give, shared by the group-scope
     * filter test and the restricted-user default test.
     *
     * @param array<string, mixed> $scenario
     */
    private function assertOnlySedeLima(array $scenario, string $type, TestResponse $response): void
    {
        $response->assertOk();

        $branch = $scenario['branches']['a'];
        $patient = $scenario['patients']['a'];
        $professional = $scenario['professionals']['a'];
        $chair = $scenario['chairs']['a'];

        switch ($type) {
            case 'appointments':
                $this->assertSame(
                    [$patient->first_name.' '.$patient->last_name],
                    array_column($response->json('data'), 'patient_name')
                );

                return;

            case 'patients':
                $this->assertSame([$patient->id], array_column($response->json('data'), 'id'));
                $this->assertEqualsWithDelta(90.25, (float) $response->json('data.0.total_spent'), 0.001);
                $this->assertEqualsWithDelta(20.25, (float) $response->json('data.0.refunds'), 0.001);

                return;

            case 'professionals':
                $this->assertSame([$professional->id], array_column($response->json('data'), 'id'));
                $this->assertEqualsWithDelta(80.25, (float) $response->json('data.0.total_revenue'), 0.001);
                $this->assertEqualsWithDelta(20.25, (float) $response->json('data.0.refunds'), 0.001);

                return;

            case 'utilization':
                $this->assertSame([$chair->id], array_column($response->json('data'), 'id'));
                $this->assertEqualsWithDelta(80.25, (float) $response->json('data.0.total_revenue'), 0.001);

                return;

            case 'revenue':
                $this->assertEqualsWithDelta(90.25, (float) $response->json('meta.summary.total_revenue'), 0.001);
                $this->assertEqualsWithDelta(20.25, (float) $response->json('meta.summary.refunds'), 0.001);
                $this->assertSame(1, $response->json('meta.summary.appointments_count'));
                $this->assertSame(
                    [$branch->name],
                    array_column($this->rowsOfType($response, 'Por Sede'), 'category')
                );

                return;

            case 'dashboard':
                $this->assertSame(1, $response->json('data.totalAppointments'));
                $this->assertSame(1, $response->json('data.totalPatients'));
                $this->assertSame(1, $response->json('data.newPatients'));
                $this->assertEqualsWithDelta(90.25, (float) $response->json('data.totalRevenue'), 0.001);
                $this->assertEqualsWithDelta(20.25, (float) $response->json('data.totalRefunds'), 0.001);
                $this->assertEqualsWithDelta(40.0, (float) $response->json('data.availableHours'), 0.001);
                $this->assertSame(
                    [$professional->id],
                    array_column($response->json('data.professionalPerformance'), 'id')
                );

                return;
        }

        $this->fail("Unknown report type [{$type}].");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsOfType(TestResponse $response, string $type): array
    {
        return array_values(array_filter(
            $response->json('data'),
            static fn (array $row) => $row['type'] === $type
        ));
    }

    public function test_the_six_reports_answer_only_the_requested_sede(): void
    {
        $scenario = $this->scenario();
        $admin = $this->user(null, 'administrador');

        foreach (self::REPORTS as $type) {
            $this->assertOnlySedeLima(
                $scenario,
                $type,
                $this->report($admin, $type, ['branch_id' => $scenario['branches']['a']->id])
            );
        }
    }

    public function test_a_restricted_user_without_a_sede_filter_is_answered_their_own_sede(): void
    {
        $scenario = $this->scenario();
        $finance = $this->user($scenario['branches']['a'], 'finanzas');

        foreach (self::REPORTS as $type) {
            $this->assertOnlySedeLima($scenario, $type, $this->report($finance, $type));
        }
    }

    public function test_another_sede_is_forbidden_in_every_report_and_in_the_export(): void
    {
        $scenario = $this->scenario();
        $finance = $this->user($scenario['branches']['a'], 'finanzas');
        $foreign = $scenario['branches']['b']->id;

        foreach (self::REPORTS as $type) {
            $this->report($finance, $type, ['branch_id' => $foreign])->assertForbidden();
        }

        $this->actingAs($finance, 'sanctum')
            ->getJson('/api/reports/revenue/export?format=csv&branch_id='.$foreign)
            ->assertForbidden();
    }

    public function test_the_sede_cut_adds_up_to_the_summary_and_names_every_row(): void
    {
        $this->scenario();

        $response = $this->report($this->user(null, 'administrador'), 'revenue');
        $response->assertOk();

        $rows = collect($this->rowsOfType($response, 'Por Sede'))->keyBy('category');

        $this->assertEqualsCanonicalizing(['Sede Lima', 'Sede Trujillo', 'Sin sede'], $rows->keys()->all());

        $this->assertEqualsWithDelta(90.25, (float) $rows['Sede Lima']['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(20.25, (float) $rows['Sede Lima']['refunds'], 0.001);
        $this->assertSame(1, $rows['Sede Lima']['appointments_count']);
        $this->assertEqualsWithDelta(90.25, (float) $rows['Sede Lima']['average_per_appointment'], 0.001);

        $this->assertEqualsWithDelta(500.75, (float) $rows['Sede Trujillo']['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(300.25, (float) $rows['Sin sede']['total_revenue'], 0.001);
        $this->assertSame(0, $rows['Sin sede']['appointments_count']);

        $this->assertEqualsWithDelta(891.25, (float) $response->json('meta.summary.total_revenue'), 0.001);
        $this->assertEqualsWithDelta(20.25, (float) $response->json('meta.summary.refunds'), 0.001);

        $this->assertEqualsWithDelta(
            (float) $response->json('meta.summary.total_revenue'),
            (float) $rows->sum('total_revenue'),
            0.001,
            'Every movement lands in exactly one sede row, so the cut adds up to the summary.'
        );
        $this->assertEqualsWithDelta(
            (float) $response->json('meta.summary.refunds'),
            (float) $rows->sum('refunds'),
            0.001
        );

        // The residual row keeps absorbing the money that resolves to no
        // appointment, so the pre-existing cuts stay balanced as well.
        $residual = $this->rowsOfType($response, 'Sin cita asociada');

        $this->assertCount(1, $residual);
        $this->assertEqualsWithDelta(310.25, (float) $residual[0]['total_revenue'], 0.001);

        foreach (['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día'] as $cut) {
            $this->assertEqualsWithDelta(
                581.00,
                round(array_sum(array_column($this->rowsOfType($response, $cut), 'total_revenue')), 2),
                0.001,
                "Cut {$cut} must keep answering only the appointment-linked money."
            );
        }
    }

    public function test_the_sede_cut_adds_up_to_the_summary_with_a_sede_filter(): void
    {
        $scenario = $this->scenario();

        $response = $this->report($this->user(null, 'administrador'), 'revenue', [
            'branch_id' => $scenario['branches']['a']->id,
        ]);
        $response->assertOk();

        $rows = collect($this->rowsOfType($response, 'Por Sede'))->keyBy('category');

        $this->assertSame(['Sede Lima'], $rows->keys()->all(), 'Money with no drawer belongs to no sede, so it is filtered out.');
        $this->assertEqualsWithDelta(
            (float) $response->json('meta.summary.total_revenue'),
            (float) $rows->sum('total_revenue'),
            0.001
        );
        $this->assertEqualsWithDelta(
            (float) $response->json('meta.summary.refunds'),
            (float) $rows->sum('refunds'),
            0.001
        );
    }

    public function test_the_export_respects_the_sede_filter(): void
    {
        $scenario = $this->scenario();

        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->get('/api/reports/revenue/export?format=csv&branch_id='
                .$scenario['branches']['a']->id
                .'&start_date='.self::START.'&end_date='.self::END);

        $response->assertOk();

        $content = (string) $response->streamedContent();

        $this->assertStringContainsString('Sede Lima', $content);
        $this->assertStringNotContainsString('Sede Trujillo', $content, 'The export must not carry the other sede.');
        $this->assertStringNotContainsString('500.75', $content, 'The other sede collected amount must not be exported.');
        $this->assertStringNotContainsString('300.25', $content, 'Money without a drawer belongs to no sede.');

        $lines = array_map(
            static fn (string $line) => str_getcsv($line),
            preg_split('/\r\n|\r|\n/', trim($content))
        );
        $netColumn = array_search('Ingresos netos (S/)', $lines[0], true);

        $this->assertNotFalse($netColumn);

        $sedeRows = array_values(array_filter(
            $lines,
            static fn (array $row) => $row[0] === 'Por Sede'
        ));

        $this->assertCount(1, $sedeRows);
        $this->assertEqualsWithDelta(90.25, (float) $sedeRows[0][$netColumn], 0.001);
    }
}
