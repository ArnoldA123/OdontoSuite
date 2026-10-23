<?php

namespace Tests\Feature\Reports;

use Carbon\Carbon;

/**
 * Revenue report contract — decisions D1, D2, D3 and D8 of
 * odd/tasks/business-intelligence-ui-redesign.md, plus the net money rule of
 * odd/tasks/reports-net-refunds.md (S1-S4).
 *
 * Money is collected payments minus completed refunds inside the period (never
 * catalog price), the refunded amount travels on its own column, each money
 * movement lands in exactly one group per cut, and `data` no longer carries the
 * retired `Resumen` row: the period totals live in `meta.summary`.
 */
class RevenueReportContractTest extends ReportContractTestCase
{
    private const START = '2024-03-04';

    private const END = '2024-03-06';

    public function test_revenue_rows_are_exclusive_groups_that_add_up_to_the_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        $appointment = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00');

        // The two payments that count: one attributed to the appointment and one
        // collected at 18:00 on the last day of the period (D3).
        $this->payment($admin, $patient, 300.00, '2024-03-05 10:30:00', $appointment);
        $this->payment($admin, $patient, 120.50, '2024-03-06 18:00:00');

        // Excluded: outside the period and not completed.
        $this->payment($admin, $patient, 999.00, '2024-03-10 10:00:00', $appointment);
        $this->payment($admin, $patient, 777.00, '2024-03-05 11:00:00', $appointment, 'pending');

        // S4: a completed refund inside the period subtracts from the same net
        // figure and is reported as a positive amount on its own column.
        $this->payment($admin, $patient, 55.00, '2024-03-05 12:00:00', $appointment, 'completed', 'refund');

        // Excluded: a pending refund never moved money.
        $this->payment($admin, $patient, 33.00, '2024-03-05 13:00:00', $appointment, 'pending', 'refund');

        $response = $this->report($admin, 'revenue', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $response->assertOk();

        $data = $response->json('data');
        $summary = $response->json('meta.summary');

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertIsArray($summary);

        // The period totals live in meta.summary, not in a duplicated data row.
        $this->assertEqualsCanonicalizing(
            ['appointments_count', 'total_revenue', 'refunds', 'average_per_appointment', 'period'],
            array_keys($summary)
        );
        $this->assertSame(1, $summary['appointments_count'], 'The denominator is completed appointments: a refund never moves it.');
        $this->assertEqualsWithDelta(365.50, (float) $summary['total_revenue'], 0.001, 'S4: 300.00 + 120.50 - 55.00.');
        $this->assertEqualsWithDelta(55.00, (float) $summary['refunds'], 0.001, 'S4: refunds are a positive amount.');
        $this->assertEqualsWithDelta(365.50, (float) $summary['average_per_appointment'], 0.001);
        $this->assertMatchesRegularExpression(
            '#^\d{2}/\d{2}/\d{4} - \d{2}/\d{2}/\d{4}$#',
            $summary['period']
        );

        $allowedTypes = ['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día', 'Por Sede', 'Sin cita asociada'];
        $types = array_values(array_unique(array_column($data, 'type')));

        foreach ($types as $rowType) {
            $this->assertContains($rowType, $allowedTypes);
        }

        $this->assertNotContains('Resumen', $types, 'D8: the retired Resumen row must not come back.');
        $this->assertNotContains('Total General', array_column($data, 'category'));

        foreach ($data as $row) {
            $this->assertArrayHasKey('category', $row);
            $this->assertArrayHasKey('total_revenue', $row);
            $this->assertArrayHasKey('refunds', $row, 'S4: every money row carries the refunded amount.');
            $this->assertGreaterThanOrEqual(0, (float) $row['refunds'], 'S4: refunds are always a positive amount.');
            $this->assertGreaterThanOrEqual(
                0,
                (float) $row['total_revenue'] + (float) $row['refunds'],
                'S4: net + refunds is the gross collected and can never be negative.'
            );
            $this->assertNotEqualsWithDelta(
                25000.00,
                (float) $row['total_revenue'],
                0.001,
                'D1: catalog price is production, never collected revenue.'
            );
        }

        $attributedNet = 300.00 - 55.00;
        $attributedRefunds = 55.00;

        foreach (['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día'] as $cut) {
            $cutRows = array_values(array_filter($data, fn (array $row) => $row['type'] === $cut));

            $this->assertNotEmpty($cutRows, "Cut {$cut} must carry the appointment-linked movement.");
            $this->assertEqualsWithDelta(
                $attributedNet,
                round(array_sum(array_column($cutRows, 'total_revenue')), 2),
                0.001,
                "Cut {$cut} must not double count the clinic total."
            );
            $this->assertEqualsWithDelta(
                $attributedRefunds,
                round(array_sum(array_column($cutRows, 'refunds')), 2),
                0.001,
                "Cut {$cut} must carry the attributed refund once."
            );
        }

        $unlinkedRows = array_values(array_filter($data, fn (array $row) => $row['type'] === 'Sin cita asociada'));

        $this->assertCount(1, $unlinkedRows, 'D2: the unlinked payment needs its own residual row.');
        $this->assertEqualsWithDelta(120.50, (float) $unlinkedRows[0]['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $unlinkedRows[0]['refunds'], 0.001);
        $this->assertSame(0, $unlinkedRows[0]['appointments_count']);

        // Every cut plus the residual equals the period summary exactly (D8, S4).
        foreach (['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día'] as $cut) {
            $cutRows = array_values(array_filter($data, fn (array $row) => $row['type'] === $cut));

            $this->assertEqualsWithDelta(
                (float) $summary['total_revenue'],
                round(array_sum(array_column($cutRows, 'total_revenue')) + (float) $unlinkedRows[0]['total_revenue'], 2),
                0.001,
                "Cut {$cut} plus the residual must add up to meta.summary.total_revenue."
            );
            $this->assertEqualsWithDelta(
                (float) $summary['refunds'],
                round(array_sum(array_column($cutRows, 'refunds')) + (float) $unlinkedRows[0]['refunds'], 2),
                0.001,
                "Cut {$cut} plus the residual must add up to meta.summary.refunds."
            );
        }

        $dayRows = array_values(array_filter($data, fn (array $row) => $row['type'] === 'Por Día'));

        $this->assertSame('2024-03-05', $dayRows[0]['category']);
        $this->assertSame(1, $dayRows[0]['appointments_count']);
        $this->assertEqualsWithDelta($attributedNet, (float) $dayRows[0]['total_revenue'], 0.001);
    }

    public function test_unlinked_refund_lands_in_the_residual_row_without_inflating_the_denominator(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        $paidAppointment = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00');
        // Refund-only appointment: it never contributes a paid appointment.
        $refundOnlyAppointment = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 15:00:00');

        $this->payment($admin, $patient, 200.00, '2024-03-05 10:30:00', $paidAppointment);
        $this->payment($admin, $patient, 80.00, '2024-03-05 16:00:00', $refundOnlyAppointment, 'completed', 'refund');
        // S4: refunds are attributed with the payment key; without an
        // appointment they belong to the residual group.
        $this->payment($admin, $patient, 70.00, '2024-03-06 10:00:00', null, 'completed', 'refund');

        $response = $this->report($admin, 'revenue', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $response->assertOk();

        $data = $response->json('data');
        $summary = $response->json('meta.summary');

        $this->assertSame(2, $summary['appointments_count'], 'The denominator counts completed appointments, refund-only ones included.');
        $this->assertEqualsWithDelta(50.00, (float) $summary['total_revenue'], 0.001, 'S4: 200.00 - 80.00 - 70.00.');
        $this->assertEqualsWithDelta(150.00, (float) $summary['refunds'], 0.001);
        $this->assertEqualsWithDelta(25.00, (float) $summary['average_per_appointment'], 0.001);

        foreach ($data as $row) {
            $this->assertGreaterThanOrEqual(0, (float) $row['refunds'], 'S4: refunds are always a positive amount.');
            $this->assertGreaterThanOrEqual(
                0,
                (float) $row['total_revenue'] + (float) $row['refunds'],
                'S4: a row may net negative, but net + refunds is the gross collected.'
            );
        }

        $unlinkedRows = array_values(array_filter($data, fn (array $row) => $row['type'] === 'Sin cita asociada'));

        $this->assertCount(1, $unlinkedRows, 'S4: an unlinked refund needs the residual row too.');
        $this->assertEqualsWithDelta(-70.00, (float) $unlinkedRows[0]['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(70.00, (float) $unlinkedRows[0]['refunds'], 0.001);
        $this->assertSame(0, $unlinkedRows[0]['appointments_count']);

        foreach (['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día'] as $cut) {
            $cutRows = array_values(array_filter($data, fn (array $row) => $row['type'] === $cut));

            $this->assertNotEmpty($cutRows, "Cut {$cut} must carry the appointment-linked movement.");
            $this->assertEqualsWithDelta(120.00, round(array_sum(array_column($cutRows, 'total_revenue')), 2), 0.001);
            $this->assertEqualsWithDelta(80.00, round(array_sum(array_column($cutRows, 'refunds')), 2), 0.001);
            $this->assertSame(
                1,
                array_sum(array_column($cutRows, 'appointments_count')),
                "Cut {$cut}: a refund-only appointment never inflates the denominator."
            );
            $this->assertEqualsWithDelta(
                (float) $summary['total_revenue'],
                round(array_sum(array_column($cutRows, 'total_revenue')) + (float) $unlinkedRows[0]['total_revenue'], 2),
                0.001,
                "Cut {$cut} plus the residual must add up to meta.summary.total_revenue."
            );
        }
    }

    public function test_revenue_columns_match_the_contract(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();

        $response = $this->report($admin, 'revenue', [
            'start_date' => self::START,
            'end_date' => self::END,
        ]);

        $response->assertOk();

        $this->assertSame(
            ['type', 'category', 'appointments_count', 'total_revenue', 'refunds', 'average_per_appointment'],
            array_column($response->json('columns'), 'key'),
            'S4: the refunded amount sits next to the net figure.'
        );
        $this->assertSame(
            ['Tipo', 'Categoría', 'Número de Citas', 'Ingresos netos (S/)', 'Devoluciones (S/)', 'Promedio por Cita (S/)'],
            array_column($response->json('columns'), 'label')
        );
        $this->assertSame([], $response->json('data'));
        $this->assertEqualsWithDelta(0.0, (float) $response->json('meta.summary.total_revenue'), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $response->json('meta.summary.refunds'), 0.001);
    }
}
