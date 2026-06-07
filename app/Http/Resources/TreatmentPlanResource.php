<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'origin_appointment_id' => $this->origin_appointment_id,
            'plan_number' => $this->plan_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'total_cost' => (float) $this->total_cost,
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'final_cost' => (float) $this->final_cost,
            'estimated_duration_weeks' => $this->estimated_duration_weeks,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'notes' => $this->notes,
            'patient_notes' => $this->patient_notes,
            'progress' => $this->progressMetrics(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'procedure_name' => $i->procedure_name,
                'dental_piece_id' => $i->dental_piece_id,
                'specialty' => $i->specialty,
                'unit_cost' => (float) $i->unit_cost,
                'total_cost' => (float) $i->total_cost,
                'phase_number' => $i->phase_number,
                'status' => $i->status,
                'materials_required' => $i->requiredMaterialsList(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
