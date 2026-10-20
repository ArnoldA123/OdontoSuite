<?php

namespace Tests\Feature\Reports;

use App\Services\Reports\RevenueReportService;
use Carbon\Carbon;
use RuntimeException;

/**
 * Export contract — decisions D9 and D10 of
 * odd/tasks/business-intelligence-ui-redesign.md.
 *
 * Three honest formats (`excel` as a SpreadsheetML-compatible HTML table served
 * as `.xls`, real CSV, DomPDF), an unknown report type is a 422, and a server
 * error never returns the exception text to the browser.
 *
 * The money columns follow odd/tasks/reports-net-refunds.md (S1-S4): the export
 * publishes the net figure and the refunded amount, and the closing summary row
 * is key-driven so the refunds figure survives.
 */
class ExportContractTest extends ReportContractTestCase
{
    public function test_unknown_report_type_returns_422(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/inventory/export?format=csv');

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'allowed']);
        $this->assertContains('revenue', $response->json('allowed'));
        $this->assertArrayNotHasKey('error', $response->json());
    }

    public function test_export_formats_are_honest(): void
    {
        $admin = $this->admin();
        $url = '/api/reports/appointments/export?format=';

        $csv = $this->actingAs($admin, 'sanctum')->get($url.'csv');

        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString('.csv', (string) $csv->headers->get('content-disposition'));
        $this->assertStringContainsString('Paciente', $csv->streamedContent());

        $excel = $this->actingAs($admin, 'sanctum')->get($url.'excel');

        $excel->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $excel->headers->get('content-type'));
        $this->assertStringContainsString('.xls', (string) $excel->headers->get('content-disposition'));
        $this->assertStringContainsString('<table border="1">', $excel->getContent());
        $this->assertStringContainsString('Paciente', $excel->getContent());

        $pdf = $this->actingAs($admin, 'sanctum')->get($url.'pdf');

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_revenue_export_carries_the_refund_column_and_the_net_figure(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-03-15 09:00:00'));

        $admin = $this->admin();
        $doctor = $this->user('odontologo');
        $patient = $this->patient();
        $type = $this->appointmentType(25000.00);
        $chair = $this->chair();

        $appointment = $this->appointment($doctor, $patient, $type, $chair, '2024-03-05 10:00:00');

        $this->payment($admin, $patient, 300.50, '2024-03-05 10:30:00', $appointment);
        $this->payment($admin, $patient, 80.25, '2024-03-06 09:00:00', $appointment, 'completed', 'refund');

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/reports/revenue/export?format=csv&start_date=2024-03-04&end_date=2024-03-06');

        $response->assertOk();

        $rows = array_values(array_filter(
            array_map(
                fn (string $line) => str_getcsv($line),
                preg_split('/\r\n|\r|\n/', trim((string) $response->streamedContent()))
            ),
            fn (array $row) => $row !== [null] && $row !== ['']
        ));

        $header = array_shift($rows);
        $netColumn = array_search('Ingresos netos (S/)', $header, true);
        $refundColumn = array_search('Devoluciones (S/)', $header, true);

        $this->assertNotFalse($netColumn, 'S1: the revenue export must publish the net column.');
        $this->assertNotFalse($refundColumn, 'S1: the revenue export must publish the refunds column.');

        $professionalRow = collect($rows)->first(fn (array $row) => $row[0] === 'Por Profesional');

        $this->assertNotNull($professionalRow, 'The attributed movement must be exported.');
        $this->assertEqualsWithDelta(220.25, (float) $professionalRow[$netColumn], 0.001, 'S4: the row carries the net, never the gross 300.50.');
        $this->assertEqualsWithDelta(80.25, (float) $professionalRow[$refundColumn], 0.001);

        // S4: the closing row is key-driven, so the refunds figure survives.
        $summaryRow = end($rows);

        $this->assertStringContainsString('Resumen del período', $summaryRow[0]);
        $this->assertEqualsWithDelta(220.25, (float) $summaryRow[$netColumn], 0.001, 'The summary row carries the net, never the gross.');
        $this->assertEqualsWithDelta(80.25, (float) $summaryRow[$refundColumn], 0.001, 'The summary row carries the refunds figure.');
    }

    public function test_server_error_never_returns_the_exception_message(): void
    {
        $admin = $this->admin();

        $this->mock(RevenueReportService::class, function ($mock) {
            $mock->shouldReceive('getReportData')
                ->andThrow(new RuntimeException('SQLSTATE[HY000] secret=leaked-password'));
        });

        $json = $this->actingAs($admin, 'sanctum')->getJson('/api/reports/revenue?start_date=2024-03-04&end_date=2024-03-08');

        $json->assertStatus(500);
        $this->assertArrayNotHasKey('error', $json->json());
        $this->assertStringNotContainsString('leaked-password', $json->getContent());
        $this->assertSame('No se pudo generar el reporte. Intenta nuevamente.', $json->json('message'));

        $export = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/revenue/export?format=csv&start_date=2024-03-04&end_date=2024-03-08');

        $export->assertStatus(500);
        $this->assertArrayNotHasKey('error', $export->json());
        $this->assertStringNotContainsString('leaked-password', $export->getContent());
    }
}
