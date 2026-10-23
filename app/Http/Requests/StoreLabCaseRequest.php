<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use App\Services\LabCaseService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F5 (#285, S1) — a prosthetic case is opened with the patient, the piece, the
 * job, the material, the two dates and the assigned technician.
 *
 * The phase is never part of the payload: it moves only through the phase door.
 */
class StoreLabCaseRequest extends FormRequest
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
            'patient_id' => ['required', 'exists:patients,id'],
            'professional_id' => ['sometimes', 'nullable', $this->roleRule(LabCaseService::PROFESSIONAL_ROLES)],
            'technician_id' => ['nullable', $this->roleRule(LabCaseService::TECHNICIAN_ROLES)],
            'dental_piece_id' => ['nullable', 'exists:dental_pieces,id'],
            'work' => ['required', 'string', 'max:255'],
            'material' => ['nullable', 'string', 'max:255'],
            'sent_at' => ['required', 'date'],
            'promised_at' => ['required', 'date', 'after_or_equal:sent_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'El paciente es obligatorio.',
            'patient_id.exists' => 'El paciente seleccionado no existe.',
            'professional_id.exists' => 'El profesional debe ser un odontólogo o implantólogo activo.',
            'technician_id.exists' => 'El técnico asignado debe ser un técnico dental activo.',
            'dental_piece_id.exists' => 'La pieza dental seleccionada no existe.',
            'work.required' => 'El trabajo a realizar es obligatorio.',
            'work.string' => 'El trabajo debe ser texto.',
            'work.max' => 'El trabajo no puede exceder 255 caracteres.',
            'material.string' => 'El material debe ser texto.',
            'material.max' => 'El material no puede exceder 255 caracteres.',
            'sent_at.required' => 'La fecha de envío es obligatoria.',
            'sent_at.date' => 'La fecha de envío debe ser una fecha válida.',
            'promised_at.required' => 'La fecha prometida es obligatoria.',
            'promised_at.date' => 'La fecha prometida debe ser una fecha válida.',
            'promised_at.after_or_equal' => 'La fecha prometida no puede ser anterior a la fecha de envío.',
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden exceder 2000 caracteres.',
            'branch_id.exists' => 'La sede seleccionada no existe.',
        ];
    }

    /**
     * Active user rule constrained to the roles that may hold the field.
     *
     * @param list<string> $roles
     */
    protected function roleRule(array $roles): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('users', 'id')
            ->where('is_active', true)
            ->whereIn('role', $roles);
    }
}
