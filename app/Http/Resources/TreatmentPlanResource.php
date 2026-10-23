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
            'phase_summary' => $this->whenLoaded('items', fn () => $this->phaseSummary()),
            'requires_anesthesia' => (bool) $this->requires_anesthesia,
            'is_urgent' => (bool) $this->is_urgent,
            'progress' => $this->progressMetrics(),
            'current_version' => $this->whenLoaded('currentVersion', fn () => $this->currentVersion ? [
                'id' => $this->currentVersion->id,
                'version_number' => $this->currentVersion->version_number,
                'status' => $this->currentVersion->status,
                'accepted_at' => $this->currentVersion->accepted_at?->toIso8601String(),
            ] : null),
            'active_version' => $this->whenLoaded('activeVersion', fn () => $this->activeVersion ? [
                'id' => $this->activeVersion->id,
                'version_number' => $this->activeVersion->version_number,
                'status' => $this->activeVersion->status,
                'accepted_at' => $this->activeVersion->accepted_at?->toIso8601String(),
            ] : null),
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
                'tooth_surface_id' => $i->tooth_surface_id,
                'site_code' => $i->site_code,
                'procedure_name' => $i->procedure_name,
                'procedure_description' => $i->procedure_description,
                'specialty' => $i->specialty,
                'quantity' => (int) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
                'total_cost' => (float) $i->total_cost,
                'phase_number' => $i->phase_number,
                'sequence' => $i->sequence,
                'priority' => $i->priority,
                'status' => $i->status,
                'estimated_duration_minutes' => $i->estimated_duration_minutes,
                'notes' => $i->notes,
                'materials_required' => $i->requiredMaterialsList(),
                'requires_anesthesia' => (bool) $i->requires_anesthesia,
                'is_optional' => (bool) $i->is_optional,
                'rejected_at' => $i->rejected_at?->toIso8601String(),
                'rejection_reason' => $i->rejection_reason,
                'alternatives' => $i->relationLoaded('alternatives')
                    ? $i->alternatives->map(fn ($alternative) => [
                        'id' => $alternative->id,
                        'option_code' => $alternative->option_code,
                        'procedure_name' => $alternative->procedure_name,
                        'dental_piece_id' => $alternative->dental_piece_id,
                        'status' => $alternative->status,
                        'unit_cost' => (float) $alternative->unit_cost,
                        'total_cost' => (float) $alternative->total_cost,
                        'rejected_at' => $alternative->rejected_at?->toIso8601String(),
                        'rejection_reason' => $alternative->rejection_reason,
                    ])->values()->all()
                    : [],
                // F4 (#284): execution is a consequence of the acts that
                // consumed the line, not of a status field.
                'executed_quantity' => $i->relationLoaded('consumptions')
                    ? round((float) $i->consumptions->sum('quantity'), 2)
                    : $i->executedQuantity(),
                'pending_quantity' => $i->relationLoaded('consumptions')
                    ? round(max(0, (float) $i->quantity - (float) $i->consumptions->sum('quantity')), 2)
                    : $i->pendingQuantity(),
                'session_count' => $i->relationLoaded('consumptions')
                    ? $i->consumptions->count()
                    : $i->consumptions()->count(),
                'consumptions' => $i->relationLoaded('consumptions')
                    ? $i->consumptions->map(fn ($consumption) => [
                        'id' => $consumption->id,
                        'clinical_encounter_id' => $consumption->clinical_encounter_id,
                        'quantity' => (float) $consumption->quantity,
                        'consumed_at' => $consumption->consumed_at?->toIso8601String(),
                    ])->values()->all()
                    : [],
            ])),
            // #296: the reviewed analyses this plan incorporates. A citation
            // exists only for a reviewed analysis, so the list is the proof.
            'cited_analyses' => $this->whenLoaded('citedAnalyses', fn () => $this->citedAnalyses->map(fn ($analysis) => [
                'id' => $analysis->id,
                'review_decision' => $analysis->review_decision,
                'reviewed_at' => $analysis->reviewed_at?->toIso8601String(),
                'confidence_score' => $analysis->confidence_score,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
