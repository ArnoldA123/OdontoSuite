<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\ClinicalEvolution;
use App\Models\ClinicalAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MedicalRecordService
{
    /**
     * Crear historia clínica
     */
    public function createRecord(int $patientId, array $data): MedicalRecord
    {
        return DB::transaction(function () use ($patientId, $data) {
            $data['patient_id'] = $patientId;
            $data['created_by'] = Auth::id();
            $data['record_number'] = $this->generateRecordNumber();
            $data['first_visit_date'] = $data['first_visit_date'] ?? now()->toDateString();

            $record = MedicalRecord::create($data);

            return $record->load(['patient', 'createdBy']);
        });
    }

    /**
     * Agregar evolución a la historia clínica
     */
    public function addEvolution(int $recordId, array $data): ClinicalEvolution
    {
        $record = MedicalRecord::findOrFail($recordId);

        $data['medical_record_id'] = $recordId;
        $data['patient_id'] = $record->patient_id;
        $data['created_by'] = Auth::id();
        $data['evolution_date'] = $data['evolution_date'] ?? now()->toDateString();
        $data['specialty'] = $data['specialty'] ?? Auth::user()->specialty;

        $evolution = ClinicalEvolution::create($data);

        return $evolution->load(['patient', 'appointment', 'createdBy']);
    }

    /**
     * Adjuntar archivo a la historia clínica
     */
    public function attachFile(array $data): ClinicalAttachment
    {
        $file = $data['file'];
        $originalName = $file->getClientOriginalName();
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('clinical-attachments', $fileName, 'public');

        $attachmentData = [
            'patient_id' => $data['patient_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            'clinical_evolution_id' => $data['clinical_evolution_id'] ?? null,
            'created_by' => Auth::id(),
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'file_type' => $this->getFileType($file->getMimeType()),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? '',
            'is_private' => $data['is_private'] ?? false
        ];

        $attachment = ClinicalAttachment::create($attachmentData);

        return $attachment->load(['patient', 'appointment', 'clinicalEvolution', 'createdBy']);
    }

    /**
     * Obtener historial completo del paciente
     */
    public function getPatientHistory(int $patientId)
    {
        return MedicalRecord::with([
            'patient',
            'createdBy',
            'evolutions' => function ($query) {
                $query->orderBy('evolution_date', 'desc');
            },
            'attachments' => function ($query) {
                $query->where('is_active', true);
            }
        ])
        ->where('patient_id', $patientId)
        ->where('is_active', true)
        ->orderBy('first_visit_date', 'desc')
        ->get();
    }

    /**
     * Obtener registros por especialidad
     */
    public function getRecordsBySpecialty(int $patientId, string $specialty)
    {
        return MedicalRecord::with([
            'patient',
            'createdBy',
            'evolutions' => function ($query) use ($specialty) {
                $query->where('specialty', $specialty)
                      ->orderBy('evolution_date', 'desc');
            },
            'attachments' => function ($query) {
                $query->where('is_active', true);
            }
        ])
        ->where('patient_id', $patientId)
        ->where('is_active', true)
        ->whereHas('evolutions', function ($query) use ($specialty) {
            $query->where('specialty', $specialty);
        })
        ->orderBy('first_visit_date', 'desc')
        ->get();
    }

    /**
     * Obtener evolución específica
     */
    public function getEvolution(int $evolutionId)
    {
        return ClinicalEvolution::with([
            'patient',
            'appointment',
            'medicalRecord',
            'createdBy',
            'attachments'
        ])->findOrFail($evolutionId);
    }

    /**
     * Actualizar evolución
     */
    public function updateEvolution(int $evolutionId, array $data): ClinicalEvolution
    {
        $evolution = ClinicalEvolution::findOrFail($evolutionId);
        $evolution->update($data);

        return $evolution->load(['patient', 'appointment', 'medicalRecord', 'createdBy', 'attachments']);
    }

    /**
     * Eliminar archivo adjunto
     */
    public function deleteAttachment(int $attachmentId): bool
    {
        $attachment = ClinicalAttachment::findOrFail($attachmentId);

        // Eliminar archivo del storage
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        // Marcar como inactivo en lugar de eliminar
        $attachment->update(['is_active' => false]);

        return true;
    }

    /**
     * Obtener archivos por categoría
     */
    public function getAttachmentsByCategory(int $patientId, string $category)
    {
        return ClinicalAttachment::where('patient_id', $patientId)
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Generar número de historia clínica único
     */
    private function generateRecordNumber(): string
    {
        do {
            $number = 'HC-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (MedicalRecord::where('record_number', $number)->exists());

        return $number;
    }

    /**
     * Determinar tipo de archivo por MIME type
     */
    private function getFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }

        if (in_array($mimeType, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ])) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Obtener estadísticas de la historia clínica
     */
    public function getRecordStats(int $patientId): array
    {
        $record = MedicalRecord::where('patient_id', $patientId)
            ->where('is_active', true)
            ->first();

        if (!$record) {
            return [
                'total_evolutions' => 0,
                'total_attachments' => 0,
                'last_evolution' => null,
                'specialties_involved' => []
            ];
        }

        $evolutions = $record->evolutions;
        $attachments = $record->attachments()->where('is_active', true)->get();

        return [
            'total_evolutions' => $evolutions->count(),
            'total_attachments' => $attachments->count(),
            'last_evolution' => $evolutions->sortByDesc('evolution_date')->first(),
            'specialties_involved' => $evolutions->pluck('specialty')->unique()->values()->toArray(),
            'attachment_categories' => $attachments->groupBy('category')->map->count()
        ];
    }
}
