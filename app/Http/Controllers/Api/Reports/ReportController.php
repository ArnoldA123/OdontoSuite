<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\AppointmentReportService;
use App\Services\Reports\DashboardService;
use App\Services\Reports\PatientReportService;
use App\Services\Reports\ProfessionalReportService;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\UtilizationReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    /**
     * Report types the export endpoint accepts.
     */
    private const REPORT_TYPES = [
        'dashboard',
        'appointments',
        'patients',
        'professionals',
        'revenue',
        'utilization',
    ];

    protected $dashboardService;

    protected $appointmentReportService;

    protected $patientReportService;

    protected $professionalReportService;

    protected $revenueReportService;

    protected $utilizationReportService;

    public function __construct(
        DashboardService $dashboardService,
        AppointmentReportService $appointmentReportService,
        PatientReportService $patientReportService,
        ProfessionalReportService $professionalReportService,
        RevenueReportService $revenueReportService,
        UtilizationReportService $utilizationReportService
    ) {
        $this->dashboardService = $dashboardService;
        $this->appointmentReportService = $appointmentReportService;
        $this->patientReportService = $patientReportService;
        $this->professionalReportService = $professionalReportService;
        $this->revenueReportService = $revenueReportService;
        $this->utilizationReportService = $utilizationReportService;
    }

    /**
     * Get dashboard data. The BI report always reads the applied period fresh.
     */
    public function dashboard(Request $request): JsonResponse
    {
        if ($invalid = $this->validateFilters($request)) {
            return $invalid;
        }

        try {
            $data = $this->dashboardService->getDashboardData($request->all(), false);

            return response()->json([
                'data' => $data,
                'meta' => [
                    'message' => 'Dashboard data retrieved successfully',
                ],
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'dashboard');
        }
    }

    /**
     * Get appointments report.
     */
    public function appointments(Request $request): JsonResponse
    {
        return $this->tabularReport($request, $this->appointmentReportService, 'appointments');
    }

    /**
     * Get patients report.
     */
    public function patients(Request $request): JsonResponse
    {
        return $this->tabularReport($request, $this->patientReportService, 'patients');
    }

    /**
     * Get professionals report.
     */
    public function professionals(Request $request): JsonResponse
    {
        return $this->tabularReport($request, $this->professionalReportService, 'professionals');
    }

    /**
     * Get revenue report.
     */
    public function revenue(Request $request): JsonResponse
    {
        if ($invalid = $this->validateFilters($request)) {
            return $invalid;
        }

        try {
            $data = $this->revenueReportService->getReportData($request->all());

            return response()->json([
                'data' => $data['data'],
                'columns' => $data['columns'],
                'meta' => [
                    'message' => 'Revenue report retrieved successfully',
                    'summary' => $data['summary'],
                ],
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'revenue');
        }
    }

    /**
     * Get utilization report.
     */
    public function utilization(Request $request): JsonResponse
    {
        return $this->tabularReport($request, $this->utilizationReportService, 'utilization');
    }

    /**
     * Export report.
     */
    public function export(Request $request, string $reportType)
    {
        if (! in_array($reportType, self::REPORT_TYPES, true)) {
            return response()->json([
                'message' => 'Tipo de reporte no soportado.',
                'allowed' => self::REPORT_TYPES,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'format' => 'required|in:excel,csv,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $format = $request->input('format');
            $filters = $request->only(['start_date', 'end_date', 'professional_id', 'environment_id']);
            $data = $this->getServiceByReportType($reportType)->getReportData($filters);

            $filename = $reportType.'_report_'.now()->format('Y-m-d_H-i-s');

            return match ($format) {
                'excel' => $this->exportToSpreadsheet($data, $filename),
                'csv' => $this->exportToCsv($data, $filename),
                'pdf' => $this->exportToPdf($data, $reportType, $filename),
                default => response()->json(['message' => 'Formato no soportado.'], 422),
            };
        } catch (Throwable $e) {
            return $this->serverError($e, 'export:'.$reportType);
        }
    }

    /**
     * Shared tabular report handler.
     */
    private function tabularReport(Request $request, $service, string $type): JsonResponse
    {
        if ($invalid = $this->validateFilters($request)) {
            return $invalid;
        }

        try {
            $data = $service->getReportData($request->all());

            return response()->json([
                'data' => $data['data'],
                'columns' => $data['columns'],
                'meta' => [
                    'message' => ucfirst($type).' report retrieved successfully',
                ],
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, $type);
        }
    }

    /**
     * Validate the shared filter block.
     */
    private function validateFilters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

    }

    /**
     * Generic 500: the exception text goes to the log, never to the client (D10).
     */
    private function serverError(Throwable $e, string $scope): JsonResponse
    {
        Log::error('Business Intelligence report failed', [
            'report' => $scope,
            'exception' => $e,
        ]);

        return response()->json([
            'message' => 'No se pudo generar el reporte. Intenta nuevamente.',
        ], 500);
    }

    /**
     * Get service by report type.
     */
    private function getServiceByReportType(string $reportType)
    {
        return match ($reportType) {
            'dashboard' => $this->dashboardService,
            'appointments' => $this->appointmentReportService,
            'patients' => $this->patientReportService,
            'professionals' => $this->professionalReportService,
            'revenue' => $this->revenueReportService,
            'utilization' => $this->utilizationReportService,
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
        };
    }

    /**
     * Excel export: a SpreadsheetML-compatible HTML table, the same decision
     * taken in App\Exports\CashReportExport for the cash module.
     */
    private function exportToSpreadsheet(array $data, string $filename)
    {
        $cells = '';

        foreach ($this->exportMatrix($data) as $row) {
            $cells .= '<tr>';
            foreach ($row as $cell) {
                $cells .= '<td>'.e($cell).'</td>';
            }
            $cells .= '</tr>';
        }

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns="http://www.w3.org/TR/REC-html40">'
            .'<head><meta charset="UTF-8"></head>'
            .'<body><table border="1">'.$cells.'</table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"',
        ]);
    }

    /**
     * CSV export.
     */
    private function exportToCsv(array $data, string $filename): StreamedResponse
    {
        $matrix = $this->exportMatrix($data);

        return response()->streamDownload(function () use ($matrix) {
            $handle = fopen('php://output', 'w');
            foreach ($matrix as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * PDF export.
     */
    private function exportToPdf(array $data, string $reportType, string $filename)
    {
        $headings = array_column($data['columns'], 'label');

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $this->getReportTitle($reportType),
            'headings' => $headings,
            'data' => $this->exportRows($data),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download($filename.'.pdf');
    }

    /**
     * Rows as printed: report rows plus the period summary when the service
     * provides one (revenue), so exports and the screen tell the same story.
     */
    private function exportRows(array $data): array
    {
        $rows = $data['data'];
        $summary = $data['summary'] ?? null;

        if (! is_array($summary) || empty($data['columns'])) {
            return $rows;
        }

        $keys = array_column($data['columns'], 'key');
        $summaryRow = array_fill_keys($keys, '');

        $summaryRow[$keys[0]] = 'Resumen del período';
        $summaryRow[$keys[1]] = $summary['period'] ?? '';
        $summaryRow[$keys[2]] = $summary['appointments_count'] ?? 0;
        $summaryRow[$keys[3]] = $summary['total_revenue'] ?? 0;
        $summaryRow[$keys[4]] = $summary['average_per_appointment'] ?? 0;

        $rows[] = $summaryRow;

        return $rows;
    }

    /**
     * Export as a matrix of label header plus positional values.
     *
     * @return array<int, array<int, mixed>>
     */
    private function exportMatrix(array $data): array
    {
        $matrix = [];

        if (! empty($data['columns'])) {
            $matrix[] = array_column($data['columns'], 'label');
        }

        foreach ($this->exportRows($data) as $row) {
            $matrix[] = array_values($row);
        }

        return $matrix;
    }

    /**
     * Get report title.
     */
    private function getReportTitle(string $reportType): string
    {
        $titles = [
            'dashboard' => 'Dashboard de Business Intelligence',
            'appointments' => 'Reporte de Citas',
            'patients' => 'Reporte de Pacientes',
            'professionals' => 'Reporte de Profesionales',
            'revenue' => 'Reporte de Ingresos',
            'utilization' => 'Reporte de Utilización de Ambientes',
        ];

        return $titles[$reportType] ?? 'Reporte';
    }
}
