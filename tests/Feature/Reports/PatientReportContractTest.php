<?php

namespace Tests\Feature\Reports;

use Carbon\Carbon;

/**
 * Patients report contract — decisions D1, D3 and D6 of
 * odd/tasks/business-intelligence-ui-redesign.md.
 *
 * Spend is collected money inside the period (not catalog price), the period
 * includes the whole last day, and a deactivated patient does not erase the
 * history of a past period.
 *
 * Money follows the net rule of odd/tasks/reports-net-refunds.md (S1-S4):
 * `total_spent` is payments minus refunds for that patient and `refunds`
 * reports the positive refunded amount.
 */
class PatientReportContractTest extends ReportContractTestCase
{
    private const START = '2024-03-04';

    private const END = '2024-03-08';

    public function test_period_spend_comes_from_payments_and_ignores_the_active_filter(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $activePatient = $this->patient();
        $inactivePatient = $this->patient(false);
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        // The whole last day counts: 18:00 on end_date is inside the period (D3).
        $this->appointment($doctor, $activePatient, $type, $chair, '2024-03-08 18:00:00', 'completed');
        $this->appointment($doctor, $inactivePatient, $type, $chair, '2024-03-05 09:00:00', 'completed');

        $this->payment($admin, $activePatient, 300.00, '2024-03-05 10:00:00');
        $this->payment($admin, $activePatient, 50.25, '2024-03-06 10:00:00');
        $this->payment($admin, $inactivePatient, 80.00, '2024-03-05 09:30:00');

        // Excluded: pending, and collected after the period.
        $this->payment($admin, $activePatient, 120.00, '2024-03-05 11:00:00', null, 'pending');
        $this->payment($admin, $activePatient, 999.00, '2024-03-20 10:00:00');
        $this->payment($admin, $inactivePatient, 500.00, '2024-03-20 10:00:00');

        // S4: refunds subtract from that patient's net spend and are reported
        // as a positive amount, attributed by `patient_id` like the payments.
        $this->payment($admin, $activePatient, 60.00, '2024-03-07 12:00:00', null, 'completed', 'refund');
        $this->payment($admin, $inactivePatient, 10.00, '2024-03-06 12:00:00', null, 'completed', 'refund');
        // Excluded: a pending refund never moved money, and neither did one
        // refunded after the period.
        $this->payment($admin, $activePatient, 30.00, '2024-03-06 13:00:00', null, 'pending', 'refund');
        $this->payment($admin, $activePatient, 40.00, '2024-03-20 13:00:00', null, 'completed', 'refund');

        $response = $this->report($admin, 'patients', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($rows->has($activePatient->id));
        $this->assertTrue($rows->has($inactivePatient->id), 'D6: a deactivated patient must still report its period history.');

        $activeRow = $rows[$activePatient->id];

        $this->assertSame(1, $activeRow['total_appointments']);
        $this->assertEqualsWithDelta(290.25, (float) $activeRow['total_spent'], 0.001, 'D1 + S4: 350.25 collected minus 60.00 refunded, never the 25000 catalog price.');
        $this->assertEqualsWithDelta(60.00, (float) $activeRow['refunds'], 0.001, 'S4: refunds are a positive amount on their own key.');
        $this->assertEqualsWithDelta(
            350.25,
            round((float) $activeRow['total_spent'] + (float) $activeRow['refunds'], 2),
            0.001,
            'S4: net + refunds is the gross collected.'
        );
        $this->assertSame('08/03/2024', $activeRow['last_appointment']);
        $this->assertSame('Activo', $activeRow['is_active']);

        $inactiveRow = $rows[$inactivePatient->id];

        $this->assertSame(1, $inactiveRow['total_appointments']);
        $this->assertEqualsWithDelta(70.00, (float) $inactiveRow['total_spent'], 0.001);
        $this->assertEqualsWithDelta(10.00, (float) $inactiveRow['refunds'], 0.001);
        $this->assertSame('Inactivo', $inactiveRow['is_active']);

        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual(0, (float) $row['refunds'], 'S4: refunds are always a positive amount.');
            $this->assertGreaterThanOrEqual(
                0,
                (float) $row['total_spent'] + (float) $row['refunds'],
                'S4: net + refunds is the gross collected and can never be negative.'
            );
        }

        $this->assertSame(
            ['id', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'age', 'total_appointments', 'total_spent', 'refunds', 'last_appointment', 'is_active', 'created_at'],
            array_column($response->json('columns'), 'key'),
            'S4: the refunded amount sits next to the net figure.'
        );
        $this->assertSame(
            'Total Neto (S/)',
            collect($response->json('columns'))->firstWhere('key', 'total_spent')['label']
        );
        $this->assertSame(
            'Devoluciones (S/)',
            collect($response->json('columns'))->firstWhere('key', 'refunds')['label']
        );
    }
}
