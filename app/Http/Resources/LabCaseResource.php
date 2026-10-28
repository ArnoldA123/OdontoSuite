<?php

namespace App\Http\Resources;

use App\Models\LabCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #449 — the prosthetic work order as the desk reads it.
 *
 * The payload keeps the shape the SPA and the audit instrument already read and
 * adds one derived capability: whether this actor may edit this case. The rule
 * is not repeated here: the policy decides ownership and this resource only
 * combines it with the phase, because a placed case is closed work.
 */
class LabCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'branch_id' => $this->branch_id,
            'professional_id' => $this->professional_id,
            'technician_id' => $this->technician_id,
            'dental_piece_id' => $this->dental_piece_id,
            'work' => $this->work,
            'material' => $this->material,
            'sent_at' => $this->sent_at?->toDateString(),
            'promised_at' => $this->promised_at?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'delivered_at' => $this->delivered_at?->toISOString(),
            'placed_at' => $this->placed_at?->toISOString(),
            'can_edit' => $this->canEdit($request),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
            ]),
            'professional' => $this->whenLoaded('professional', fn () => $this->professional ? [
                'id' => $this->professional->id,
                'name' => $this->professional->name,
            ] : null),
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? [
                'id' => $this->technician->id,
                'name' => $this->technician->name,
            ] : null),
            'dental_piece' => $this->whenLoaded('dentalPiece', fn () => $this->dentalPiece ? [
                'id' => $this->dentalPiece->id,
                'fdi_number' => $this->dentalPiece->fdi_number,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
        ];
    }

    /**
     * A placed case is finished work: nothing is left to correct.
     */
    private function canEdit(Request $request): bool
    {
        if ($this->status === LabCase::PHASE_PLACED) {
            return false;
        }

        return $request->user()?->can('update', $this->resource) ?? false;
    }
}
