<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;

/**
 * #287 (T2, S3/S4) — the counter's charge.
 *
 * The charge is a row of the patient ledger, not a transaction: it does not
 * move cash, so it asks for no method and no open drawer. The amount is money
 * owed and therefore positive, and the date cannot be in the future because the
 * debt cannot be recorded before it exists.
 */
class StorePatientChargeRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        // Authorization is the route's `permission:` middleware; this request
        // only owns the shape of the payload.
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'charged_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'amount.required' => 'El importe del cargo es requerido.',
            'amount.numeric' => 'El importe del cargo debe ser numérico.',
            'amount.min' => 'El importe del cargo debe ser mayor que cero.',
            'description.required' => 'La descripción del cargo es requerida.',
            'description.string' => 'La descripción del cargo debe ser texto.',
            'description.max' => 'La descripción del cargo no puede exceder 255 caracteres.',
            'charged_at.date' => 'La fecha del cargo debe ser una fecha válida.',
            'charged_at.before_or_equal' => 'La fecha del cargo no puede ser futura.',
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
        ];
    }
}
