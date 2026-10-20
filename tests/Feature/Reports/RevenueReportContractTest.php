<?php

namespace Tests\Feature\Reports;

use Carbon\Carbon;

/**
 * Revenue report contract — decisions D1, D2, D3 and D8 of
 * odd/tasks/business-intelligence-ui-redesign.md.
 *
 * Money is collected payments only (never catalog price), each payment lands in
 * exactly one group per cut, and `data` no longer carries the retired `Resumen`
 * row: the period totals live in `meta.summary`.
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

        // Excluded: outside the period, not completed, and a refund (D1).
        $this->payment($admin, $patient, 999.00, '2024-03-10 10:00:00', $appointment);
        $this->payment($admin, $patient, 777.00, '2024-03-05 11:00:00', $appointment, 'pending');
        $this->payment($admin, $patient, 55.00, '2024-03-05 12:00:00', $appointment, 'completed', 'refund');

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
            ['appointments_count', 'total_revenue', 'average_per_appointment', 'period'],
            array_keys($summary)
        );
        $this->assertSame(1, $summary['appointments_count']);
        $this->assertEqualsWithDelta(420.50, (float) $summary['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(420.50, (float) $summary['average_per_appointment'], 0.001);
        $this->assertMatchesRegularExpression(
            '#^\d{2}/\d{2}/\d{4} - \d{2}/\d{2}/\d{4}$#',
            $summary['period']
        );

        $allowedTypes = ['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día', 'Sin cita asociada'];
        $types = array_values(array_unique(array_column($data, 'type')));

        foreach ($types as $rowType) {
            $this->assertContains($rowType, $allowedTypes);
        }

        $this->assertNotContains('Resumen', $types, 'D8: the retired Resumen row must not come back.');
        $this->assertNotContains('Total General', array_column($data, 'category'));

        foreach ($data as $row) {
            $this->assertArrayHasKey('category', $row);
            $this->assertArrayHasKey('total_revenue', $row);
            $this->assertGreaterThanOrEqual(
                0,
                (float) $row['total_revenue'],
                'No revenue row may carry a negative adjustment: each cut adds up on its own.'
            );
            $this->assertNotEqualsWithDelta(
                25000.00,
                (float) $row['total_revenue'],
                0.001,
                'D1: catalog price is production, never collected revenue.'
            );
        }

        $cutTotal = 300.00;

        foreach (['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día'] as $cut) {
            $cutRows = array_values(array_filter($data, fn (array $row) => $row['type'] === $cut));

            $this->assertNotEmpty($cutRows, "Cut {$cut} must carry the appointment-linked payment.");
            $this->assertEqualsWithDelta(
                $cutTotal,
                round(array_sum(array_column($cutRows, 'total_revenue')), 2),
                0.001,
                "Cut {$cut} must not double count the clinic total."
            );
        }

        $unlinkedRows = array_values(array_filter($data, fn (array $row) => $row['type'] === 'Sin cita asociada'));

        $this->assertCount(1, $unlinkedRows, 'D2: the unlinked payment needs its own residual row.');
        $this->assertEqualsWithDelta(120.50, (float) $unlinkedRows[0]['total_revenue'], 0.001);
        $this->assertSame(0, $unlinkedRows[0]['appointments_count']);

        // Every cut plus the residual equals the period summary exactly (D8).
        foreach (['Por Tipo de Cita', 'Por Profesional', 'Por Ambiente', 'Por Día'] as $cut) {
            $cutRows = array_values(array_filter($data, fn (array $row) => $row['type'] === $cut));

            $this->assertEqualsWithDelta(
                (float) $summary['total_revenue'],
                round(array_sum(array_column($cutRows, 'total_revenue')) + (float) $unlinkedRows[0]['total_revenue'], 2),
                0.001,
                "Cut {$cut} plus the residual must add up to meta.summary.total_revenue."
            );
        }

        $dayRows = array_values(array_filter($data, fn (array $row) => $row['type'] === 'Por Día'));

        $this->assertSame('2024-03-05', $dayRows[0]['category']);
        $this->assertSame(1, $dayRows[0]['appointments_count']);
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
            ['type', 'category', 'appointments_count', 'total_revenue', 'average_per_appointment'],
            array_column($response->json('columns'), 'key')
        );
        $this->assertSame([], $response->json('data'));
        $this->assertEqualsWithDelta(0.0, (float) $response->json('meta.summary.total_revenue'), 0.001);
    }
}
