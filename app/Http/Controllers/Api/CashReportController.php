<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\CashReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\CashReportExport;

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
            'excel' => Excel::download(new CashReportExport($report), $filename . '.xlsx'),
            'pdf' => Pdf::loadView('reports.cash-report-pdf', ['report' => $report])
                ->download($filename . '.pdf'),
            default => response()->json([
                'message' => 'Formato de exportacion no soportado. Use excel, pdf o csv.',
            ], 400),
        };
    }

    public function exportExcel(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'branch_id' => 'nullable|integer',
            'report_type' => 'required|in:daily,period,summary'
        ]);

        $report = $this->cashReportService->getPeriodReport(
            $filters['start_date'],
            $filters['end_date'],
            $filters['branch_id'] ?? null
        );

        return Excel::download(
            new CashReportExport($report),
            'reporte-caja-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'branch_id' => 'nullable|integer',
            'report_type' => 'required|in:daily,period,summary'
        ]);

        $report = $this->cashReportService->getPeriodReport(
            $filters['start_date'],
            $filters['end_date'],
            $filters['branch_id'] ?? null
        );

        $pdf = Pdf::loadView('reports.cash-report-pdf', ['report' => $report]);

        return $pdf->download('reporte-caja-' . now()->format('Y-m-d') . '.pdf');
    }
}



























