<?php

namespace App\Services;

use App\Models\ClinicalAttachment;
use Illuminate\Http\UploadedFile;

/**
 * Creation path for a clinical attachment uploaded from the AI analysis
 * surface.
 *
 * The rest of the attachment lifecycle — listing, updating the description,
 * deleting the binary — is owned by `MedicalRecordService`, which is the
 * implementation the endpoints call. Keeping a second `delete()` here (#265)
 * meant a fix applied to this class could pass its tests and change nothing
 * observable, so the duplicated operations were removed instead.
 */
class ClinicalAttachmentService
{
    public function store(array $data): ClinicalAttachment
    {
        $file = $data['file'];
        $path = $file->store('clinical-attachments', 'public');

        return ClinicalAttachment::create([
            'patient_id' => $data['patient_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            // #296: the file uploaded from the AI surface belongs to the same act
            // as the analysis it feeds.
            'clinical_encounter_id' => $data['clinical_encounter_id'] ?? null,
            'clinical_evolution_id' => $data['clinical_evolution_id'] ?? null,
            'created_by' => $data['created_by'],
            'file_name' => $file->hashName(),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $this->getFileType($file->getMimeType()),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'metadata' => $this->generateMetadata($file),
            'is_private' => $data['is_private'] ?? false,
            'is_active' => true,
        ]);
    }

    protected function getFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])) {
            return 'document';
        }

        return 'other';
    }

    protected function generateMetadata(UploadedFile $file): array
    {
        $metadata = [
            'uploaded_at' => now()->toISOString(),
            'file_hash' => $file->hashName(),
        ];

        // Para imágenes, intentar obtener metadatos EXIF
        if (str_starts_with($file->getMimeType(), 'image/')) {
            try {
                $imageInfo = getimagesize($file->getPathname());
                if ($imageInfo) {
                    $metadata['dimensions'] = [
                        'width' => $imageInfo[0],
                        'height' => $imageInfo[1],
                    ];
                }
            } catch (\Exception $e) {
                // Ignorar errores de metadatos
            }
        }

        return $metadata;
    }
}
