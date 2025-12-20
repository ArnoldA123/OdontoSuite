<?php

namespace App\Services;

use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class PatientExportService
{
    /**
     * Export patient file to PDF.
     */
    public function exportToPdf(int $patientId): string
    {
        try {
            $patient = $this->loadPatientData($patientId);

            if (!$patient) {
                throw new \Exception("Paciente no encontrado con ID: {$patientId}");
            }

            $pdf = Pdf::loadView('exports.patient-file', [
                'patient' => $patient,
                'generatedAt' => now()->format('d/m/Y H:i:s'),
            ]);

            return $pdf->output();
        } catch (\Exception $e) {
            Log::error('Error generating PDF: ' . $e->getMessage(), [
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Export patient file to ZIP (PDF + attachments).
     */
    public function exportToZip(int $patientId): string
    {
        $patient = $this->loadPatientData($patientId);

        // Create temporary directory
        $baseTempDir = storage_path('app/temp/exports');
        if (!is_dir($baseTempDir)) {
            mkdir($baseTempDir, 0755, true);
        }
        $tempDir = $baseTempDir . '/' . uniqid('patient_export_', true);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // Generate PDF
            $pdfContent = $this->exportToPdf($patientId);
            $pdfPath = $tempDir . '/ficha_paciente_' . $patient->id . '.pdf';
            file_put_contents($pdfPath, $pdfContent);

            // Copy attachments
            $attachmentsDir = $tempDir . '/adjuntos';
            if (!is_dir($attachmentsDir)) {
                mkdir($attachmentsDir, 0755, true);
            }

            $attachmentCount = 0;
            // Get attachments directly from patient (they have patient_id)
            $attachments = \App\Models\ClinicalAttachment::where('patient_id', $patient->id)
                ->where('is_active', true)
                ->get();
            
            foreach ($attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    $safeName = $attachmentCount . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $attachment->original_name);
                    $sourcePath = Storage::disk('public')->path($attachment->file_path);
                    $destPath = $attachmentsDir . '/' . $safeName;
                    
                    if (file_exists($sourcePath)) {
                        copy($sourcePath, $destPath);
                        $attachmentCount++;
                    }
                }
            }

            // Create ZIP file
            $zipPath = storage_path('app/temp/exports/patient_' . $patient->id . '_' . now()->format('Y-m-d_His') . '.zip');
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // Add PDF
                $zip->addFile($pdfPath, 'ficha_paciente.pdf');
                
                // Add attachments
                if (is_dir($attachmentsDir)) {
                    $files = glob($attachmentsDir . '/*');
                    foreach ($files as $file) {
                        $zip->addFile($file, 'adjuntos/' . basename($file));
                    }
                }
                
                $zip->close();
            }

            // Clean up temporary directory
            $this->deleteDirectory($tempDir);

            // Read ZIP content
            $zipContent = file_get_contents($zipPath);
            
            // Delete ZIP file
            unlink($zipPath);

            return $zipContent;
        } catch (\Exception $e) {
            // Clean up on error
            if (is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
            throw $e;
        }
    }

    /**
     * Load all patient data for export.
     */
    private function loadPatientData(int $patientId): Patient
    {
        return Patient::with([
            'appointments' => function ($query) {
                $query->with([
                    'appointmentType:id,name,default_duration_minutes,price',
                    'user:id,name,specialty',
                    'dentalChair:id,name,code'
                ])->orderBy('scheduled_at', 'desc');
            },
            'waitingLists' => function ($query) {
                $query->with([
                    'appointmentType:id,name',
                    'preferredUser:id,name'
                ])->orderBy('created_at', 'desc');
            },
            'treatmentPlans' => function ($query) {
                $query->with([
                    'items.dentalPiece',
                    'createdBy:id,name'
                ])->orderBy('created_at', 'desc');
            },
            'quotations' => function ($query) {
                $query->with([
                    'items',
                    'createdBy:id,name'
                ])->orderBy('created_at', 'desc');
            },
            'medicalRecords' => function ($query) {
                $query->with([
                    'evolutions' => function ($q) {
                        $q->orderBy('evolution_date', 'desc');
                    },
                    'createdBy:id,name'
                ])->orderBy('first_visit_date', 'desc');
            },
            'endodonticsRecords' => function ($query) {
                $query->with('createdBy:id,name')->orderBy('created_at', 'desc');
            },
            'implantologyRecords' => function ($query) {
                $query->with('createdBy:id,name')->orderBy('created_at', 'desc');
            },
            'orthodonticsRecords' => function ($query) {
                $query->with('createdBy:id,name')->orderBy('created_at', 'desc');
            },
            'rehabilitationRecords' => function ($query) {
                $query->with('createdBy:id,name')->orderBy('created_at', 'desc');
            },
            'oralSurgeryRecords' => function ($query) {
                $query->with('createdBy:id,name')->orderBy('created_at', 'desc');
            },
            'odontograms' => function ($query) {
                $query->with([
                    'records' => function ($q) {
                        $q->with([
                            'dentalPiece:id,fdi_number,name,type',
                            'toothSurface:id,surface_code,surface_name',
                            'appointment:id,scheduled_at',
                            'createdBy:id,name'
                        ]);
                    },
                    'createdBy:id,name'
                ])->orderBy('created_at', 'desc');
            },
            'auditLogs' => function ($query) {
                $query->with('user:id,name,email')
                      ->orderBy('created_at', 'desc')
                      ->limit(100);
            }
        ])->findOrFail($patientId);
    }

    /**
     * Delete directory recursively.
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}

