<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use Carbon\Carbon;

/**
 * Period activity summary — #447 (S1-S3) of odd/tasks/audit-u10-bi-lab-447-449.md.
 *
 * Three tabular reports publish the whole census of their population and only
 * bound their counters by the period, so the screen cannot learn whether the
 * period had activity from `rows.length`: a quiet period still answers one row
 * per patient, professional or chair. Each service sums the rows it already
 * materialized and publishes `meta.summary = {appointments, revenue}` without a
 * second query, so a zero there is a quiet period, not an empty table.
 *
 * Every assertion here is exact and independent of the population other suites
 * leave behind: the summary is compared against the rows the same payload
 * publishes, and against its own baseline read before the fixtures of the test
 * exist.
 */
class ReportPeriodActivitySummaryTest extends ReportContractTestCase
{
    private const QUIET_START = '2019-01-01';

    private const QUIET_END = '2019-01-31';

    private const START = '2024-03-04';

    private const END = '2024-03-08';

    /**
     * @return array{appointments: int, revenue: float}
     */
    private function summaryOf($response): array
    {
        return [
            'appointments' => (int) $response->json('meta.summary.appointments'),
            'revenue' => (float) $response->json('meta.summary.revenue'),
        ];
    }

    /**
     * The rule the screen depends on: the summary counts the rows published next
     * to it, so a row-level aggregate can never disagree with the summary.
     */
    private function assertSummaryAddsUpItsRows($response, string $revenueKey): void
    {
        $rows = (array) $response->json('data');

        $this->assertEqualsWithDelta(
            array_sum(array_column($rows, 'total_appointments')),
            (float) $response->json('meta.summary.appointments'),
            0.001,
            'The summary counts the appointments of the rows it publishes.'
        );
        $this->assertEqualsWithDelta(
            round(array_sum(array_column($rows, $revenueKey)), 2),
            (float) $response->json('meta.summary.revenue'),
            0.001,
            'The summary adds up the money of the rows it publishes.'
        );
    }

    public function test_patients_report_summarizes_the_period_activity_of_its_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        // The census is the population, so a quiet period still answers the row
        // of this patient: that is exactly why the empty period cannot be read
        // from the count.
        $quiet = $this->report($admin, 'patients', [
            'start_date' => self::QUIET_START,
            'end_date' => self::QUIET_END,
        ]);

        $quiet->assertOk();

        $this->assertContains(
            $patient->id,
            array_column((array) $quiet->json('data'), 'id'),
            'The patient census is published even for a quiet period.'
        );
        $this->assertSame(['appointments', 'revenue'], array_keys((array) $quiet->json('meta.summary')));
        $this->assertSame(0, $quiet->json('meta.summary.appointments'));
        $this->assertEqualsWithDelta(0.0, (float) $quiet->json('meta.summary.revenue'), 0.001);
        $this->assertSummaryAddsUpItsRows($quiet, 'total_spent');

        $baseline = $this->summaryOf($this->report($admin, 'patients', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]));

        $appointment = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00');

        $this->payment($admin, $patient, 300.00, '2024-03-05 10:30:00', $appointment);
        $this->payment($admin, $patient, 50.00, '2024-03-06 10:30:00', $appointment, 'completed', 'refund');

        $busy = $this->report($admin, 'patients', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $busy->assertOk();

        $after = $this->summaryOf($busy);

        $this->assertSame(1, $after['appointments'] - $baseline['appointments'], 'The one appointment of the test is the whole delta.');
        $this->assertEqualsWithDelta(
            250.00,
            $after['revenue'] - $baseline['revenue'],
            0.001,
            'Net of the refund, never the 25000 catalog price.'
        );
        $this->assertSummaryAddsUpItsRows($busy, 'total_spent');
    }

    public function test_professionals_report_summarizes_the_period_activity_of_its_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        $quiet = $this->report($admin, 'professionals', [
            'start_date' => self::QUIET_START,
            'end_date' => self::QUIET_END,
        ]);

        $quiet->assertOk();

        $this->assertContains(
            $doctor->id,
            array_column((array) $quiet->json('data'), 'id'),
            'The professional census is published even for a quiet period.'
        );
        $this->assertSame(['appointments', 'revenue'], array_keys((array) $quiet->json('meta.summary')));
        $this->assertSame(0, $quiet->json('meta.summary.appointments'));
        $this->assertEqualsWithDelta(0.0, (float) $quiet->json('meta.summary.revenue'), 0.001);
        $this->assertSummaryAddsUpItsRows($quiet, 'total_revenue');

        $baseline = $this->summaryOf($this->report($admin, 'professionals', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]));

        $completed = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00');
        $this->appointment($doctor, $patient, $type, $chair, '2024-03-06 11:00:00', 'cancelled');

        $this->payment($admin, $patient, 300.00, '2024-03-05 10:30:00', $completed);
        $this->payment($admin, $patient, 80.00, '2024-03-05 12:00:00', $completed, 'completed', 'refund');
        // Money without an appointment belongs to no professional row, so it
        // must not reach the summary either.
        $this->payment($admin, $patient, 50.00, '2024-03-06 12:00:00');

        $busy = $this->report($admin, 'professionals', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $busy->assertOk();

        $after = $this->summaryOf($busy);

        $this->assertSame(2, $after['appointments'] - $baseline['appointments'], 'Both appointments of the test count, the cancelled one included.');
        $this->assertEqualsWithDelta(
            220.00,
            $after['revenue'] - $baseline['revenue'],
            0.001,
            'Net of the refund and never the unlinked payment.'
        );
        $this->assertSummaryAddsUpItsRows($busy, 'total_revenue');
    }

    public function test_utilization_report_summarizes_the_period_activity_of_its_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        $quiet = $this->report($admin, 'utilization', [
            'start_date' => self::QUIET_START,
            'end_date' => self::QUIET_END,
        ]);

        $quiet->assertOk();

        $this->assertContains(
            $chair->id,
            array_column((array) $quiet->json('data'), 'id'),
            'The chair census is published even for a quiet period.'
        );
        $this->assertSame(['appointments', 'revenue'], array_keys((array) $quiet->json('meta.summary')));
        $this->assertSame(0, $quiet->json('meta.summary.appointments'));
        $this->assertEqualsWithDelta(0.0, (float) $quiet->json('meta.summary.revenue'), 0.001);
        $this->assertSummaryAddsUpItsRows($quiet, 'total_revenue');

        $baseline = $this->summaryOf($this->report($admin, 'utilization', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]));

        $completed = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00');
        $this->appointment($doctor, $patient, $type, $chair, '2024-03-06 11:00:00', 'cancelled');

        $this->payment($admin, $patient, 300.00, '2024-03-05 10:30:00', $completed);
        $this->payment($admin, $patient, 80.00, '2024-03-05 12:00:00', $completed, 'completed', 'refund');
        // Money without an appointment is attributed to no chair and stays out
        // of the summary too.
        $this->payment($admin, $patient, 50.00, '2024-03-06 12:00:00');

        $busy = $this->report($admin, 'utilization', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $busy->assertOk();

        $after = $this->summaryOf($busy);

        $this->assertSame(2, $after['appointments'] - $baseline['appointments'], 'Both appointments of the test count, the cancelled one included.');
        $this->assertEqualsWithDelta(
            220.00,
            $after['revenue'] - $baseline['revenue'],
            0.001,
            'Net of the refund and never the unlinked payment.'
        );
        $this->assertSummaryAddsUpItsRows($busy, 'total_revenue');
    }

    /**
     * The services answer a zero summary on their early return too, never a
     * missing key: a report whose population does not resolve still tells the
     * screen that the period is quiet instead of leaving it guessing.
     */
    public function test_a_report_without_population_still_publishes_a_zero_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();

        // A fresh sede has no chair and no appointment of its own, so both
        // populations resolve empty without touching the rest of the database.
        $branch = Branch::create([
            'name' => 'Sede vacía',
            'code' => 'E'.strtoupper(substr(uniqid(), -8)),
            'address' => 'Av. Test 000',
            'city' => 'Lima',
            'is_active' => true,
        ]);

        foreach (['professionals', 'utilization'] as $report) {
            $response = $this->report($admin, $report, [
                'start_date' => self::START,
                'end_date' => self::END,
                'branch_id' => $branch->id,
            ]);

            $response->assertOk();

            $this->assertSame([], $response->json('data'), "The {$report} population of an empty sede is empty.");
            $this->assertSame(
                ['appointments', 'revenue'],
                array_keys((array) $response->json('meta.summary')),
                "The {$report} report publishes its activity summary even with no population."
            );
            $this->assertSame(0, $response->json('meta.summary.appointments'));
            $this->assertEqualsWithDelta(0.0, (float) $response->json('meta.summary.revenue'), 0.001);
        }
    }

    public function test_revenue_report_keeps_its_own_summary_contract(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        $appointment = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00');

        $this->payment($admin, $patient, 300.00, '2024-03-05 10:30:00', $appointment);
        $this->payment($admin, $patient, 50.00, '2024-03-05 12:00:00', $appointment, 'completed', 'refund');

        $response = $this->report($admin, 'revenue', [
            'start_date' => self::START,
            'end_date' => '2024-03-06',
        ]);

        $response->assertOk();

        $summary = $response->json('meta.summary');

        $this->assertEqualsCanonicalizing(
            ['appointments_count', 'total_revenue', 'refunds', 'average_per_appointment', 'period'],
            array_keys((array) $summary),
            'The revenue report keeps its own summary shape.'
        );
        $this->assertSame(1, $summary['appointments_count']);
        $this->assertEqualsWithDelta(250.00, (float) $summary['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(50.00, (float) $summary['refunds'], 0.001);
        $this->assertEqualsWithDelta(250.00, (float) $summary['average_per_appointment'], 0.001);
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} - \d{2}/\d{2}/\d{4}$#', $summary['period']);
    }
}
