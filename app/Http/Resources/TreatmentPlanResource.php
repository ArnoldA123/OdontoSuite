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
            'phases' => $this->phases,
            'requires_anesthesia' => (bool) $this->requires_anesthesia,
            'is_urgent' => (bool) $this->is_urgent,
            'progress' => $this->progressMetrics(),
            'is_overdue' => $this->isOverdue(),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'document_number' => $this->patient->document_number,
                'email' => $this->patient->email,
                'phone' => $this->patient->phone,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'first_name' => $this->createdBy->first_name,
                'last_name' => $this->createdBy->last_name,
                'role' => $this->createdBy->role,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'procedure_id' => $i->procedure_id,
                'procedure_catalog_id' => $i->procedure_catalog_id,
                'dental_piece_id' => $i->dental_piece_id,
                'procedure_name' => $i->procedure_name,
                'procedure_description' => $i->procedure_description,
                'specialty' => $i->specialty,
                'quantity' => (int) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
                'total_cost' => (float) $i->total_cost,
                'phase_number' => $i->phase_number,
                'status' => $i->status,
                'estimated_duration_minutes' => $i->estimated_duration_minutes,
                'notes' => $i->notes,
                'materials_required' => $i->requiredMaterialsList(),
                'requires_anesthesia' => (bool) $i->requires_anesthesia,
                'is_optional' => (bool) $i->is_optional,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
