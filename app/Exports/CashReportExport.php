<?php

namespace App\Exports;

/**
 * Cash report rows builder for the cash-register export endpoint.
 *
 * Issue #53: the previous implementation mixed two generations of the
 * maatwebsite/excel library (a 3.x-style `Excel::download(new Export)` call
 * with a 1.1-style `handle(LaravelExcelWorksheet)` payload) while the project
 * installs 1.1, whose facade only exposes create/load/loadView. The result
 * was a 500 on every Excel export.
 *
 * Product decision: rewrite against capabilities already installed instead of
 * migrating to ^3.x. This class is now a framework-agnostic row builder with
 * no PHPExcel dependency; CashReportController renders the rows as an
 * Excel-compatible HTML table (.xls) or plain CSV. Upgrading to ^3.x remains
 * possible later without touching callers of toRows().
 */
class CashReportExport
{
    protected array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    /**
     * Flatten the period report into [sección, concepto, valor] rows.
     *
     * @return array<int, array<int, string>>
     */
    public function toRows(): array
    {
        $rows = [];
        $rows[] = ['Sección', 'Concepto', 'Valor'];

        $rows[] = ['Resumen', 'Total sesiones', (string) ($this->report['sessions_count'] ?? 0)];
        $rows[] = ['Resumen', 'Total apertura (S/)', $this->money($this->report['total_opening_amount'] ?? 0)];
        $rows[] = ['Resumen', 'Total cierre (S/)', $this->money($this->report['total_closing_amount'] ?? 0)];
        $rows[] = ['Resumen', 'Total esperado (S/)', $this->money($this->report['total_expected_amount'] ?? 0)];
        $rows[] = ['Resumen', 'Diferencia total (S/)', $this->money($this->report['total_difference'] ?? 0)];
        $rows[] = ['Resumen', 'Diferencia promedio (S/)', $this->money($this->report['average_difference'] ?? 0)];

        foreach ($this->report['daily_summary'] ?? [] as $day) {
            $rows[] = [
                'Diario ' . ($day['date'] ?? ''),
                'Sesiones: ' . ($day['sessions_count'] ?? 0),
                'Cierre S/ ' . $this->money($day['total_closing'] ?? 0)
                . ' / Dif. S/ ' . $this->money($day['total_difference'] ?? 0),
            ];
        }

        foreach ($this->report['summary_by_branch'] ?? [] as $branch) {
            $rows[] = [
                'Por sucursal',
                (string) ($branch['branch'] ?? 'N/A') . ' (' . ($branch['sessions_count'] ?? 0) . ' sesiones)',
                'Cierre S/ ' . $this->money($branch['total_closing'] ?? 0)
                . ' / Dif. S/ ' . $this->money($branch['total_difference'] ?? 0),
            ];
        }

        foreach ($this->report['summary_by_user'] ?? [] as $user) {
            $rows[] = [
                'Por usuario',
                (string) ($user['user'] ?? 'N/A') . ' (' . ($user['sessions_count'] ?? 0) . ' sesiones)',
                'Cierre S/ ' . $this->money($user['total_closing'] ?? 0)
                . ' / Dif. S/ ' . $this->money($user['total_difference'] ?? 0),
            ];
        }

        foreach ($this->report['payment_methods_analysis'] ?? [] as $method) {
            $rows[] = [
                'Por método de pago',
                (string) ($method['payment_method'] ?? 'N/A') . ' x' . ($method['count'] ?? 0),
                'Total S/ ' . $this->money($method['total'] ?? 0),
            ];
        }

        return $rows;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
