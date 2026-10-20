<?php

namespace Tests\Feature\Reports;

use App\Services\Reports\RevenueReportService;
use RuntimeException;

/**
 * Export contract — decisions D9 and D10 of
 * odd/tasks/business-intelligence-ui-redesign.md.
 *
 * Three honest formats (`excel` as a SpreadsheetML-compatible HTML table served
 * as `.xls`, real CSV, DomPDF), an unknown report type is a 422, and a server
 * error never returns the exception text to the browser.
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
