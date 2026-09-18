<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\CashReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\CashReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashReportController extends Controller
{
    protected $cashReportService;

    public function __construct(CashReportService $cashReportService)
    {
        $this->cashReportService = $cashReportService;
    }

    public function daily(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $branchId = $request->input('branch_id');
        $userId = $request->input('user_id');

        $report = $this->cashReportService->getDailyReport($date, $branchId, $userId);

        return response()->json(['data' => $report]);
    }

    public function period(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $branchId = $request->input('branch_id');

        $report = $this->cashReportService->getPeriodReport($startDate, $endDate, $branchId);

        return response()->json([
            'data' => $report,
            'meta' => ['message' => 'Reporte por periodo generado exitosamente'],
        ]);
    }

    /**
     * Slice 01 / T-01.1 (API-005): unified export endpoint.
     * Accepts a {format} whitelist (excel|pdf|csv) and dispatches to the
     * existing per-format helpers. Returns 400 for unsupported formats.
     *
     * Slice 04 / T-04.6: the legacy exportExcel/exportPdf methods were
     * removed because the frontend (CashReports.vue) calls the unified
     * /api/cash-register/reports/export/{format} endpoint wired in slice 01.
     *
     * Issue #53: the excel arm called Excel::download(), which does not exist
     * in the installed maatwebsite/excel 1.1 (only create/load/loadView),
     * so every Excel export 500'd. The arm now streams an Excel-compatible
     * HTML table (.xls, MIME application/vnd.ms-excel) built from
     * CashReportExport::toRows(), with no PHPExcel dependency.
     */
    public function export(Request $request, string $format)
    {
        $filters = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'branch_id' => 'nullable|integer',
            'report_type' => 'required|in:daily,period,summary',
        ]);

        $report = $this->cashReportService->getPeriodReport(
            $filters['start_date'],
            $filters['end_date'],
            $filters['branch_id'] ?? null
        );

        $filename = 'reporte-caja-' . now()->format('Y-m-d');

        return match (strtolower($format)) {
            'excel' => $this->downloadExcel(new CashReportExport($report), $filename . '.xls'),
            'csv' => $this->downloadCsv(new CashReportExport($report), $filename . '.csv'),
            'pdf' => Pdf::loadView('reports.cash-report-pdf', ['report' => $report])
                ->download($filename . '.pdf'),
            default => response()->json([
                'message' => 'Formato de exportacion no soportado. Use excel, pdf o csv.',
            ], 400),
        };
    }

    private function downloadExcel(CashReportExport $export, string $filename)
    {
        $cells = '';
        foreach ($export->toRows() as $row) {
            $cells .= '<tr>';
            foreach ($row as $cell) {
                $cells .= '<td>' . e($cell) . '</td>';
            }
            $cells .= '</tr>';
        }

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office"'
            . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            . ' xmlns="http://www.w3.org/TR/REC-html40">'
            . '<head><meta charset="UTF-8"></head>'
            . '<body><table border="1">' . $cells . '</table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function downloadCsv(CashReportExport $export, string $filename): StreamedResponse
    {
        $rows = $export->toRows();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}



























