<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardService;
use App\Services\Reports\AppointmentReportService;
use App\Services\Reports\PatientReportService;
use App\Services\Reports\ProfessionalReportService;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\UtilizationReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
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
     * Get dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $this->dashboardService->getDashboardData($request->all());

            return response()->json([
                'data' => $data,
                'meta' => [
                    'message' => 'Dashboard data retrieved successfully'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appointments report
     */
    public function appointments(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $this->appointmentReportService->getReportData($request->all());

            return response()->json([
                'data' => $data['data'],
                'columns' => $data['columns'],
                'meta' => [
                    'message' => 'Appointments report retrieved successfully'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving appointments report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get patients report
     */
    public function patients(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $this->patientReportService->getReportData($request->all());

            return response()->json([
                'data' => $data['data'],
                'columns' => $data['columns'],
                'meta' => [
                    'message' => 'Patients report retrieved successfully'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving patients report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get professionals report
     */
    public function professionals(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $this->professionalReportService->getReportData($request->all());

            return response()->json([
                'data' => $data['data'],
                'columns' => $data['columns'],
                'meta' => [
                    'message' => 'Professionals report retrieved successfully'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving professionals report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue report
     */
    public function revenue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $this->revenueReportService->getReportData($request->all());

            return response()->json([
                'data' => $data['data'],
                'columns' => $data['columns'],
                'meta' => [
                    'message' => 'Revenue report retrieved successfully'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving revenue report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get utilization report
     */
    public function utilization(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $this->utilizationReportService->getReportData($request->all());

            return response()->json([
                'data' => $data['data'],
                'columns' => $data['columns'],
                'meta' => [
                    'message' => 'Utilization report retrieved successfully'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving utilization report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report
     */
    public function export(Request $request, string $reportType)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:excel,csv,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'professional_id' => 'nullable|exists:users,id',
            'environment_id' => 'nullable|exists:dental_chairs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $format = $request->input('format');
            $filters = $request->only(['start_date', 'end_date', 'professional_id', 'environment_id']);

            // Get the appropriate service based on report type
            $service = $this->getServiceByReportType($reportType);
            $data = $service->getReportData($filters);

            // Export based on format
            switch ($format) {
                case 'excel':
                    return $this->exportToExcel($data, $reportType);
                case 'csv':
                    return $this->exportToCsv($data, $reportType);
                case 'pdf':
                    return $this->exportToPdf($data, $reportType);
                default:
                    return response()->json([
                        'message' => 'Unsupported export format'
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error exporting report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service by report type
     */
    private function getServiceByReportType(string $reportType)
    {
        switch ($reportType) {
            case 'dashboard':
                return $this->dashboardService;
            case 'appointments':
                return $this->appointmentReportService;
            case 'patients':
                return $this->patientReportService;
            case 'professionals':
                return $this->professionalReportService;
            case 'revenue':
                return $this->revenueReportService;
            case 'utilization':
                return $this->utilizationReportService;
            default:
                throw new \InvalidArgumentException("Unsupported report type: {$reportType}");
        }
    }

    /**
     * Export to Excel
     */
    private function exportToExcel(array $data, string $reportType)
    {
        // For now, return a simple CSV response as Excel
        $filename = $reportType . '_report_' . now()->format('Y-m-d_H-i-s');
        $csv = $this->generateCsv($data);

        return response($csv, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xls\""
        ]);
    }

    /**
     * Export to CSV
     */
    private function exportToCsv(array $data, string $reportType)
    {
        $csv = $this->generateCsv($data);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$reportType}_report.csv\""
        ]);
    }

    /**
     * Export to PDF
     */
    private function exportToPdf(array $data, string $reportType)
    {
        $filename = $reportType . '_report_' . now()->format('Y-m-d_H-i-s');
        $headings = array_column($data['columns'], 'label');
        $exportData = $data['data'];

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $this->getReportTitle($reportType),
            'headings' => $headings,
            'data' => $exportData,
            'generatedAt' => now()->format('d/m/Y H:i')
        ]);

        return $pdf->download($filename . '.pdf');
    }

    /**
     * Generate CSV content
     */
    private function generateCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        // Write headers
        if (!empty($data['columns'])) {
            fputcsv($output, array_column($data['columns'], 'label'));
        }

        // Write data
        if (!empty($data['data'])) {
            foreach ($data['data'] as $row) {
                fputcsv($output, array_values($row));
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Get report title
     */
    private function getReportTitle(string $reportType): string
    {
        $titles = [
            'dashboard' => 'Dashboard de Business Intelligence',
            'appointments' => 'Reporte de Citas',
            'patients' => 'Reporte de Pacientes',
            'professionals' => 'Reporte de Profesionales',
            'revenue' => 'Reporte de Ingresos',
            'utilization' => 'Reporte de Utilización de Ambientes'
        ];

        return $titles[$reportType] ?? 'Reporte';
    }
}
