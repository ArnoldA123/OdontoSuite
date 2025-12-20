<?php

namespace App\Exports;

use Maatwebsite\Excel\Classes\LaravelExcelWorksheet;

class CashReportExport
{
    protected $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function handle(LaravelExcelWorksheet $sheet)
    {
        // Encabezados
        $sheet->row(1, ['Concepto', 'Detalle', 'Apertura', 'Cierre']);

        $row = 2;

        // Resumen general
        $sheet->row($row++, ['RESUMEN GENERAL', '', '', '']);
        $sheet->row($row++, ['Total Sesiones', $this->report['sessions_count'] ?? 0, '', '']);
        $sheet->row($row++, ['Total Ingresos', 'S/ ' . number_format($this->report['total_income'] ?? 0, 2), '', '']);
        $sheet->row($row++, ['Total Egresos', 'S/ ' . number_format($this->report['total_expenses'] ?? 0, 2), '', '']);
        $sheet->row($row++, ['Diferencia Total', 'S/ ' . number_format($this->report['total_difference'] ?? 0, 2), '', '']);
        $sheet->row($row++, ['', '', '', '']);

        // Detalle por sesión
        $sheet->row($row++, ['DETALLE DE SESIONES', '', '', '']);
        foreach ($this->report['sessions'] ?? [] as $session) {
            $sheet->row($row++, [
                'Sesión #' . $session['session']->id,
                $session['session']->user->name,
                'S/ ' . number_format($session['opening_amount'], 2),
                'S/ ' . number_format($session['closing_amount'], 2)
            ]);
        }

        // Aplicar estilos
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFont()->setSize(14);
    }
}
