<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRecallRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'interval_months' => ['required', Rule::in([3, 6, 12])],
            'reason' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'El paciente es requerido.',
            'patient_id.exists' => 'El paciente seleccionado no existe.',
            'interval_months.required' => 'El intervalo de control es requerido.',
            'interval_months.in' => 'El intervalo de control debe ser de 3, 6 o 12 meses.',
            'reason.required' => 'El motivo del recall es requerido.',
            'reason.string' => 'El motivo del recall debe ser texto.',
            'reason.max' => 'El motivo del recall no puede exceder 255 caracteres.',
            'due_date.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a hoy.',
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
            'branch_id.exists' => 'La sede seleccionada no existe.',
        ];
    }
}
