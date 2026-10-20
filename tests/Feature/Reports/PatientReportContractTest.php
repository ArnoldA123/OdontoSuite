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
        $this->assertEqualsWithDelta(350.25, (float) $activeRow['total_spent'], 0.001, 'D1: spend is collected money, not the 25000 catalog price.');
        $this->assertSame('08/03/2024', $activeRow['last_appointment']);
        $this->assertSame('Activo', $activeRow['is_active']);

        $inactiveRow = $rows[$inactivePatient->id];

        $this->assertSame(1, $inactiveRow['total_appointments']);
        $this->assertEqualsWithDelta(80.00, (float) $inactiveRow['total_spent'], 0.001);
        $this->assertSame('Inactivo', $inactiveRow['is_active']);

        $this->assertSame(
            ['id', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'age', 'total_appointments', 'total_spent', 'last_appointment', 'is_active', 'created_at'],
            array_column($response->json('columns'), 'key')
        );
    }
}
