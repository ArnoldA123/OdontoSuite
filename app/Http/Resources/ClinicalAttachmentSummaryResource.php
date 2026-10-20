<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attachment fields the analysis surfaces render: the thumbnail source, its
 * label and the category it was classified under.
 */
class ClinicalAttachmentSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_path' => $this->file_path,
            'original_name' => $this->original_name,
            'file_type' => $this->file_type,
            'mime_type' => $this->mime_type,
            'category' => $this->category,
        ];
    }
}
