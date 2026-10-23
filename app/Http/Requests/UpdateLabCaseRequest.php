<?php

namespace App\Http\Requests;

use App\Models\LabCase;
use App\Services\LabCaseService;

/**
 * F5 (#285, S1) — the laboratory desk completes or corrects a work order.
 *
 * Every field is optional, but a field that arrives is validated exactly as it
 * is on creation. The phase is not editable here, and `promised_at` is still
 * compared against the case's own sent date when the payload only moves one of
 * the two dates.
 */
class UpdateLabCaseRequest extends StoreLabCaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'required', 'exists:patients,id'],
            'professional_id' => ['sometimes', 'nullable', $this->roleRule(LabCaseService::PROFESSIONAL_ROLES)],
            'technician_id' => ['sometimes', 'nullable', $this->roleRule(LabCaseService::TECHNICIAN_ROLES)],
            'dental_piece_id' => ['sometimes', 'nullable', 'exists:dental_pieces,id'],
            'work' => ['sometimes', 'required', 'string', 'max:255'],
            'material' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sent_at' => ['sometimes', 'required', 'date'],
            'promised_at' => ['sometimes', 'required', 'date', 'after_or_equal:'.$this->sentAtReference()],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'branch_id' => ['sometimes', 'nullable', 'exists:branches,id'],
        ];
    }

    /**
     * The date `promised_at` must not precede: the one in the payload, or the
     * one already stored on the case being edited.
     */
    private function sentAtReference(): string
    {
        if ($this->filled('sent_at')) {
            return (string) $this->input('sent_at');
        }

        $case = $this->route('labCase');

        return $case instanceof LabCase && $case->sent_at !== null
            ? $case->sent_at->toDateString()
            : now()->toDateString();
    }
}
