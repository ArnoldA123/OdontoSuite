<?php

namespace App\Services;

use App\Models\ClinicalAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClinicalAttachmentService
{
    public function store(array $data): ClinicalAttachment
    {
        $file = $data['file'];
        $path = $file->store('clinical-attachments', 'public');

        return ClinicalAttachment::create([
            'patient_id' => $data['patient_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
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
            'is_active' => true
        ]);
    }

    public function update(int $id, array $data): ClinicalAttachment
    {
        $attachment = ClinicalAttachment::findOrFail($id);

        $attachment->update([
            'description' => $data['description'] ?? $attachment->description,
            'category' => $data['category'] ?? $attachment->category,
            'is_private' => $data['is_private'] ?? $attachment->is_private,
        ]);

        return $attachment;
    }

    public function delete(int $id): bool
    {
        $attachment = ClinicalAttachment::findOrFail($id);

        // Eliminar archivo físico
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        // Soft delete
        $attachment->update(['is_active' => false]);

        return true;
    }

    public function getAttachmentUrl(ClinicalAttachment $attachment): string
    {
        return Storage::url($attachment->file_path);
    }

    protected function getFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
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
                        'height' => $imageInfo[1]
                    ];
                }
            } catch (\Exception $e) {
                // Ignorar errores de metadatos
            }
        }

        return $metadata;
    }
}
