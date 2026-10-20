<?php

namespace Tests\Feature\Reports;

use Carbon\Carbon;

/**
 * Utilization report contract — decisions D1, D3, D4 and D5 of
 * odd/tasks/business-intelligence-ui-redesign.md.
 *
 * Available hours are real chair hours (working days only, active chairs only),
 * the numerator counts appointments that occupied the chair, and revenue is the
 * money collected against that chair.
 */
class UtilizationReportContractTest extends ReportContractTestCase
{
    public function test_chair_hours_occupancy_counters_and_collected_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);

        $activeChair = $this->chair();
        $inactiveChair = $this->chair(false);

        $completed = $this->appointment($doctor, $patient, $type, $activeChair, '2024-03-08 18:00:00', 'completed');
        $this->appointment($doctor, $patient, $type, $activeChair, '2024-03-05 09:00:00', 'cancelled');
        $this->appointment($doctor, $patient, $type, $activeChair, '2024-03-06 09:00:00', 'no_show', 30);

        $this->payment($admin, $patient, 300.00, '2024-03-08 18:30:00', $completed);
        $this->payment($admin, $patient, 50.00, '2024-03-07 10:00:00');

        $response = $this->report($admin, 'utilization', [
            'start_date' => '2024-03-04',
            'end_date' => '2024-03-10',
        ]);

        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($rows->has($activeChair->id));
        $this->assertTrue($rows->has($inactiveChair->id));

        $activeRow = $rows[$activeChair->id];

        $this->assertSame(3, $activeRow['total_appointments']);
        $this->assertSame(1, $activeRow['completed_appointments']);
        $this->assertSame(1, $activeRow['cancelled_appointments']);
        $this->assertSame(1, $activeRow['no_show_appointments']);
        $this->assertEqualsWithDelta(1.0, (float) $activeRow['total_hours_used'], 0.001, 'D5: cancelled and no-show appointments never occupy the chair.');
        $this->assertEqualsWithDelta(40.0, (float) $activeRow['total_hours_available'], 0.001, 'D4: five working days x 8 hours, weekends excluded.');
        $this->assertEqualsWithDelta(2.5, (float) $activeRow['utilization_rate'], 0.001);
        $this->assertEqualsWithDelta(300.00, (float) $activeRow['total_revenue'], 0.001, 'D1: collected money, not the 25000 catalog price.');
        $this->assertEqualsWithDelta(300.00, (float) $activeRow['average_per_appointment'], 0.001);

        $inactiveRow = $rows[$inactiveChair->id];

        $this->assertEqualsWithDelta(
            0.0,
            (float) $inactiveRow['total_hours_available'],
            0.001,
            'D4: an inactive chair contributes no physical availability.'
        );
        $this->assertEqualsWithDelta(0.0, (float) $inactiveRow['utilization_rate'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inactiveRow['total_revenue'], 0.001);

        $this->assertSame(
            ['id', 'name', 'code', 'description', 'status', 'total_appointments', 'completed_appointments', 'cancelled_appointments', 'no_show_appointments', 'total_hours_used', 'total_hours_available', 'utilization_rate', 'total_revenue', 'average_per_appointment', 'is_active', 'created_at'],
            array_column($response->json('columns'), 'key')
        );
    }
}
