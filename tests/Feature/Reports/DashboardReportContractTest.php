<?php

namespace Tests\Feature\Reports;

use Carbon\Carbon;

/**
 * Dashboard report contract — decisions D1 to D6 and the Contract v2 shape of
 * odd/tasks/business-intelligence-ui-redesign.md.
 *
 * Revenue is attributed exactly once, availability is physical (active chairs
 * times working days, never capped), occupancy excludes cancelled and no-show
 * appointments, and patients mean patients seen in the period.
 */
class DashboardReportContractTest extends ReportContractTestCase
{
    private const START = '2024-03-04';

    private const END = '2024-03-08';

    public function test_contract_v2_metrics_and_single_revenue_attribution(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-06 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo', 'Dra. Uno');
        $otherDoctor = $this->user('implantologo', 'Dr. Dos');
        $this->user('asistente', 'Asistente Tres');

        $chairOne = $this->chair();
        $chairTwo = $this->chair();
        $this->chair(false);

        $type = $this->appointmentType(25000.00);

        $patientOne = $this->patient();
        $patientTwo = $this->patient(false);
        $patientThree = $this->patient();
        $patientFour = $this->patient();

        $firstAppointment = $this->appointment($doctor, $patientOne, $type, $chairOne, '2024-03-04 09:00:00', 'completed');
        $this->appointment($doctor, $patientTwo, $type, $chairOne, '2024-03-05 10:00:00', 'cancelled');
        $this->appointment($doctor, $patientTwo, $type, $chairOne, '2024-03-08 18:00:00', 'no_show', 30);
        $thirdAppointment = $this->appointment($otherDoctor, $patientThree, $type, $chairTwo, '2024-03-06 11:00:00', 'scheduled', 30);

        // Outside the period on purpose: it must not affect any period metric.
        $this->appointment($otherDoctor, $patientFour, $type, $chairOne, '2024-03-20 10:00:00', 'completed');

        $this->payment($admin, $patientOne, 300.00, '2024-03-05 10:00:00', $firstAppointment);
        $this->payment($admin, $patientThree, 200.00, '2024-03-06 12:00:00', $thirdAppointment);
        // D2: counted in the clinic total, attributed to no professional.
        $this->payment($admin, $patientOne, 50.00, '2024-03-07 10:00:00');
        $this->payment($admin, $patientOne, 999.00, '2024-03-20 10:00:00');

        $response = $this->report($admin, 'dashboard', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $response->assertOk();

        $data = $response->json('data');

        $this->assertEqualsCanonicalizing(
            ['totalAppointments', 'totalPatients', 'newPatients', 'totalRevenue', 'occupancyRate', 'usedHours', 'availableHours', 'appointmentsByDay', 'revenueByMonth', 'professionalPerformance'],
            array_keys($data),
            'Contract v2: the dashboard payload must expose exactly these keys.'
        );

        $this->assertSame(4, $data['totalAppointments']);
        $this->assertSame(3, $data['totalPatients'], 'D6: patients seen in the period, including a deactivated patient.');
        $this->assertSame(4, $data['newPatients'], 'D6: patients registered in the period.');
        $this->assertEqualsWithDelta(550.00, (float) $data['totalRevenue'], 0.001, 'D1: collected payments only, not the 25000 catalog price.');
        $this->assertEqualsWithDelta(1.5, (float) $data['usedHours'], 0.001, 'D5: cancelled and no-show appointment hours do not count.');
        $this->assertEqualsWithDelta(80.0, (float) $data['availableHours'], 0.001, 'D4: 2 active chairs x 5 working days x 8 hours.');
        $this->assertEqualsWithDelta(1.88, (float) $data['occupancyRate'], 0.001);

        $this->assertSame(4, array_sum(array_column($data['appointmentsByDay'], 'count')));
        $this->assertContains('2024-03-08', array_column($data['appointmentsByDay'], 'date'), 'D3: the last day of the period is inclusive (18:00 counts).');

        $this->assertCount(1, $data['revenueByMonth']);
        $this->assertSame('Mar 2024', $data['revenueByMonth'][0]['month']);
        $this->assertEqualsWithDelta(550.00, (float) $data['revenueByMonth'][0]['revenue'], 0.001);

        $performance = collect($data['professionalPerformance'])->keyBy('id');

        $this->assertTrue($performance->has($doctor->id));
        $this->assertTrue($performance->has($otherDoctor->id));

        foreach ($performance as $row) {
            $this->assertEqualsCanonicalizing(
                ['id', 'name', 'appointments', 'revenue', 'averagePerAppointment'],
                array_keys($row)
            );
        }

        $doctorRow = $performance[$doctor->id];

        $this->assertSame(3, $doctorRow['appointments']);
        $this->assertEqualsWithDelta(300.00, (float) $doctorRow['revenue'], 0.001);
        $this->assertEqualsWithDelta(100.00, (float) $doctorRow['averagePerAppointment'], 0.001);

        $otherRow = $performance[$otherDoctor->id];

        $this->assertSame(1, $otherRow['appointments']);
        $this->assertEqualsWithDelta(200.00, (float) $otherRow['revenue'], 0.001);

        $professionalRevenue = (float) collect($data['professionalPerformance'])->sum('revenue');

        $this->assertEqualsWithDelta(500.00, $professionalRevenue, 0.001);
        $this->assertLessThanOrEqual(
            (float) $data['totalRevenue'],
            $professionalRevenue,
            'D2: sum(professionalPerformance.revenue) can never exceed totalRevenue.'
        );
        $this->assertNotEqualsWithDelta(
            (float) $data['totalRevenue'],
            (float) $doctorRow['revenue'],
            0.001,
            'D2: the appointment-less payment is never redistributed across professionals.'
        );
    }

    public function test_availability_counts_active_chairs_on_working_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $firstChair = $this->chair();
        $this->chair();
        $this->chair(false);

        // Monday to Sunday: five working days, no min(days, 30) cap involved.
        $query = [
            'start_date' => '2024-03-04',
            'end_date' => '2024-03-10',
        ];

        $response = $this->report($admin, 'dashboard', $query);
        $response->assertOk();

        $this->assertEqualsWithDelta(80.0, (float) $response->json('data.availableHours'), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.usedHours'), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.occupancyRate'), 0.001);

        $filtered = $this->report($admin, 'dashboard', $query + ['environment_id' => $firstChair->id]);
        $filtered->assertOk();

        $this->assertEqualsWithDelta(40.0, (float) $filtered->json('data.availableHours'), 0.001);
    }

    public function test_overbooking_is_reported_above_one_hundred_percent(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType();
        $chair = $this->chair();

        for ($hour = 7; $hour < 16; $hour++) {
            $this->appointment(
                $doctor,
                $patient,
                $type,
                $chair,
                Carbon::parse('2024-03-04')->setTime($hour, 0),
                'completed'
            );
        }

        $response = $this->report($admin, 'dashboard', [
            'start_date' => '2024-03-04',
            'end_date' => '2024-03-04',
        ]);

        $response->assertOk();

        $this->assertEqualsWithDelta(9.0, (float) $response->json('data.usedHours'), 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $response->json('data.availableHours'), 0.001);
        $this->assertEqualsWithDelta(
            112.5,
            (float) $response->json('data.occupancyRate'),
            0.001,
            'D4: the occupancy rate is not silently capped.'
        );
    }
}
