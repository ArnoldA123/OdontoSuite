<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanResource extends JsonResource
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
            'patient_id' => $this->patient_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'estimated_duration_weeks' => $this->estimated_duration_weeks,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,
            'patient_notes' => $this->patient_notes,
            'phases' => $this->phases,
            'requires_anesthesia' => $this->requires_anesthesia,
            'is_urgent' => $this->is_urgent,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relaciones condicionales
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'first_name' => $this->patient->first_name,
                    'last_name' => $this->patient->last_name,
                    'full_name' => $this->patient->full_name,
                ];
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                        'category' => $item->category,
                        'dental_piece' => $item->dentalPiece ? [
                            'id' => $item->dentalPiece->id,
                            'fdi_number' => $item->dentalPiece->fdi_number,
                            'name' => $item->dentalPiece->name,
                        ] : null,
                    ];
                });
            }),
            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
        ];
    }
}

