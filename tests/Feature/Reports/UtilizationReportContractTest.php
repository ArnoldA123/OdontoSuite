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
 *
 * Money follows the net rule of odd/tasks/reports-net-refunds.md (S1-S4):
 * `total_revenue` is payments minus refunds attributed through the chair's
 * appointments, and `refunds` reports the positive refunded amount.
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
        // S4: the refund is attributed through the same appointment key, so it
        // lands in the chair's net figure and its own refunds column.
        $this->payment($admin, $patient, 80.00, '2024-03-08 19:00:00', $completed, 'completed', 'refund');

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
        $this->assertEqualsWithDelta(220.00, (float) $activeRow['total_revenue'], 0.001, 'D1 + S4: 300.00 collected minus 80.00 refunded, never the 25000 catalog price.');
        $this->assertEqualsWithDelta(80.00, (float) $activeRow['refunds'], 0.001, 'S4: refunds are a positive amount on their own key.');
        $this->assertEqualsWithDelta(220.00, (float) $activeRow['average_per_appointment'], 0.001);

        $inactiveRow = $rows[$inactiveChair->id];

        $this->assertEqualsWithDelta(
            0.0,
            (float) $inactiveRow['total_hours_available'],
            0.001,
            'D4: an inactive chair contributes no physical availability.'
        );
        $this->assertEqualsWithDelta(0.0, (float) $inactiveRow['utilization_rate'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inactiveRow['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $inactiveRow['refunds'], 0.001);

        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual(0, (float) $row['refunds'], 'S4: refunds are always a positive amount.');
            $this->assertGreaterThanOrEqual(
                0,
                (float) $row['total_revenue'] + (float) $row['refunds'],
                'S4: net + refunds is the gross collected and can never be negative.'
            );
        }

        $this->assertSame(
            ['id', 'name', 'code', 'description', 'status', 'total_appointments', 'completed_appointments', 'cancelled_appointments', 'no_show_appointments', 'total_hours_used', 'total_hours_available', 'utilization_rate', 'total_revenue', 'refunds', 'average_per_appointment', 'is_active', 'created_at'],
            array_column($response->json('columns'), 'key'),
            'S4: the refunded amount sits next to the net figure.'
        );
        $this->assertSame(
            'Ingresos netos (S/)',
            collect($response->json('columns'))->firstWhere('key', 'total_revenue')['label']
        );
        $this->assertSame(
            'Devoluciones (S/)',
            collect($response->json('columns'))->firstWhere('key', 'refunds')['label']
        );
    }
}
