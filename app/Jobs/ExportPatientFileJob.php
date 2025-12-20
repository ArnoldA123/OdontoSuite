<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Services\PatientExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportPatientFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $patientId;
    protected string $format; // 'pdf' or 'zip'
    protected ?int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $patientId, string $format = 'pdf', ?int $userId = null)
    {
        $this->patientId = $patientId;
        $this->format = $format;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(PatientExportService $exportService): void
    {
        try {
            Log::info('Starting patient file export', [
                'patient_id' => $this->patientId,
                'format' => $this->format,
                'user_id' => $this->userId,
            ]);

            $patient = Patient::findOrFail($this->patientId);

            if ($this->format === 'zip') {
                $filePath = $exportService->exportToZip($this->patientId);
            } else {
                $filePath = $exportService->exportToPdf($this->patientId);
            }

            // Store the file path in cache or database for retrieval
            $cacheKey = "patient_export_{$this->patientId}_{$this->format}_" . now()->timestamp;
            cache()->put($cacheKey, $filePath, now()->addHours(1));

            Log::info('Patient file export completed', [
                'patient_id' => $this->patientId,
                'format' => $this->format,
                'file_path' => $filePath,
            ]);

            // TODO: Notify user via WebSocket or email that export is ready
        } catch (\Exception $e) {
            Log::error('Error in ExportPatientFileJob', [
                'patient_id' => $this->patientId,
                'format' => $this->format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExportPatientFileJob failed', [
            'patient_id' => $this->patientId,
            'format' => $this->format,
            'error' => $exception->getMessage(),
        ]);
    }
}

