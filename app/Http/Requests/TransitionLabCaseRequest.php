<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use App\Models\LabCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F5 (#285, S2) — the only door that moves the phase.
 *
 * A later phase is accepted, the same or an earlier one is not; the service
 * owns that rule and this request only keeps the value inside the five phases
 * the issue names.
 */
class TransitionLabCaseRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phase' => ['required', Rule::in(LabCase::PHASES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phase.required' => 'La fase es obligatoria.',
            'phase.in' => 'La fase indicada no existe.',
        ];
    }
}
