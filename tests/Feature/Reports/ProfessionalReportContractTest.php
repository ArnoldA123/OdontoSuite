<?php

namespace Tests\Feature\Reports;

use Carbon\Carbon;

/**
 * Professionals report contract — decisions D1, D2, D3 and D7 of
 * odd/tasks/business-intelligence-ui-redesign.md.
 *
 * The population is the dashboard's clinical roles, status counters come from
 * their own aggregate (never from a collection pre-filtered to `completed`),
 * and revenue is money collected through `transactions.appointment_id`.
 *
 * Money follows the net rule of odd/tasks/reports-net-refunds.md (S1-S4):
 * `total_revenue` is payments minus refunds and `refunds` reports the positive
 * refunded amount with the same attribution key.
 */
class ProfessionalReportContractTest extends ReportContractTestCase
{
    private const START = '2024-03-04';

    private const END = '2024-03-08';

    public function test_cancelled_and_no_show_counters_and_transaction_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo', 'Dra. Uno');
        $otherDoctor = $this->user('implantologo', 'Dr. Dos');
        $assistant = $this->user('asistente', 'Asistente Tres');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        // 18:00 on the last day of the period must be counted (D3).
        $completed = $this->appointment($doctor, $patient, $type, $chair, '2024-03-08 18:00:00', 'completed');
        $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00', 'cancelled');
        $this->appointment($doctor, $patient, $type, $chair, '2024-03-06 10:00:00', 'no_show');
        $this->appointment($doctor, $patient, $type, $chair, '2024-03-20 10:00:00', 'completed');

        $otherCompleted = $this->appointment($otherDoctor, $patient, $type, $chair, '2024-03-07 11:00:00', 'completed');

        $this->payment($admin, $patient, 300.00, '2024-03-08 18:30:00', $completed);
        $this->payment($admin, $patient, 200.00, '2024-03-07 11:30:00', $otherCompleted);
        // D2: a payment without an appointment belongs to no professional.
        $this->payment($admin, $patient, 50.00, '2024-03-07 12:00:00');
        // S4: refunds subtract from the same row and are reported as a positive
        // amount, using the same appointment key as the payments.
        $this->payment($admin, $patient, 80.00, '2024-03-08 19:00:00', $completed, 'completed', 'refund');
        $this->payment($admin, $patient, 20.00, '2024-03-07 12:30:00', $otherCompleted, 'completed', 'refund');

        $response = $this->report($admin, 'professionals', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($rows->has($doctor->id));
        $this->assertTrue($rows->has($otherDoctor->id));
        $this->assertTrue($rows->has($assistant->id), 'D7: every clinical role shares the dashboard population.');
        $this->assertFalse($rows->has($admin->id), 'D7: administrative roles are not professionals.');

        $doctorRow = $rows[$doctor->id];

        $this->assertSame(3, $doctorRow['total_appointments']);
        $this->assertSame(1, $doctorRow['completed_appointments']);
        $this->assertSame(1, $doctorRow['cancelled_appointments'], 'D7: cancelled appointments must be counted, not read off a completed-only collection.');
        $this->assertSame(1, $doctorRow['no_show_appointments'], 'D7: no-show appointments must be counted, not read off a completed-only collection.');
        $this->assertEqualsWithDelta(220.00, (float) $doctorRow['total_revenue'], 0.001, 'D1 + S4: 300.00 collected minus 80.00 refunded, never the 25000 catalog price.');
        $this->assertEqualsWithDelta(80.00, (float) $doctorRow['refunds'], 0.001, 'S4: refunds are a positive amount on their own key.');
        $this->assertEqualsWithDelta(220.00, (float) $doctorRow['average_per_appointment'], 0.001);
        $this->assertEqualsWithDelta(33.33, (float) $doctorRow['completion_rate'], 0.001);

        $otherRow = $rows[$otherDoctor->id];

        $this->assertSame(1, $otherRow['total_appointments']);
        $this->assertEqualsWithDelta(180.00, (float) $otherRow['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(20.00, (float) $otherRow['refunds'], 0.001);

        $assistantRow = $rows[$assistant->id];

        $this->assertSame(0, $assistantRow['total_appointments']);
        $this->assertEqualsWithDelta(0.0, (float) $assistantRow['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $assistantRow['refunds'], 0.001);

        $this->assertEqualsWithDelta(400.00, (float) $rows->sum('total_revenue'), 0.001);
        $this->assertEqualsWithDelta(100.00, (float) $rows->sum('refunds'), 0.001);
        $this->assertSame(
            ['id', 'name', 'username', 'email', 'phone', 'specialty', 'total_appointments', 'completed_appointments', 'cancelled_appointments', 'no_show_appointments', 'total_revenue', 'refunds', 'average_per_appointment', 'completion_rate', 'is_active', 'created_at'],
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
