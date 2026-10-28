<?php

namespace App\Http\Resources;

use App\Models\AiImageAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of an AI analysis.
 *
 * `prompt_sent` and `raw_response` are provider-side artifacts: audit material,
 * not a contract, and they never leave the API. Relations are summarized so a
 * clinical list does not ship the whole `users` record alongside each row.
 */
class AiImageAnalysisResource extends JsonResource
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
            'clinical_attachment_id' => $this->clinical_attachment_id,
            'clinical_encounter_id' => $this->clinical_encounter_id,
            'status' => $this->status,
            'reviewed' => (bool) $this->reviewed,
            'confidence_score' => $this->confidence_score,
            'findings' => $this->findings,
            'recommendations' => $this->recommendations,
            'model_used' => $this->model_used,
            'tokens_used' => $this->tokens_used,
            'estimated_cost' => $this->estimated_cost !== null ? (float) $this->estimated_cost : null,
            'review_decision' => $this->review_decision,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'voided' => $this->isVoided(),
            'voided_at' => $this->voided_at?->toISOString(),
            'void_reason' => $this->void_reason,
            'failure_reason' => $this->failure_reason,
            'assistive' => (bool) $this->isAssistive(),
            'assistive_notice' => AiImageAnalysis::ASSISTIVE_NOTICE,
            'notice_acknowledged_at' => $this->notice_acknowledged_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'patient' => $this->whenLoaded('patient', fn () => new PatientSummaryResource($this->patient)),
            'clinical_attachment' => $this->whenLoaded('clinicalAttachment', fn () => new ClinicalAttachmentSummaryResource($this->clinicalAttachment)),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => new UserSummaryResource($this->requestedBy)),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy ? new UserSummaryResource($this->reviewedBy) : null),
            'voided_by' => $this->whenLoaded('voidedBy', fn () => $this->voidedBy ? new UserSummaryResource($this->voidedBy) : null),
            'notice_acknowledged_by' => $this->whenLoaded('noticeAcknowledgedBy', fn () => $this->noticeAcknowledgedBy ? new UserSummaryResource($this->noticeAcknowledgedBy) : null),
        ];
    }
}
